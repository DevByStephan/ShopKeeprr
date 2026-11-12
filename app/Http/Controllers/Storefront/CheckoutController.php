<?php

namespace App\Http\Controllers\Storefront;

use App\Enums\StoreMode;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\CartService;
use App\Services\OrderService;
use App\Services\Payments\PaymentException;
use App\Services\SettingsService;
use App\Services\Payments\PaymentManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\View\View;
use Throwable;

class CheckoutController extends Controller
{
    public function __construct(
        private readonly CartService $cartService,
        private readonly OrderService $orderService,
        private readonly SettingsService $settings,
    ) {
    }

    public function index(): View|RedirectResponse
    {
        $cart = $this->cartService->current(false);

        if (! $cart || $cart->isEmpty()) {
            return redirect()->route('storefront.cart')->with('status', 'Add something to your bag first.');
        }

        $mode = nexus_mode();

        if (! $mode) {
            return redirect()->route('storefront.cart')->withErrors(['cart' => 'The store is not configured for checkout yet.']);
        }

        $result = $this->cartService->reconcileCart($cart, $mode, $this->settings->modeRevision());

        if ($cart->items->isEmpty()) {
            return redirect()->route('storefront.cart')->withErrors(['cart' => 'Your bag no longer contains items available in this store model.']);
        }

        if ($result['invalidated']) {
            return redirect()->route('storefront.cart')->with('status', 'Your bag changed while the store model was updated. Review the items and continue.');
        }

        $gateways = app(PaymentManager::class)->available($mode === StoreMode::Subscription);

        if (! $gateways) {
            return redirect()->route('storefront.cart')->withErrors(['payment' => 'No payment method is configured yet. Please contact the store.']);
        }

        $user = Auth::user();
        $lastOrder = $user?->orders()->latest()->first();
        $address = $lastOrder?->shipping_address ?? [
            'name' => $user?->name,
            'line1' => '',
            'line2' => '',
            'city' => '',
            'state' => '',
            'postcode' => '',
            'country' => nexus_setting('store.country', 'US'),
        ];
        $totals = $this->cartService->totals($cart, $address);

        return nexus_view('checkout.index', compact('cart', 'totals', 'gateways', 'address', 'mode'));
    }

    public function shipping(Request $request): JsonResponse
    {
        $data = $request->validate([
            'country' => ['required', 'string', 'size:2'],
            'state' => ['nullable', 'string', 'max:80'],
        ]);

        $cart = $this->cartService->current(false);
        if (! $cart) {
            return response()->json(['options' => [], 'totals' => null]);
        }

        if ($mode = nexus_mode()) {
            $this->cartService->reconcileCart($cart, $mode, $this->settings->modeRevision());
        }

        $totals = $this->cartService->totals($cart, [
            'country' => strtoupper($data['country']),
            'state' => $data['state'] ?? null,
        ]);

        return response()->json([
            'options' => $totals['options'],
            'totals' => [
                'subtotal' => $totals['subtotal'],
                'discount' => $totals['discount'],
                'shipping' => $totals['shipping'],
                'tax' => $totals['tax'],
                'total' => $totals['total'],
            ],
        ]);
    }

