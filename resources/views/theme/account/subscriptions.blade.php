@extends('theme.layouts.app')

@section('title', 'Your subscriptions — '.nexus_setting('store.name'))

@section('content')
<div class="container-wide py-10 sm:py-14">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <div class="text-[10px] font-bold uppercase tracking-[.18em] text-[var(--link)]">My account</div>
            <h1 class="mt-2 font-display text-4xl tracking-tight">Subscriptions</h1>
        </div>
        <a href="{{ route('storefront.account.index') }}" class="text-xs font-bold uppercase tracking-[.1em] text-[var(--muted)] hover:text-[var(--link)]">← Account overview</a>
    </div>

    @if($subscriptions->isEmpty())
        <div class="mt-8 rounded-2xl border border-dashed border-[var(--line)] bg-white p-10 text-center">
            <p class="text-sm text-[var(--muted)]">You do not have any subscriptions yet.</p>
            <a href="{{ route('storefront.shop') }}" class="btn-primary mt-5">Browse subscriptions</a>
        </div>
    @else
        <div class="mt-8 space-y-3">
            @foreach($subscriptions as $subscription)
                <div class="rounded-2xl border border-[var(--line)] bg-white p-5">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <div class="font-display text-xl">{{ $subscription->product?->name }}</div>
                            <div class="mt-1 text-xs text-[var(--muted)]">{{ $subscription->plan?->name }} · {{ nexus_money($subscription->price, $subscription->currency) }} every {{ $subscription->intervalLabel() }}</div>
                            <div class="mt-2 text-xs text-[var(--muted)]">
                                Status: <span class="font-semibold">{{ $subscription->status->label() }}</span>
                                @if($subscription->current_period_end)
                                    · {{ $subscription->status->value === 'cancelled' ? 'Ends' : 'Renews' }} {{ $subscription->current_period_end->format('M j, Y') }}
                                @endif
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="badge badge-{{ nexus_status_badge($subscription->status->value) }}">{{ $subscription->status->label() }}</span>
                            @if($subscription->isActive())
                                <form method="post" action="{{ route('storefront.account.subscription.cancel', $subscription) }}" onsubmit="return confirm('Cancel this subscription at the end of the current period?')">
                                    @csrf
                                    <button class="btn-secondary !px-4 !py-2 text-xs">Cancel subscription</button>
                                </form>
                            @else
                                <a href="{{ route('storefront.product', $subscription->product?->slug) }}" class="btn-secondary !px-4 !py-2 text-xs">Resubscribe</a>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
        <div class="mt-6">{{ $subscriptions->links() }}</div>
    @endif
</div>
@endsection
