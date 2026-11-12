@extends('admin.layouts.app')

@section('title', 'Order '.$order->number)
@section('eyebrow', 'Orders')
@section('heading', $order->number)

@section('content')
@php
    $status = $order->status;
    $capturedPayment = $order->payments->firstWhere('status', 'succeeded');
    $refundable = max(0, round($order->paid_total - $order->refunded_total, 2));
@endphp
<div class="mb-5 flex flex-wrap items-center justify-between gap-3">
    <div class="flex flex-wrap items-center gap-2">
        <span class="badge badge-{{ nexus_status_badge($status->value) }}">{{ $status->label() }}</span>
        <span class="badge badge-{{ nexus_status_badge($order->payment_status->value) }}">{{ $order->payment_status->label() }}</span>
        <span class="badge badge-{{ nexus_status_badge($order->fulfillment_status->value) }}">{{ str_replace('_', ' ', ucfirst($order->fulfillment_status->value)) }}</span>
        <span class="text-xs text-[#8a9892]">Placed {{ $order->created_at?->format('F j, Y \a\t g:i a') }}</span>
    </div>
    <a href="{{ route('admin.orders.index') }}" class="text-xs font-bold uppercase tracking-[.1em] text-[#4d5d56] hover:text-[#0f766e]">← All orders</a>
</div>

