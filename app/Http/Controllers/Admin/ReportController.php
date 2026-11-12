<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request): View
    {
        $data = $this->data($request);

        return view('admin.reports.index', $data);
    }

    public function export(Request $request): StreamedResponse
    {
        $data = $this->data($request);

        return response()->streamDownload(function () use ($data) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Order', 'Date', 'Customer', 'Email', 'Status', 'Payment', 'Subtotal', 'Discount', 'Shipping', 'Tax', 'Total', 'Refunded']);
            foreach ($data['orders'] as $order) {
                fputcsv($out, array_map([$this, 'safeCell'], [
                    $order->number,
                    $order->created_at?->toDateTimeString(),
                    $order->customer_name,
                    $order->customer_email,
                    $order->status->value ?? $order->status,
                    $order->payment_status->value ?? $order->payment_status,
                    number_format((float) $order->subtotal, 2, '.', ''),
                    number_format((float) $order->discount_total, 2, '.', ''),
                    number_format((float) $order->shipping_total, 2, '.', ''),
                    number_format((float) $order->tax_total, 2, '.', ''),
                    number_format((float) $order->grand_total, 2, '.', ''),
                    number_format((float) $order->refunded_total, 2, '.', ''),
                ]));
            }
            fclose($out);
        }, 'shopkeeprr-sales-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
    }

    private function data(Request $request): array
    {
        $from = $request->filled('from') ? $request->date('from')->startOfDay() : now()->subDays(29)->startOfDay();
        $to = $request->filled('to') ? $request->date('to')->endOfDay() : now()->endOfDay();

        $base = DB::table('orders')
            ->whereIn('payment_status', ['paid', 'partially_refunded'])
            ->whereBetween('created_at', [$from, $to]);

        $summary = (clone $base)->selectRaw('COUNT(*) as orders, SUM(paid_total - refunded_total) as revenue, AVG(grand_total) as average, SUM(tax_total) as tax, SUM(shipping_total) as shipping, SUM(discount_total) as discounts')->first();

        $daily = (clone $base)
            ->selectRaw('DATE(created_at) as day, SUM(paid_total - refunded_total) as revenue, COUNT(*) as orders')
            ->groupBy('day')
            ->orderBy('day')
            ->get();

        $topProducts = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereIn('orders.payment_status', ['paid', 'partially_refunded'])
            ->whereBetween('orders.created_at', [$from, $to])
            ->selectRaw('order_items.product_name, SUM(order_items.quantity) as quantity, SUM(order_items.line_total) as revenue')
            ->groupBy('order_items.product_name')
            ->orderByDesc('revenue')
            ->limit(10)
            ->get();

        $payments = (clone $base)
            ->selectRaw('payment_method, COUNT(*) as orders, SUM(paid_total - refunded_total) as revenue')
            ->groupBy('payment_method')
            ->get();

        $orders = (clone $base)->orderByDesc('created_at')->get();

        return compact('from', 'to', 'summary', 'daily', 'topProducts', 'payments', 'orders');
    }

    private function safeCell(mixed $value): string
    {
        $value = (string) $value;

        return preg_match('/^[=+\-@]/', $value) ? "'".$value : $value;
    }
}