    public function place(Request $request): RedirectResponse
    {
        $cart = $this->cartService->current(false);

        if (! $cart || $cart->isEmpty()) {
            return redirect()->route('storefront.cart')->withErrors(['cart' => 'Your bag is empty.']);
        }

        $mode = nexus_mode();

        if (! $mode) {
            return redirect()->route('storefront.cart')->withErrors(['cart' => 'The store is not configured for checkout yet.']);
        }

        $reconciled = $this->cartService->reconcileCart($cart, $mode, $this->settings->modeRevision());

        if ($cart->items->isEmpty() || $reconciled['invalidated']) {
            return redirect()->route('storefront.cart')->withErrors(['cart' => 'Some items are no longer available in this store model. Review your bag before paying.']);
        }

        $data = $request->validate([
            'customer_name' => ['required', 'string', 'max:120'],
            'customer_email' => ['required', 'email', 'max:190'],
            'customer_phone' => ['nullable', 'string', 'max:40'],
            'line1' => ['required', 'string', 'max:160'],
            'line2' => ['nullable', 'string', 'max:160'],
            'city' => ['required', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:80'],
            'postcode' => ['required', 'string', 'max:24'],
            'country' => ['required', 'string', 'size:2'],
            'shipping_method_id' => ['nullable', 'integer'],
            'payment_method' => ['required', 'string', 'max:30'],
            'customer_note' => ['nullable', 'string', 'max:1000'],
            'marketing_opt_in' => ['nullable', 'boolean'],
            'terms' => ['accepted'],
        ]);

        $gateways = app(PaymentManager::class)->available(nexus_mode() === StoreMode::Subscription);
        if (! array_key_exists($data['payment_method'], $gateways)) {
            return back()->withInput()->withErrors(['payment_method' => 'Choose an available payment method.']);
        }

        $address = [
            'name' => $data['customer_name'],
            'line1' => $data['line1'],
            'line2' => $data['line2'] ?? null,
            'city' => $data['city'],
            'state' => $data['state'] ?? null,
            'postcode' => $data['postcode'],
            'country' => strtoupper($data['country']),
        ];

        try {
            $order = $this->orderService->createFromCart($cart, [
                'customer_name' => $data['customer_name'],
                'customer_email' => $data['customer_email'],
                'customer_phone' => $data['customer_phone'] ?? null,
                'shipping_address' => $address,
                'billing_address' => $address,
                'shipping_method_id' => $data['shipping_method_id'] ?? null,
                'payment_method' => $data['payment_method'],
                'customer_note' => $data['customer_note'] ?? null,
            ]);
        } catch (Throwable $exception) {
            return back()->withInput()->withErrors(['checkout' => $exception->getMessage()]);
        }

        if ($request->boolean('marketing_opt_in')) {
            Event::dispatch('marketing.opt-in', [[
                'consent' => true,
                'email' => $data['customer_email'],
                'name' => $data['customer_name'],
                'user_id' => Auth::id(),
                'source' => 'checkout',
                'order_id' => $order->id,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'tags' => ['customer', 'checkout'],
            ]]);
        }

        // A fully discounted order has nothing to charge. Sending a zero amount
        // to Stripe or PayPal would fail and mark the order failed, so settle
        // it here as a completed order with no gateway payment.
        if ((float) $order->grand_total <= 0) {
            $this->orderService->markPaid($order, 'free', 'free-'.$order->number, ['reason' => 'No payment required.']);

            return redirect()->to($order->confirmationUrl());
        }

        try {
            $result = app(PaymentManager::class)->get($data['payment_method'])->start($order);
        } catch (PaymentException $exception) {
            $this->orderService->markFailed($order, $data['payment_method']);

            return back()->withInput()->withErrors(['payment' => $exception->getMessage()]);
        } catch (Throwable $exception) {
            report($exception);
            $this->orderService->markFailed($order, $data['payment_method']);

            return back()->withInput()->withErrors(['payment' => 'The payment provider is unavailable right now. Please try again.']);
        }

        if ($result->isRedirect()) {
            return redirect()->away($result->redirectUrl);
        }

        return redirect()->to($order->confirmationUrl());
    }

    public function confirmation(Order $order, Request $request): View
    {
        $access = (string) $request->query('access', '');
        $user = Auth::user();

        abort_unless(
            ($user && ($user->isAdmin() || $order->user_id === $user->id))
            || ($order->access_token && hash_equals($order->access_token, $access)),
            403
        );

        if (! $order->isPaid() && $request->filled('gateway')) {
            try {
                $gateway = app(PaymentManager::class)->get((string) $request->query('gateway'));

                if ($gateway->code() === 'stripe') {
                    $gateway->complete($order, ['session_id' => $request->query('session_id')]);
                }

                if ($gateway->code() === 'paypal') {
                    $gateway->complete($order, [
                        'token' => $request->query('token'),
                        'subscription_id' => $request->query('subscription_id'),
                    ]);
                }
            } catch (Throwable $exception) {
                report($exception);
            }

            $order->refresh();
        }

        $order->load(['items.product', 'items.variant', 'payments', 'notes' => fn ($query) => $query->where('is_customer_visible', true)]);

        return nexus_view('checkout.confirmation', compact('order'));
    }
}
