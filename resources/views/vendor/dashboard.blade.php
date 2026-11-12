@extends('vendor.layouts.app')

@section('title', 'Dashboard')
@section('eyebrow', 'Seller')
@section('heading', 'Dashboard')

@section('content')
<div class="mb-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
    <div class="card card-pad"><div class="text-[10px] font-bold uppercase tracking-[.14em] text-[#8a9892]">Balance</div><div class="mt-2 font-display text-3xl">{{ nexus_money($stats['balance']) }}</div><div class="mt-1 text-[11px] text-[#8a9892]">{{ $vendor->payoutHoldDays() }}-day hold after payment</div></div>
    <div class="card card-pad"><div class="text-[10px] font-bold uppercase tracking-[.14em] text-[#8a9892]">Lifetime paid</div><div class="mt-2 font-display text-3xl">{{ nexus_money($stats['paid']) }}</div></div>
    <div class="card card-pad"><div class="text-[10px] font-bold uppercase tracking-[.14em] text-[#8a9892]">Orders</div><div class="mt-2 font-display text-3xl">{{ $stats['orders'] }}</div></div>
    <div class="card card-pad"><div class="text-[10px] font-bold uppercase tracking-[.14em] text-[#8a9892]">Products</div><div class="mt-2 font-display text-3xl">{{ $stats['active_products'] }}<span class="text-base text-[#8a9892]"> / {{ $stats['products'] }}</span></div><div class="mt-1 text-[11px] text-[#8a9892]">active / total</div></div>
</div>

@if($vendor->payout_method === 'stripe' && ! $vendor->stripe_payouts_enabled)
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3">
        <div>
            <div class="text-sm font-bold text-amber-900">Finish your Stripe onboarding</div>
            <p class="mt-1 text-xs text-amber-800">Stripe needs a few details before automatic payouts can start.</p>
        </div>
        <a href="{{ route('vendor.payouts.index') }}" class="btn-primary !px-4 !py-2 text-xs">Continue</a>
    </div>
@endif

<div class="grid gap-5 xl:grid-cols-[1.3fr_.9fr]">
    <div class="card card-pad">
        <div class="flex items-center justify-between">
            <h2 class="font-display text-xl">Recent sales</h2>
            <a href="{{ route('vendor.orders.index') }}" class="text-[11px] font-bold text-[#0f766e]">All orders →</a>
        </div>
        <div class="mt-4 overflow-x-auto">
            <table class="data-table">
                <thead><tr><th>Order</th><th>Date</th><th>Status</th><th>You earn</th></tr></thead>
                <tbody>
                    @forelse($recent as $split)
                        <tr>
                            <td><a href="{{ route('vendor.orders.show', $split) }}" class="font-semibold hover:text-[#0f766e]">{{ $split->order?->number ?? '#'.$split->order_id }}</a></td>
                            <td class="text-xs">{{ $split->created_at?->format('M j, Y') }}</td>
                            <td><span class="badge {{ $split->status === 'paid' ? 'badge-emerald' : ($split->status === 'reversed' ? 'badge-rose' : 'badge-amber') }}">{{ ucfirst($split->status) }}</span></td>
                            <td class="text-sm font-semibold">{{ nexus_money($split->payout_total) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="py-8 text-center text-sm text-[#8a9892]">No sales yet. Add a product to get started.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="space-y-5">
        <div class="card card-pad">
            <div class="flex items-center justify-between">
                <h2 class="font-display text-lg">Your products</h2>
                <a href="{{ route('vendor.products.index') }}" class="text-[11px] font-bold text-[#0f766e]">Manage</a>
            </div>
            <div class="mt-3 space-y-2">
                @forelse($products as $product)
                    <a href="{{ route('vendor.products.edit', $product) }}" class="flex items-center gap-3 rounded-lg border border-[#eef2ef] p-2 hover:border-[#cfe0d8]">
                        <div class="h-9 w-9 overflow-hidden rounded-lg bg-[#eef2ef]">
                            @if($product->imageUrl())<img src="{{ $product->imageUrl() }}" alt="" class="h-full w-full object-cover">@endif
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="truncate text-xs font-semibold">{{ $product->name }}</div>
                            <div class="text-[10px] text-[#8a9892]">{{ nexus_money($product->price) }} · {{ $product->is_active ? 'Active' : 'Draft' }}</div>
                        </div>
                    </a>
                @empty
                    <p class="text-xs text-[#8a9892]">Nothing listed yet. <a href="{{ route('vendor.products.create') }}" class="font-bold text-[#0f766e]">Add a product →</a></p>
                @endforelse
            </div>
        </div>

        <div class="card card-pad !bg-[#f7f4ec]">
            <h2 class="font-display text-lg">How you get paid</h2>
            <p class="mt-2 text-xs leading-5 text-[#6a6047]">
                Your item subtotal, after any order discount, is split between you and the store. The store keeps the commission shown on your payout page; shipping and tax stay with the store. Refunds reverse the matching seller balance before it is paid out.
            </p>
            <a href="{{ route('vendor.payouts.index') }}" class="mt-3 inline-block text-xs font-bold text-[#0f766e]">Payout settings →</a>
        </div>
    </div>
</div>
@endsection
