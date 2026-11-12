<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Services\SubscriptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class SubscriptionController extends Controller
{
    public function index(Request $request): View
    {
        $query = Subscription::query()->with(['user', 'product', 'plan'])->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->filled('q')) {
            $term = '%'.$request->string('q')->toString().'%';
            $query->whereHas('user', fn ($sub) => $sub->where('name', 'like', $term)->orWhere('email', 'like', $term))
                ->orWhereHas('product', fn ($sub) => $sub->where('name', 'like', $term));
        }

        $subscriptions = $query->paginate(20)->withQueryString();

        return view('admin.subscriptions.index', compact('subscriptions'));
    }

    public function show(Subscription $subscription): View
    {
        $subscription->load(['user', 'product', 'variant', 'plan', 'order', 'events']);

        return view('admin.subscriptions.show', compact('subscription'));
    }

    public function action(Request $request, Subscription $subscription, SubscriptionService $service): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', 'in:cancel,pause,resume'],
            'immediately' => ['nullable', 'boolean'],
        ]);

        try {
            match ($data['action']) {
                'cancel' => $service->cancelWithGateway($subscription, $request->boolean('immediately')),
                'pause' => $service->pauseWithGateway($subscription),
                'resume' => $service->resumeWithGateway($subscription),
            };
        } catch (Throwable $exception) {
            return back()->withErrors(['subscription' => $exception->getMessage()]);
        }

        return back()->with('status', 'Subscription updated.');
    }
}
