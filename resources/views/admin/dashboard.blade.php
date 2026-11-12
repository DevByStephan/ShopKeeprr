@extends('admin.layouts.app')

@section('title', 'Dashboard')
@section('eyebrow', 'Overview')
@section('heading', 'Dashboard')

@section('content')
@php($maxSale = max(1, (float) $sales->max('total')))
<div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
    <div class="stat-card">
        <div class="stat-label">Net revenue</div>
        <div class="stat-value">{{ nexus_money($revenue) }}</div>
        <div class="mt-2 text-[11px] text-[#8a9892]">Paid orders, after refunds</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Orders</div>
        <div class="stat-value">{{ number_format($ordersCount) }}</div>
        <div class="mt-2 text-[11px] text-[#8a9892]">{{ $pendingOrders }} awaiting fulfilment</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Customers</div>
        <div class="stat-value">{{ number_format($customersCount) }}</div>
        <div class="mt-2 text-[11px] text-[#8a9892]">{{ $pendingWholesale }} trade {{ Str::plural('application', $pendingWholesale) }} pending</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Average order</div>
        <div class="stat-value">{{ nexus_money($averageOrder) }}</div>
        <div class="mt-2 text-[11px] text-[#8a9892]">{{ nexus_money($mrr) }} monthly recurring</div>
    </div>
</div>

<div class="mt-5 grid gap-5 xl:grid-cols-[1.6fr_1fr]">
    <div class="card card-pad">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div>
                <h2 class="font-display text-xl">Sales, last 14 days</h2>
                <p class="text-xs text-[#8a9892]">Paid revenue by day</p>
            </div>
            <div class="text-right">
                <div class="text-[10px] font-bold uppercase tracking-[.12em] text-[#8a9892]">Total</div>
                <div class="text-sm font-bold">{{ nexus_money($sales->sum('total')) }}</div>
            </div>
        </div>
        <div class="mt-7 flex h-44 items-end gap-1.5">
            @foreach($sales as $day)
                <div class="group flex h-full flex-1 flex-col justify-end">
                    <div class="relative flex-1">
                        <div class="absolute bottom-0 w-full rounded-t-md bg-[#0f766e]/15 transition group-hover:bg-[#0f766e]/30" style="height: {{ max(4, ((float) $day['total'] / $maxSale) * 100) }}%"></div>
                        <div class="absolute bottom-0 w-full rounded-t-md bg-[#0f766e]" style="height: {{ ((float) $day['total'] / $maxSale) * 100 }}%"></div>
                    </div>
                    <div class="mt-2 truncate text-center text-[9px] text-[#8a9892]">{{ $day['label'] }}</div>
                </div>
            @endforeach
        </div>
    </div>

    <div class="card card-pad">
        <div class="flex items-center justify-between">
            <h2 class="font-display text-xl">Top products</h2>
            <a href="{{ route('admin.reports.index') }}" class="text-[11px] font-bold text-[#0f766e]">Reports →</a>
        </div>
        @forelse($topProducts as $product)
            <div class="mt-4 flex items-center justify-between gap-3 border-b border-[#edf2ef] pb-3 last:border-0">
                <div class="min-w-0">
                    <div class="truncate text-sm font-semibold">{{ $product->product_name }}</div>
                    <div class="text-[11px] text-[#8a9892]">{{ $product->quantity }} sold</div>
                </div>
                <div class="text-sm font-semibold">{{ nexus_money($product->revenue) }}</div>
            </div>
        @empty
            <p class="mt-4 text-sm text-[#8a9892]">No paid orders yet.</p>
        @endforelse
    </div>
</div>

<div class="mt-5 grid gap-5 xl:grid-cols-[1.6fr_1fr]">
    <div class="table-wrap">
        <div class="flex items-center justify-between border-b border-[#e3e9e5] px-5 py-4">
            <h2 class="font-display text-xl">Recent orders</h2>
            <a href="{{ route('admin.orders.index') }}" class="text-[11px] font-bold text-[#0f766e]">All orders →</a>
        </div>
        <table class="data-table">
            <thead><tr><th>Order</th><th>Customer</th><th>Status</th><th>Payment</th><th class="text-right">Total</th></tr></thead>
            <tbody>
                @forelse($recentOrders as $order)
                    <tr>
                        <td><a href="{{ route('admin.orders.show', $order) }}" class="font-semibold text-[#0f766e]">{{ $order->number }}</a><div class="text-[11px] text-[#8a9892]">{{ $order->created_at?->diffForHumans() }}</div></td>
                        <td><div class="text-sm">{{ $order->customer_name }}</div><div class="text-[11px] text-[#8a9892]">{{ $order->customer_email }}</div></td>
                        <td><span class="badge badge-{{ nexus_status_badge($order->status->value) }}">{{ $order->status->label() }}</span></td>
                        <td><span class="badge badge-{{ nexus_status_badge($order->payment_status->value) }}">{{ $order->payment_status->label() }}</span></td>
                        <td class="text-right font-semibold">{{ nexus_money($order->grand_total, $order->currency) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-8 text-center text-sm text-[#8a9892]">No orders yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="space-y-5">
        <div class="card card-pad">
            <div class="flex items-center justify-between">
                <h2 class="font-display text-xl">Low stock</h2>
                <a href="{{ route('admin.products.index') }}" class="text-[11px] font-bold text-[#0f766e]">Products →</a>
            </div>
            @forelse($lowStock as $product)
                <div class="mt-3 flex items-center justify-between gap-3 border-b border-[#edf2ef] pb-3 last:border-0">
                    <a href="{{ route('admin.products.edit', $product) }}" class="truncate text-sm font-medium hover:text-[#0f766e]">{{ $product->name }}</a>
                    <span class="badge {{ $product->stock_quantity <= 0 ? 'badge-rose' : 'badge-amber' }}">{{ $product->stock_quantity }} left</span>
                </div>
            @empty
                <p class="mt-3 text-sm text-[#8a9892]">Everything is well stocked.</p>
            @endforelse
        </div>

        <div class="card card-pad">
            <div class="flex items-center justify-between">
                <h2 class="font-display text-xl">Subscriptions</h2>
                <a href="{{ route('admin.subscriptions.index') }}" class="text-[11px] font-bold text-[#0f766e]">Manage →</a>
            </div>
            <div class="mt-3 text-3xl font-display">{{ $activeSubscriptions->count() }}</div>
            <p class="text-xs text-[#8a9892]">Active or trialing · {{ nexus_money($mrr) }} MRR</p>
        </div>

        @if($pendingWholesale > 0)
            <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5">
                <div class="text-sm font-bold text-amber-900">Trade applications waiting</div>
                <p class="mt-1 text-xs leading-5 text-amber-800">{{ $pendingWholesale }} {{ Str::plural('business', $pendingWholesale) }} applied for wholesale pricing.</p>
                <a href="{{ route('admin.customers.index', ['wholesale_status' => 'pending']) }}" class="mt-3 inline-block text-xs font-bold text-amber-900 underline underline-offset-4">Review applications</a>
            </div>
        @endif
    </div>
</div>
@endsection
