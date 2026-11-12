@extends('admin.layouts.app')

@section('title', 'Orders')
@section('eyebrow', 'Sell')
@section('heading', 'Orders')

@section('content')
<form method="get" class="card mb-5 grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-5">
    <div>
        <label class="label" for="q">Search</label>
        <input id="q" name="q" value="{{ request('q') }}" class="input" placeholder="Order, email, name">
    </div>
    <div>
        <label class="label" for="status">Status</label>
        <select id="status" name="status" class="select">
            <option value="">All statuses</option>
            @foreach(\App\Enums\OrderStatus::cases() as $status)
                <option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="label" for="payment_status">Payment</label>
        <select id="payment_status" name="payment_status" class="select">
            <option value="">All payments</option>
            @foreach(\App\Enums\PaymentStatus::cases() as $status)
                <option value="{{ $status->value }}" @selected(request('payment_status') === $status->value)>{{ $status->label() }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="label" for="from">From</label>
        <input id="from" type="date" name="from" value="{{ request('from') }}" class="input">
    </div>
    <div class="flex items-end gap-2">
        <div class="flex-1">
            <label class="label" for="to">To</label>
            <input id="to" type="date" name="to" value="{{ request('to') }}" class="input">
        </div>
        <button class="btn-primary !px-5">Filter</button>
    </div>
    <div class="flex flex-wrap items-center justify-between gap-3 border-t border-[#e3e9e5] pt-3 lg:col-span-5">
        <label class="flex items-center gap-2 text-xs font-semibold">
            <input type="checkbox" name="show_abandoned" value="1" class="accent-[#0f766e]" @checked(request()->boolean('show_abandoned'))>
            Show abandoned orders
        </label>
        <p class="text-[11px] text-[#8a9892]">Unpaid orders are cancelled after {{ $abandonedHours }} hours (inventory is restored automatically) and stay on this screen for {{ $retentionDays }} days, then are hidden. Paid orders are always listed.</p>
    </div>
</form>

<div class="table-wrap">
    <table class="data-table">
        <thead><tr><th>Order</th><th>Customer</th><th>Status</th><th>Payment</th><th>Fulfilment</th><th class="text-right">Total</th><th></th></tr></thead>
        <tbody>
            @forelse($orders as $order)
                <tr>
                    <td>
                        <a href="{{ route('admin.orders.show', $order) }}" class="font-semibold text-[#0f766e]">{{ $order->number }}</a>
                        <div class="text-[11px] text-[#8a9892]">{{ $order->created_at?->format('M j, Y g:i a') }}</div>
                    </td>
                    <td>
                        <div class="text-sm">{{ $order->customer_name }}</div>
                        <div class="text-[11px] text-[#8a9892]">{{ $order->customer_email }}</div>
                    </td>
                    <td><span class="badge badge-{{ nexus_status_badge($order->status->value) }}">{{ $order->status->label() }}</span></td>
                    <td><span class="badge badge-{{ nexus_status_badge($order->payment_status->value) }}">{{ $order->payment_status->label() }}</span></td>
                    <td class="text-xs capitalize text-[#687771]">{{ str_replace('_', ' ', $order->fulfillment_status->value) }}</td>
                    <td class="text-right font-semibold">{{ nexus_money($order->grand_total, $order->currency) }}</td>
                    <td class="text-right"><a href="{{ route('admin.orders.show', $order) }}" class="text-xs font-bold text-[#0f766e]">View →</a></td>
                </tr>
            @empty
                <tr><td colspan="7" class="py-10 text-center text-sm text-[#8a9892]">No orders match these filters.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="mt-5">{{ $orders->links() }}</div>
@endsection