<div class="grid gap-5 xl:grid-cols-[1.5fr_1fr]">
    <div class="space-y-5">
        <div class="table-wrap">
            <div class="border-b border-[#e3e9e5] px-5 py-4">
                <h2 class="font-display text-xl">Items</h2>
            </div>
            <table class="data-table">
                <thead><tr><th>Product</th><th>SKU</th><th class="text-center">Qty</th><th class="text-right">Unit</th><th class="text-right">Total</th></tr></thead>
                <tbody>
                    @foreach($order->items as $item)
                        <tr>
                            <td>
                                <div class="font-semibold">{{ $item->product_name }}</div>
                                @if($item->variant_name)<div class="text-[11px] text-[#8a9892]">{{ $item->variant_name }}</div>@endif
                                @if($item->is_subscription)<div class="text-[11px] font-semibold text-[#0f766e]">Subscription · {{ $item->subscription_interval_count }} {{ $item->subscription_interval }}</div>@endif
                            </td>
                            <td class="text-xs text-[#8a9892]">{{ $item->sku ?: '—' }}</td>
                            <td class="text-center">{{ $item->quantity }}</td>
                            <td class="text-right">{{ nexus_money($item->unit_price, $order->currency) }}</td>
                            <td class="text-right font-semibold">{{ nexus_money($item->line_total, $order->currency) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="grid gap-6 border-t border-[#e3e9e5] p-5 sm:grid-cols-2">
                <div>
                    <div class="text-[10px] font-bold uppercase tracking-[.14em] text-[#8a9892]">Ship to</div>
                    <div class="mt-2 text-sm leading-6 text-[#4a5a53]">{!! nl2br(e($order->shippingAddressLine())) !!}</div>
                    @if($order->shipping_method_title)<div class="mt-2 text-xs text-[#7b8a83]">{{ $order->shipping_method_title }}</div>@endif
                </div>
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-[#687771]">Subtotal</dt><dd>{{ nexus_money($order->subtotal, $order->currency) }}</dd></div>
                    @if($order->discount_total > 0)<div class="flex justify-between text-emerald-700"><dt>Discount {{ $order->coupon_code ? '('.$order->coupon_code.')' : '' }}</dt><dd>−{{ nexus_money($order->discount_total, $order->currency) }}</dd></div>@endif
                    <div class="flex justify-between"><dt class="text-[#687771]">Shipping</dt><dd>{{ nexus_money($order->shipping_total, $order->currency) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-[#687771]">Tax</dt><dd>{{ nexus_money($order->tax_total, $order->currency) }}</dd></div>
                    <div class="flex justify-between border-t border-[#e3e9e5] pt-2 text-base font-bold"><dt>Total</dt><dd>{{ nexus_money($order->grand_total, $order->currency) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-[#687771]">Paid</dt><dd>{{ nexus_money($order->paid_total, $order->currency) }}</dd></div>
                    @if($order->refunded_total > 0)<div class="flex justify-between text-rose-600"><dt>Refunded</dt><dd>−{{ nexus_money($order->refunded_total, $order->currency) }}</dd></div>@endif
                </dl>
            </div>
        </div>

        <div class="card card-pad">
            <h2 class="font-display text-xl">Notes</h2>
            <form method="post" action="{{ route('admin.orders.note', $order) }}" class="mt-4 space-y-3">
                @csrf
                <textarea name="note" rows="3" class="textarea" placeholder="Internal note or customer update…" required></textarea>
                <label class="flex items-center gap-2 text-xs text-[#5f6f67]"><input type="checkbox" name="is_customer_visible" value="1" class="accent-[#0f766e]"> Show to customer in their account</label>
                <button class="btn-secondary !px-4 !py-2 text-xs">Add note</button>
            </form>
            <div class="mt-4 space-y-3">
                @foreach($order->notes as $note)
                    <div class="rounded-xl bg-[#f5f8f6] p-3">
                        <div class="flex justify-between gap-2 text-[11px] text-[#8a9892]">
                            <span>{{ $note->user?->name ?? 'System' }}{{ $note->is_customer_visible ? ' · visible to customer' : ' · internal' }}</span>
                            <span>{{ $note->created_at?->format('M j, g:i a') }}</span>
                        </div>
                        <p class="mt-1 whitespace-pre-line text-sm leading-6 text-[#4a5a53]">{{ $note->note }}</p>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="table-wrap">
            <div class="border-b border-[#e3e9e5] px-5 py-4"><h2 class="font-display text-xl">Timeline</h2></div>
            <table class="data-table">
                <thead><tr><th>When</th><th>Status</th><th>By</th><th>Note</th></tr></thead>
                <tbody>
                    @forelse($order->statusHistory->sortByDesc('created_at') as $history)
                        <tr>
                            <td class="text-xs">{{ $history->created_at?->format('M j, Y g:i a') }}</td>
                            <td><span class="badge badge-{{ nexus_status_badge($history->to_status) }}">{{ str_replace('_', ' ', ucfirst($history->to_status)) }}</span></td>
                            <td class="text-xs">{{ $history->user?->name ?? 'System' }}</td>
                            <td class="text-xs text-[#687771]">{{ $history->note }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="py-6 text-center text-sm text-[#8a9892]">No status history yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="space-y-5">
        <div class="card card-pad">
            <h2 class="font-display text-xl">Update order</h2>
            <form method="post" action="{{ route('admin.orders.status', $order) }}" class="mt-4 space-y-3">
                @csrf
                @method('PATCH')
                <div>
                    <label class="label" for="status">Order status</label>
                    <select id="status" name="status" class="select">
                        @foreach(\App\Enums\OrderStatus::cases() as $option)
                            <option value="{{ $option->value }}" @selected($status === $option)>{{ $option->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="label" for="status_note">Note (optional)</label>
                    <input id="status_note" name="note" class="input" placeholder="Reason or internal note">
                </div>
                <button class="btn-primary w-full !py-2.5 text-xs">Save status</button>
            </form>

            @if(! $order->isPaid())
                <form method="post" action="{{ route('admin.orders.paid', $order) }}" class="mt-3">
                    @csrf
                    <button class="btn-secondary w-full !py-2.5 text-xs">Mark as paid manually</button>
                </form>
            @endif
            @if($order->canBeCancelled())
                <form method="post" action="{{ route('admin.orders.cancel', $order) }}" class="mt-3" onsubmit="return confirm('Cancel this order and restore inventory?')">
                    @csrf
                    <input name="reason" class="input mb-2" placeholder="Cancellation reason">
                    <button class="btn-danger w-full !py-2.5 text-xs">Cancel order</button>
                </form>
            @endif
        </div>

        <div class="card card-pad">
            <h2 class="font-display text-xl">Customer</h2>
            <div class="mt-3 text-sm font-semibold">{{ $order->customer_name }}</div>
            <div class="text-xs text-[#687771]"><a href="mailto:{{ $order->customer_email }}" class="hover:text-[#0f766e]">{{ $order->customer_email }}</a></div>
            @if($order->customer_phone)<div class="text-xs text-[#687771]">{{ $order->customer_phone }}</div>@endif
            @if($order->user)
                <a href="{{ route('admin.customers.show', $order->user) }}" class="mt-3 inline-block text-xs font-bold text-[#0f766e]">View customer profile →</a>
            @endif
            @if($order->customer_note)
                <div class="mt-4 rounded-xl bg-[#f5f8f6] p-3 text-xs leading-5 text-[#5f6f67]"><strong>Customer note:</strong> {{ $order->customer_note }}</div>
            @endif
            <dl class="mt-4 space-y-2 border-t border-[#e3e9e5] pt-3 text-xs">
                <div class="flex justify-between"><dt class="text-[#8a9892]">Payment method</dt><dd class="font-semibold capitalize">{{ $order->payment_method ?: '—' }}</dd></div>
                <div class="flex justify-between"><dt class="text-[#8a9892]">IP address</dt><dd>{{ $order->ip_address ?: '—' }}</dd></div>
                <div class="flex justify-between"><dt class="text-[#8a9892]">Mode</dt><dd class="capitalize">{{ $order->mode?->value ?? '—' }}</dd></div>
            </dl>
        </div>

        @if($order->payments->isNotEmpty())
            <div class="card card-pad">
                <h2 class="font-display text-xl">Payments</h2>
                <div class="mt-3 space-y-2">
                    @foreach($order->payments as $payment)
                        <div class="rounded-xl border border-[#e3e9e5] px-3 py-2 text-xs">
                            <div class="flex justify-between font-semibold"><span class="capitalize">{{ $payment->gateway }}</span><span>{{ nexus_money($payment->amount, $payment->currency) }}</span></div>
                            <div class="mt-0.5 text-[#8a9892]">{{ $payment->status }} · {{ $payment->created_at?->format('M j, Y g:i a') }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        @if($order->refunds->isNotEmpty())
            <div class="card card-pad">
                <h2 class="font-display text-xl">Refunds</h2>
                <div class="mt-2 divide-y divide-[#e3e9e5]">
                    @foreach($order->refunds->sortByDesc('created_at') as $refund)
                        <div class="py-3 text-xs">
                            <div class="flex items-center justify-between gap-2">
                                <span class="font-semibold">{{ nexus_money($refund->amount, $order->currency) }}</span>
                                <span class="badge badge-{{ $refund->status === 'completed' ? 'emerald' : ($refund->status === 'failed' ? 'rose' : 'amber') }}">{{ ucfirst($refund->status) }}</span>
                            </div>
                            <div class="mt-1 text-[#8a9892]">
                                {{ $refund->created_at?->format('M j, Y g:i a') }}
                                · {{ $refund->gateway_refund_id ?: 'No provider reference' }}
                                · {{ $refund->user?->name ?? 'System' }}
                            </div>
                            @if($refund->reason)
                                <p class="mt-1 text-[#687771]">{{ $refund->reason }}</p>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        @if($order->isPaid() && $refundable > 0)
            <div class="card card-pad">
                <div class="flex items-start justify-between gap-2">
                    <h2 class="font-display text-xl">Refund</h2>
                    <span class="text-[11px] font-semibold text-[#8a9892]">{{ nexus_money($refundable, $order->currency) }} refundable</span>
                </div>
                <form method="post" action="{{ route('admin.orders.refund', $order) }}" class="mt-3 space-y-3">
                    @csrf
                    <input type="hidden" name="refund_token" value="{{ \Illuminate\Support\Str::uuid() }}">
                    <div>
                        <label class="label" for="amount">Amount</label>
                        <input id="amount" type="number" step="0.01" min="0.01" max="{{ $refundable }}" name="amount" value="{{ number_format($refundable, 2, '.', '') }}" class="input" required>
                    </div>
                    <div>
                        <label class="label" for="reason">Reason</label>
                        <input id="reason" name="reason" class="input" placeholder="Return, damaged, goodwill…">
                    </div>
                    @if($capturedPayment?->gateway === 'manual')
                        <p class="text-[11px] leading-5 text-[#8a9892]">Manual refund: ShopKeeprr records it here. Send the money through your bank or payment provider separately.</p>
                        <button class="btn-secondary w-full !py-2.5 text-xs" onclick="return confirm('Record this manual refund?')">Record manual refund</button>
                    @else
                        <button class="btn-secondary w-full !py-2.5 text-xs" onclick="return confirm('Send this refund to the payment provider?')">Refund payment</button>
                    @endif
                </form>
            </div>
        @endif

        <div class="card card-pad text-xs leading-5 text-[#687771]">
            <div class="text-[10px] font-bold uppercase tracking-[.14em] text-[#8a9892]">Access link</div>
            <a href="{{ $order->confirmationUrl() }}" target="_blank" class="mt-2 block break-all font-semibold text-[#0f766e]">Open customer confirmation →</a>
        </div>
    </div>
</div>
@endsection
