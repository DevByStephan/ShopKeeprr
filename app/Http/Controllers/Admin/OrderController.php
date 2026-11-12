<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderService;
use App\Services\RefundService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class OrderController extends Controller
{
    public function __construct(
        private readonly OrderService $orders,
    ) {
    }

    public function index(Request $request): View
    {
        $query = Order::query()->with('user')->latest();

        if ($request->filled('q')) {
            $query->search($request->string('q')->toString());
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->filled('payment_status')) {
            $query->where('payment_status', $request->string('payment_status')->toString());
        }

        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->date('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->date('to'));
        }

        $abandonedHours = max(1, (int) nexus_setting('orders.abandoned_after_hours', 24));
        $retentionDays = max(1, (int) nexus_setting('orders.abandoned_retention_days', 30));
        $showAbandoned = $request->boolean('show_abandoned')
            || $request->filled('status')
            || $request->filled('payment_status');

        if (! $showAbandoned) {
            $retentionCutoff = now()->subDays($retentionDays);

            // Orders that never took money leave this screen once they pass the
            // retention window. Paid, refunded and recent orders stay; nothing
            // is deleted from the database and filters can reveal the rest.
            $query->where(function ($builder) use ($retentionCutoff): void {
                $builder->where('paid_total', '>', 0)
                    ->orWhere('refunded_total', '>', 0)
                    ->orWhere('created_at', '>=', $retentionCutoff);
            });
        }

        $orders = $query->paginate(15)->withQueryString();

        return view('admin.orders.index', compact('orders', 'abandonedHours', 'retentionDays', 'showAbandoned'));
    }

    public function show(Order $order): View
    {
        $order->load([
            'user',
            'items.product',
            'items.variant',
            'payments',
            'refunds.user',
            'notes.user',
            'statusHistory.user',
            'subscriptions.product',
            'coupon',
        ]);

        return view('admin.orders.show', compact('order'));
    }

    public function updateStatus(Request $request, Order $order): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::enum(OrderStatus::class)],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $this->orders->updateStatus($order, OrderStatus::from($data['status']), $request->user(), $data['note'] ?? null);
        } catch (Throwable $exception) {
            return back()->withErrors(['order' => $exception->getMessage()]);
        }

        return back()->with('status', 'Order status updated.');
    }

    public function markPaid(Request $request, Order $order): RedirectResponse
    {
        if ($order->isPaid()) {
            return back()->with('status', 'This order is already marked paid.');
        }

        $this->orders->markPaid($order, $order->payment_method ?: 'manual', 'manual-'.now()->timestamp, [
            'marked_by' => $request->user()->email,
        ]);

        return back()->with('status', 'Order marked as paid.');
    }

    public function cancel(Request $request, Order $order): RedirectResponse
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        try {
            $this->orders->cancel($order, $request->user(), $data['reason'] ?? 'Cancelled by store administrator.');
        } catch (Throwable $exception) {
            return back()->withErrors(['order' => $exception->getMessage()]);
        }

        return back()->with('status', 'Order cancelled and inventory restored.');
    }

    public function note(Request $request, Order $order): RedirectResponse
    {
        $data = $request->validate([
            'note' => ['required', 'string', 'max:2000'],
            'is_customer_visible' => ['nullable', 'boolean'],
        ]);

        $this->orders->addNote($order, $data['note'], $request->boolean('is_customer_visible'), $request->user());

        return back()->with('status', 'Note added.');
    }

    public function refund(Request $request, Order $order, RefundService $refunds): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:'.$order->paid_total],
            'reason' => ['nullable', 'string', 'max:500'],
            // Generated per render: a resubmitted or duplicated refund form is
            // treated as the same refund instead of moving money twice.
            'refund_token' => ['required', 'string', 'max:64'],
        ]);

        try {
            $refunds->refund(
                $order,
                (float) $data['amount'],
                $data['reason'] ?? null,
                $request->user(),
                $data['refund_token'] ?? null,
            );
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        } catch (Throwable $exception) {
            return back()->withErrors(['refund' => $exception->getMessage()]);
        }

        return back()->with('status', 'Refund recorded.');
    }
}
