@extends('vendor.layouts.app')

@section('title', 'Orders')
@section('eyebrow', 'Sales')
@section('heading', 'Orders')

@section('content')
<form method="get" class="mb-5 flex items-end gap-3">
    <div>
        <label class="label" for="status">Status</label>
        <select id="status" name="status" class="select !w-44" onchange="this.form.submit()">
            <option value="">All</option>
            @foreach(['pending', 'held', 'paid', 'reversed'] as $option)
                <option value="{{ $option }}" @selected(request('status') === $option)>{{ ucfirst($option) }}</option>
            @endforeach
        </select>
    </div>
</form>

<div class="table-wrap">
    <table class="data-table">
        <thead><tr><th>Order</th><th>Customer</th><th>Date</th><th>Order status</th><th>Your total</th><th>Payout</th><th></th></tr></thead>
        <tbody>
            @forelse($splits as $split)
                <tr>
                    <td class="font-semibold">{{ $split->order?->number ?? '#'.$split->order_id }}</td>
                    <td class="text-xs">{{ $split->order?->customer_name }}</td>
                    <td class="text-xs">{{ $split->created_at?->format('M j, Y') }}</td>
                    <td><span class="badge badge-slate">{{ $split->order?->status?->value ?? '—' }}</span></td>
                    <td class="text-sm font-semibold">{{ nexus_money($split->payout_total) }}</td>
                    <td><span class="badge {{ $split->status === 'paid' ? 'badge-emerald' : ($split->status === 'reversed' ? 'badge-rose' : 'badge-amber') }}">{{ ucfirst($split->status) }}</span></td>
                    <td class="text-right"><a href="{{ route('vendor.orders.show', $split) }}" class="text-[11px] font-bold text-[#0f766e]">View</a></td>
                </tr>
            @empty
                <tr><td colspan="7" class="py-10 text-center text-sm text-[#8a9892]">No orders yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="mt-5">{{ $splits->links() }}</div>
@endsection
