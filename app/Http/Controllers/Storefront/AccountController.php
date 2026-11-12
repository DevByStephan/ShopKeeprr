<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Subscription;
use App\Services\SubscriptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function index(): View
    {
        $user = auth()->user();
        $orders = $user->orders()->latest()->take(3)->get();
        $subscriptions = $user->subscriptions()->with(['product', 'plan'])->latest()->take(3)->get();

        return nexus_view('account.index', compact('user', 'orders', 'subscriptions'));
    }

    public function orders(): View
    {
        $orders = auth()->user()->orders()->latest()->paginate(10);

        return nexus_view('account.orders', compact('orders'));
    }

    public function order(Order $order): View
    {
        abort_unless($order->user_id === auth()->id(), 404);
        $order->load(['items.product', 'items.variant', 'payments', 'notes' => fn ($query) => $query->where('is_customer_visible', true)]);

        return nexus_view('account.order', compact('order'));
    }

    public function subscriptions(): View
    {
        $subscriptions = auth()->user()->subscriptions()->with(['product', 'plan'])->latest()->paginate(10);

        return nexus_view('account.subscriptions', compact('subscriptions'));
    }

    public function cancelSubscription(Subscription $subscription, SubscriptionService $service): RedirectResponse
    {
        abort_if(nexus_demo(), 403, 'Account changes are disabled in this demo.');
        abort_unless($subscription->user_id === auth()->id(), 404);

        if (! $subscription->isActive()) {
            return back()->withErrors(['subscription' => 'This subscription is not active.']);
        }

        try {
            $service->cancelWithGateway($subscription, false);
        } catch (\Throwable $exception) {
            return back()->withErrors(['subscription' => $exception->getMessage()]);
        }

        return back()->with('status', 'Your subscription will end at the close of the current billing period.');
    }

    public function profile(): View
    {
        return nexus_view('account.profile', ['user' => auth()->user()]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        abort_if(nexus_demo(), 403, 'Account changes are disabled in this demo.');

        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:40'],
            'company_name' => ['nullable', 'string', 'max:160'],
            'tax_id' => ['nullable', 'string', 'max:60'],
        ]);

        $user->update($data);

        return back()->with('status', 'Profile updated.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        abort_if(nexus_demo(), 403, 'Account changes are disabled in this demo.');

        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:10', 'confirmed'],
        ]);

        $request->user()->update(['password' => Hash::make($data['password'])]);

        return back()->with('status', 'Password updated.');
    }
}
