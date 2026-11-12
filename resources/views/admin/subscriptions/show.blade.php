@extends('admin.layouts.app')

@section('title', 'Subscription #'.$subscription->id)
@section('eyebrow', 'Subscriptions')
@section('heading', 'Subscription #'.$subscription->id)

@section('content')
<div class="mb-5 flex flex-wrap items-center justify-between gap-3">
    <div class="flex items-center gap-2">
        <span class="badge badge-{{ nexus_status_badge($subscription->status->value) }}">{{ $subscription->status->label() }}</span>
        <span class="text-xs text-[#8a9892]">Started {{ $subscription->created_at?->format('M j, Y') }}</span>
    </div>
    <a href="{{ route('admin.subscriptions.index') }}" class="text-xs font-bold uppercase tracking-[.1em] text-[#4d5d56] hover:text-[#0f766e]">← All subscriptions</a>
</div>

<div class="grid gap-5 xl:grid-cols-[1.4fr_1fr]">
    <div class="space-y-5">
        <div class="card card-pad">
            <h2 class="font-display text-xl">Subscription</h2>
            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <div><div class="text-[10px] font-bold uppercase tracking-[.12em] text-[#8a9892]">Product</div><div class="mt-1 text-sm font-semibold">{{ $subscription->product?->name ?? '—' }}</div>@if($subscription->variant)<div class="text-xs text-[#8a9892]">{{ $subscription->variant->displayName() }}</div>@endif</div>
                <div><div class="text-[10px] font-bold uppercase tracking-[.12em] text-[#8a9892]">Plan</div><div class="mt-1 text-sm font-semibold">{{ $subscription->plan?->name ?? '—' }}</div><div class="text-xs text-[#8a9892]">Every {{ $subscription->intervalLabel() }}</div></div>
                <div><div class="text-[10px] font-bold uppercase tracking-[.12em] text-[#8a9892]">Price</div><div class="mt-1 text-sm font-semibold">{{ nexus_money($subscription->price, $subscription->currency) }} × {{ $subscription->quantity }}</div></div>
                <div><div class="text-[10px] font-bold uppercase tracking-[.12em] text-[#8a9892]">Gateway</div><div class="mt-1 text-sm font-semibold capitalize">{{ $subscription->gateway ?: 'Manual' }}</div><div class="text-xs text-[#8a9892]">{{ $subscription->gateway_subscription_id ?: 'No gateway reference' }}</div></div>
                <div><div class="text-[10px] font-bold uppercase tracking-[.12em] text-[#8a9892]">Current period</div><div class="mt-1 text-sm">{{ $subscription->current_period_start?->format('M j, Y') ?? '—' }} → {{ $subscription->current_period_end?->format('M j, Y') ?? '—' }}</div></div>
                <div><div class="text-[10px] font-bold uppercase tracking-[.12em] text-[#8a9892]">Trial ends</div><div class="mt-1 text-sm">{{ $subscription->trial_ends_at?->format('M j, Y') ?? 'No trial' }}</div></div>
            </div>
        </div>

        <div class="table-wrap">
            <div class="border-b border-[#e3e9e5] px-5 py-4"><h2 class="font-display text-xl">Events</h2></div>
            <table class="data-table">
                <thead><tr><th>When</th><th>Event</th><th>Details</th></tr></thead>
                <tbody>
                    @forelse($subscription->events->sortByDesc('created_at') as $event)
                        <tr>
                            <td class="text-xs">{{ $event->created_at?->format('M j, Y g:i a') }}</td>
                            <td class="text-xs font-semibold capitalize">{{ str_replace('_', ' ', $event->type) }}</td>
                            <td class="text-xs text-[#687771]">{{ is_array($event->data) ? json_encode($event->data) : ($event->data ?? '—') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="py-8 text-center text-sm text-[#8a9892]">No events recorded.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="space-y-5">
        <div class="card card-pad">
            <h2 class="font-display text-xl">Actions</h2>
            <div class="mt-4 space-y-3">
                @foreach([
                    ['pause', 'Pause subscription', 'Stop renewals until resumed.', 'btn-secondary'],
                    ['resume', 'Resume subscription', 'Restart billing from the next period.', 'btn-secondary'],
                    ['cancel', 'Cancel at period end', 'Keep access until the current period ends.', 'btn-secondary'],
                ] as [$action, $label, $copy, $class])
                    <form method="post" action="{{ route('admin.subscriptions.action', $subscription) }}" @if($action === 'cancel') onsubmit="return confirm('Cancel this subscription at the end of the current period?')" @endif>
                        @csrf
                        <input type="hidden" name="action" value="{{ $action }}">
                        <button class="{{ $class }} w-full !justify-between !py-3 text-xs"><span>{{ $label }}</span><span class="text-[10px] font-normal text-[#8a9892]">{{ $copy }}</span></button>
                    </form>
                @endforeach
                <form method="post" action="{{ route('admin.subscriptions.action', $subscription) }}" onsubmit="return confirm('Cancel immediately? Access ends now.')">
                    @csrf
                    <input type="hidden" name="action" value="cancel">
                    <input type="hidden" name="immediately" value="1">
                    <button class="btn-danger w-full !py-2.5 text-xs">Cancel immediately</button>
                </form>
            </div>
        </div>
        <div class="card card-pad">
            <h2 class="font-display text-xl">Customer</h2>
            @if($subscription->user)
                <div class="mt-3 text-sm font-semibold">{{ $subscription->user->name }}</div>
                <div class="text-xs text-[#687771]">{{ $subscription->user->email }}</div>
                <a href="{{ route('admin.customers.show', $subscription->user) }}" class="mt-3 inline-block text-xs font-bold text-[#0f766e]">View customer →</a>
            @else
                <p class="mt-3 text-sm text-[#8a9892]">Guest subscription.</p>
            @endif
            @if($subscription->order)
                <a href="{{ route('admin.orders.show', $subscription->order) }}" class="mt-3 inline-block text-xs font-bold text-[#0f766e]">Order {{ $subscription->order->number }} →</a>
            @endif
        </div>
    </div>
</div>
@endsection
