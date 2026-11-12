@extends('theme.layouts.app')

@section('title', 'My account — '.nexus_setting('store.name'))

@section('content')
<div class="container-wide py-10 sm:py-14">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <div class="text-[10px] font-bold uppercase tracking-[.18em] text-[var(--link)]">My account</div>
            <h1 class="mt-2 font-display text-4xl tracking-tight">Hello, {{ $user->firstName() }}.</h1>
            <p class="mt-1 text-sm text-[var(--muted)]">{{ $user->email }}</p>
        </div>
        <nav class="flex flex-wrap gap-2">
            <a href="{{ route('storefront.account.orders') }}" class="btn-secondary !px-4 !py-2 text-xs">Orders</a>
            @if(nexus_mode()?->value === 'subscription')
                <a href="{{ route('storefront.account.subscriptions') }}" class="btn-secondary !px-4 !py-2 text-xs">Subscriptions</a>
            @endif
            @if(\Illuminate\Support\Facades\Route::has('wishlist.index'))
                <a href="{{ route('wishlist.index') }}" class="btn-secondary !px-4 !py-2 text-xs">Wishlist</a>
            @endif
            @if(\Illuminate\Support\Facades\Route::has('loyalty.index'))
                <a href="{{ route('loyalty.index') }}" class="btn-secondary !px-4 !py-2 text-xs">Rewards</a>
            @endif
            <a href="{{ route('storefront.account.profile') }}" class="btn-secondary !px-4 !py-2 text-xs">Profile</a>
        </nav>
    </div>

    <div class="mt-8 grid gap-8 lg:grid-cols-[1.4fr_.6fr]">
        <section>
            <div class="flex items-center justify-between">
                <h2 class="font-display text-2xl">Recent orders</h2>
                <a href="{{ route('storefront.account.orders') }}" class="text-xs font-bold uppercase tracking-[.1em] text-[var(--muted)] hover:text-[var(--link)]">All orders →</a>
            </div>
            @if($orders->isEmpty())
                <div class="mt-4 rounded-2xl border border-dashed border-[var(--line)] bg-white p-8 text-center">
                    <p class="text-sm text-[var(--muted)]">You have not placed an order yet.</p>
                    <a href="{{ route('storefront.shop') }}" class="btn-primary mt-5">Start shopping</a>
                </div>
            @else
                <div class="mt-4 space-y-3">
                    @foreach($orders as $order)
                        <a href="{{ route('storefront.account.order', $order) }}" class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-[var(--line)] bg-white p-4 transition hover:border-[#c8d8d1]">
                            <div>
                                <div class="text-sm font-bold">{{ $order->number }}</div>
                                <div class="mt-0.5 text-xs text-[var(--muted)]">{{ $order->created_at?->format('M j, Y') }} · {{ $order->items()->count() }} {{ Str::plural('item', $order->items()->count()) }}</div>
                            </div>
                            <div class="flex items-center gap-4">
                                <span class="badge badge-{{ nexus_status_badge($order->status->value) }}">{{ $order->status->label() }}</span>
                                <span class="text-sm font-semibold">{{ nexus_money($order->grand_total, $order->currency) }}</span>
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif

            @if($subscriptions->isNotEmpty())
                <div class="mt-10 flex items-center justify-between">
                    <h2 class="font-display text-2xl">Subscriptions</h2>
                    <a href="{{ route('storefront.account.subscriptions') }}" class="text-xs font-bold uppercase tracking-[.1em] text-[var(--muted)] hover:text-[var(--link)]">Manage →</a>
                </div>
                <div class="mt-4 space-y-3">
                    @foreach($subscriptions as $subscription)
                        <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-[var(--line)] bg-white p-4">
                            <div>
                                <div class="text-sm font-bold">{{ $subscription->product?->name }}</div>
                                <div class="mt-0.5 text-xs text-[var(--muted)]">Every {{ $subscription->intervalLabel() }} · next {{ $subscription->current_period_end?->format('M j, Y') ?? '—' }}</div>
                            </div>
                            <span class="badge badge-{{ nexus_status_badge($subscription->status->value) }}">{{ $subscription->status->label() }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>

        <aside class="space-y-4">
            <div class="rounded-2xl border border-[var(--line)] bg-white p-5">
                <div class="text-[10px] font-bold uppercase tracking-[.14em] text-[var(--muted)]">Account details</div>
                <div class="mt-3 space-y-2 text-sm">
                    <div class="font-semibold">{{ $user->name }}</div>
                    <div class="text-[var(--muted)]">{{ $user->email }}</div>
                    @if($user->phone)<div class="text-[var(--muted)]">{{ $user->phone }}</div>@endif
                    @if($user->company_name)<div class="text-[var(--muted)]">{{ $user->company_name }}</div>@endif
                </div>
                <a href="{{ route('storefront.account.profile') }}" class="mt-4 inline-block text-xs font-bold text-[var(--link)] underline underline-offset-4">Edit details</a>
            </div>
            @if(nexus_mode()?->value === 'b2b')
                <div class="rounded-2xl border border-[var(--line)] bg-[var(--surface-2)] p-5">
                    <div class="text-[10px] font-bold uppercase tracking-[.14em] text-[var(--muted)]">Trade account</div>
                    <div class="mt-2 text-sm font-semibold">{{ $user->wholesale_status?->label() ?? 'Not applied' }}</div>
                    <p class="mt-1 text-xs leading-5 text-[var(--muted)]">Trade pricing is applied automatically when your account is approved.</p>
                    <a href="{{ route('storefront.trade') }}" class="mt-3 inline-block text-xs font-bold text-[var(--link)] underline underline-offset-4">View trade program</a>
                </div>
            @endif
            <div class="rounded-2xl border border-[var(--line)] bg-white p-5">
                <div class="text-[10px] font-bold uppercase tracking-[.14em] text-[var(--muted)]">Need help?</div>
                <p class="mt-2 text-sm leading-6 text-[var(--muted)]">Questions about an order? Reply to your confirmation email or contact us directly.</p>
                <a href="mailto:{{ nexus_setting('store.email') }}" class="mt-3 inline-block text-xs font-bold text-[var(--link)] underline underline-offset-4">Contact support</a>
            </div>
        </aside>
    </div>
</div>
@endsection
