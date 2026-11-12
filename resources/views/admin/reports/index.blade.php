@extends('admin.layouts.app')

@section('title', 'Reports')
@section('eyebrow', 'Operations')
@section('heading', 'Sales reports')

@section('content')
@php($maxDaily = max(1, (float) $daily->max('revenue')))
<form method="get" class="card mb-5 flex flex-wrap items-end gap-3 p-4">
    <div><label class="label" for="from">From</label><input id="from" type="date" name="from" value="{{ $from->format('Y-m-d') }}" class="input"></div>
    <div><label class="label" for="to">To</label><input id="to" type="date" name="to" value="{{ $to->format('Y-m-d') }}" class="input"></div>
    <button class="btn-primary !px-5 !py-2.5 text-xs">Apply</button>
    <a href="{{ route('admin.reports.export', request()->only(['from','to'])) }}" class="btn-secondary !px-5 !py-2.5 text-xs">Export CSV</a>
    <div class="ml-auto text-xs text-[#8a9892]">{{ $from->format('M j, Y') }} → {{ $to->format('M j, Y') }}</div>
</form>

<div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
    <div class="stat-card"><div class="stat-label">Net revenue</div><div class="stat-value">{{ nexus_money($summary->revenue ?? 0) }}</div></div>
    <div class="stat-card"><div class="stat-label">Orders</div><div class="stat-value">{{ number_format($summary->orders ?? 0) }}</div></div>
    <div class="stat-card"><div class="stat-label">Average order</div><div class="stat-value">{{ nexus_money($summary->average ?? 0) }}</div></div>
    <div class="stat-card"><div class="stat-label">Tax collected</div><div class="stat-value">{{ nexus_money($summary->tax ?? 0) }}</div></div>
</div>

<div class="mt-5 grid gap-5 xl:grid-cols-[1.6fr_1fr]">
    <div class="card card-pad">
        <h2 class="font-display text-xl">Daily revenue</h2>
        @if($daily->isEmpty())
            <p class="mt-5 text-sm text-[#8a9892]">No paid orders in this period.</p>
        @else
            <div class="mt-6 flex h-52 items-end gap-1">
                @foreach($daily as $day)
                    <div class="group flex h-full flex-1 flex-col justify-end" title="{{ $day->day }} · {{ nexus_money($day->revenue) }}">
                        <div class="relative flex-1">
                            <div class="absolute bottom-0 w-full rounded-t bg-[#0f766e]/20" style="height: {{ max(3, ($day->revenue / $maxDaily) * 100) }}%"></div>
                            <div class="absolute bottom-0 w-full rounded-t bg-[#0f766e]" style="height: {{ ($day->revenue / $maxDaily) * 100 }}%"></div>
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="mt-2 flex justify-between text-[10px] text-[#8a9892]"><span>{{ $from->format('M j') }}</span><span>{{ $to->format('M j') }}</span></div>
        @endif
    </div>

    <div class="space-y-5">
        <div class="table-wrap">
            <div class="border-b border-[#e3e9e5] px-5 py-4"><h2 class="font-display text-xl">Payment methods</h2></div>
            <table class="data-table">
                <thead><tr><th>Method</th><th class="text-right">Orders</th><th class="text-right">Revenue</th></tr></thead>
                <tbody>
                    @forelse($payments as $payment)
                        <tr><td class="text-sm capitalize">{{ $payment->payment_method ?: 'Unknown' }}</td><td class="text-right text-xs">{{ $payment->orders }}</td><td class="text-right text-sm font-semibold">{{ nexus_money($payment->revenue) }}</td></tr>
                    @empty
                        <tr><td colspan="3" class="py-6 text-center text-sm text-[#8a9892]">No payments.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card card-pad">
            <div class="text-[10px] font-bold uppercase tracking-[.14em] text-[#8a9892]">Discounts & shipping</div>
            <div class="mt-3 flex justify-between text-sm"><span class="text-[#687771]">Discounts given</span><span class="font-semibold">{{ nexus_money($summary->discounts ?? 0) }}</span></div>
            <div class="mt-2 flex justify-between text-sm"><span class="text-[#687771]">Shipping collected</span><span class="font-semibold">{{ nexus_money($summary->shipping ?? 0) }}</span></div>
        </div>
    </div>
</div>

<div class="table-wrap mt-5">
    <div class="flex items-center justify-between border-b border-[#e3e9e5] px-5 py-4">
        <h2 class="font-display text-xl">Top products</h2>
        <span class="text-xs text-[#8a9892]">{{ $orders->count() }} {{ Str::plural('order', $orders->count()) }} in range</span>
    </div>
    <table class="data-table">
        <thead><tr><th>Product</th><th class="text-right">Units</th><th class="text-right">Revenue</th></tr></thead>
        <tbody>
            @forelse($topProducts as $product)
                <tr><td class="text-sm font-medium">{{ $product->product_name }}</td><td class="text-right text-xs">{{ $product->quantity }}</td><td class="text-right text-sm font-semibold">{{ nexus_money($product->revenue) }}</td></tr>
            @empty
                <tr><td colspan="3" class="py-8 text-center text-sm text-[#8a9892]">No product sales in this period.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
