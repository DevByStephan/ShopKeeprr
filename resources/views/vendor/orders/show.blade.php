@extends('vendor.layouts.app')

@section('title', 'Order '.$split->order?->number)
@section('eyebrow', 'Sales')
@section('heading', 'Order '.$split->order?->number)

@section('content')
<div class="mb-5 flex flex-wrap items-center gap-2">
    <span class="badge {{ $split->status === 'paid' ? 'badge-emerald' : ($split->status === 'reversed' ? 'badge-rose' : 'badge-amber') }}">{{ ucfirst($split->status) }}</span>
    <span class="badge badge-slate">Store status: {{ $split->order?->status?->value ?? '—' }}</span>
    <a href="{{ route('vendor.orders.index') }}" class="ml-auto text-[11px] font-bold text-[#0f766e]">← All orders</a>
</div>

<div class="grid gap-5 xl:grid-cols-[1.4fr_.8fr]">
    <div class="space-y-5">
        <div class="card card-pad">
            <h2 class="font-display text-xl">Your items</h2>
            <div class="mt-4 overflow-x-auto">
                <table class="data-table">
                    <thead><tr><th>Item</th><th>Qty</th><th>Unit</th><th>Discount</th><th>Commission</th><th>You earn</th></tr></thead>
                    <tbody>
                        @foreach($split->order?->items ?? [] as $item)
                            <tr>
                                <td>
                                    <div class="font-semibold">{{ $item->product_name }}</div>
                                    @if($item->variant_name)<div class="text-[11px] text-[#8a9892]">{{ $item->variant_name }}</div>@endif
                                </td>
                                <td class="text-xs">{{ $item->quantity }}</td>
                                <td class="text-xs">{{ nexus_money($item->unit_price) }}</td>
                                <td class="text-xs">{{ $item->discount_amount > 0 ? nexus_money($item->discount_amount) : '—' }}</td>
                                <td class="text-xs">{{ $item->commission_amount > 0 ? nexus_money($item->commission_amount) : '—' }}</td>
                                <td class="text-sm font-semibold">{{ nexus_money($item->vendor_total) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-4 ml-auto max-w-xs space-y-1.5 text-sm">
                <div class="flex justify-between"><span class="text-[#687771]">Items subtotal</span><span>{{ nexus_money($split->subtotal) }}</span></div>
                <div class="flex justify-between"><span class="text-[#687771]">Discount</span><span>-{{ nexus_money($split->discount_total) }}</span></div>
                <div class="flex justify-between"><span class="text-[#687771]">Commission</span><span>-{{ nexus_money($split->commission_total) }}</span></div>
                <div class="flex justify-between border-t border-[#e3e9e5] pt-1.5 font-bold"><span>Seller payout</span><span>{{ nexus_money($split->payout_total) }}</span></div>
                @if($split->refunded_total > 0)
                    <div class="flex justify-between text-rose-600"><span>Refunded to customer</span><span>-{{ nexus_money($split->refunded_total) }}</span></div>
                    <div class="flex justify-between font-bold text-rose-700"><span>Net</span><span>{{ nexus_money($split->netPayout()) }}</span></div>
                @endif
            </div>
        </div>

        <div class="card card-pad">
            <h2 class="font-display text-xl">Fulfilment</h2>
            <p class="mt-1 text-xs text-[#687771]">The store handles the customer relationship and refunds. Contact the store owner if an item needs special handling.</p>
            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <div>
                    <div class="text-[10px] font-bold uppercase tracking-[.12em] text-[#8a9892]">Ship to</div>
                    <div class="mt-1 text-sm leading-6">{{ $split->order?->customer_name }}<br>{!! nl2br(e($split->order?->shippingAddressLine())) !!}</div>
                </div>
                <div>
                    <div class="text-[10px] font-bold uppercase tracking-[.12em] text-[#8a9892]">Customer</div>
                    <div class="mt-1 text-sm leading-6">{{ $split->order?->customer_email }}<br>{{ $split->order?->customer_phone }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="space-y-5">
        <div class="card card-pad">
            <h2 class="font-display text-lg">Payout</h2>
            <dl class="mt-3 space-y-3 text-sm">
                <div><dt class="text-[10px] font-bold uppercase tracking-[.12em] text-[#8a9892]">Method</dt><dd>{{ $vendor->payoutMethodLabel() }}</dd></div>
                <div><dt class="text-[10px] font-bold uppercase tracking-[.12em] text-[#8a9892]">Status</dt><dd>{{ ucfirst($split->status) }}</dd></div>
                <div><dt class="text-[10px] font-bold uppercase tracking-[.12em] text-[#8a9892]">Release date</dt><dd>{{ $split->available_at?->format('M j, Y') ?? 'When the order is paid' }}</dd></div>
                @if($split->paid_at)<div><dt class="text-[10px] font-bold uppercase tracking-[.12em] text-[#8a9892]">Paid</dt><dd>{{ $split->paid_at->format('M j, Y') }}</dd></div>@endif
                @if($split->stripe_transfer_id)<div><dt class="text-[10px] font-bold uppercase tracking-[.12em] text-[#8a9892]">Stripe transfer</dt><dd class="break-all font-mono text-[11px]">{{ $split->stripe_transfer_id }}</dd></div>@endif
                @if($split->failure_reason)<div><dt class="text-[10px] font-bold uppercase tracking-[.12em] text-[#8a9892]">Note</dt><dd class="text-xs text-amber-700">{{ $split->failure_reason }}</dd></div>@endif
            </dl>
        </div>
    </div>
</div>
@endsection
