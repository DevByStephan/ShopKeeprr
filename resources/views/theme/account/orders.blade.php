@extends('theme.layouts.app')

@section('title', 'Your orders — '.nexus_setting('store.name'))

@section('content')
<div class="container-wide py-10 sm:py-14">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <div class="text-[10px] font-bold uppercase tracking-[.18em] text-[var(--link)]">My account</div>
            <h1 class="mt-2 font-display text-4xl tracking-tight">Your orders</h1>
        </div>
        <a href="{{ route('storefront.account.index') }}" class="text-xs font-bold uppercase tracking-[.1em] text-[var(--muted)] hover:text-[var(--link)]">← Account overview</a>
    </div>

    @if($orders->isEmpty())
        <div class="mt-8 rounded-2xl border border-dashed border-[var(--line)] bg-white p-10 text-center">
            <p class="text-sm text-[var(--muted)]">No orders yet.</p>
            <a href="{{ route('storefront.shop') }}" class="btn-primary mt-5">Start shopping</a>
        </div>
    @else
        <div class="table-wrap mt-8">
            <table class="data-table">
                <thead><tr><th>Order</th><th>Date</th><th>Status</th><th>Payment</th><th class="text-right">Total</th><th></th></tr></thead>
                <tbody>
                    @foreach($orders as $order)
                        <tr>
                            <td class="font-semibold">{{ $order->number }}</td>
                            <td>{{ $order->created_at?->format('M j, Y') }}</td>
                            <td><span class="badge badge-{{ nexus_status_badge($order->status->value) }}">{{ $order->status->label() }}</span></td>
                            <td><span class="badge badge-{{ nexus_status_badge($order->payment_status->value) }}">{{ $order->payment_status->label() }}</span></td>
                            <td class="text-right font-semibold">{{ nexus_money($order->grand_total, $order->currency) }}</td>
                            <td class="text-right"><a href="{{ route('storefront.account.order', $order) }}" class="text-xs font-bold text-[var(--link)]">View →</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-6">{{ $orders->links() }}</div>
    @endif
</div>
@endsection
