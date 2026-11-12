@extends('theme.layouts.app')

@section('title', 'Order '.$order->number.' — '.nexus_setting('store.name'))

@section('content')
<div class="container-wide py-10 sm:py-14">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <div class="text-[10px] font-bold uppercase tracking-[.18em] text-[var(--link)]">Order details</div>
            <h1 class="mt-2 font-display text-4xl tracking-tight">{{ $order->number }}</h1>
            <p class="mt-1 text-sm text-[var(--muted)]">Placed {{ $order->created_at?->format('F j, Y \a\t g:i a') }}</p>
        </div>
        <div class="flex items-center gap-3">
            <span class="badge badge-{{ nexus_status_badge($order->status->value) }}">{{ $order->status->label() }}</span>
            <a href="{{ route('storefront.account.orders') }}" class="text-xs font-bold uppercase tracking-[.1em] text-[var(--muted)] hover:text-[var(--link)]">← All orders</a>
        </div>
    </div>

    <div class="mt-8 grid gap-6 lg:grid-cols-[1.3fr_.7fr]">
        <div class="space-y-6">
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th>Item</th><th class="text-center">Qty</th><th class="text-right">Total</th></tr></thead>
                    <tbody>
                        @foreach($order->items as $item)
                            <tr>
                                <td>
                                    <div class="font-semibold">{{ $item->product_name }}</div>
                                    @if($item->variant_name)<div class="text-xs text-[var(--muted)]">{{ $item->variant_name }}</div>@endif
                                    @if($item->is_subscription)<div class="text-xs font-semibold text-[var(--link)]">Subscription</div>@endif
                                </td>
                                <td class="text-center">{{ $item->quantity }}</td>
                                <td class="text-right font-semibold">{{ nexus_money($item->line_total, $order->currency) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($order->notes->isNotEmpty())
                <div class="card card-pad">
                    <h2 class="text-sm font-bold">Updates from the store</h2>
                    @foreach($order->notes as $note)
                        <div class="mt-3 rounded-xl bg-[#f5f8f6] p-3">
                            <div class="text-[11px] font-semibold text-[var(--muted)]">{{ $note->created_at?->format('M j, Y g:i a') }}</div>
                            <p class="mt-1 whitespace-pre-line text-sm leading-6 text-[#4a5a53]">{{ $note->note }}</p>
                        </div>
                    @endforeach
                </div>
            @endif

            @if($order->payments->isNotEmpty())
                <div class="card card-pad">
                    <h2 class="text-sm font-bold">Payments</h2>
                    <div class="mt-3 space-y-2">
                        @foreach($order->payments as $payment)
                            <div class="flex items-center justify-between rounded-xl border border-[var(--line)] px-3 py-2 text-xs">
                                <span class="font-semibold">{{ ucfirst($payment->gateway) }} · {{ $payment->status }}</span>
                                <span>{{ nexus_money($payment->amount, $payment->currency) }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        <aside class="space-y-4">
            <div class="card card-pad">
                <h2 class="font-display text-xl">Summary</h2>
                <dl class="mt-3 space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-[var(--muted)]">Subtotal</dt><dd>{{ nexus_money($order->subtotal, $order->currency) }}</dd></div>
                    @if($order->discount_total > 0)<div class="flex justify-between text-emerald-700"><dt>Discount</dt><dd>−{{ nexus_money($order->discount_total, $order->currency) }}</dd></div>@endif
                    <div class="flex justify-between"><dt class="text-[var(--muted)]">Shipping</dt><dd>{{ $order->shipping_total > 0 ? nexus_money($order->shipping_total, $order->currency) : 'Free' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-[var(--muted)]">Tax</dt><dd>{{ nexus_money($order->tax_total, $order->currency) }}</dd></div>
                    <div class="flex justify-between border-t border-[var(--line)] pt-2 text-base font-bold"><dt>Total</dt><dd>{{ nexus_money($order->grand_total, $order->currency) }}</dd></div>
                    @if($order->refunded_total > 0)<div class="flex justify-between text-[var(--muted)]"><dt>Refunded</dt><dd>−{{ nexus_money($order->refunded_total, $order->currency) }}</dd></div>@endif
                </dl>
            </div>
            <div class="card card-pad">
                <div class="text-[10px] font-bold uppercase tracking-[.14em] text-[var(--muted)]">Delivery address</div>
                <div class="mt-2 text-sm leading-6 text-[#4a5a53]">{!! nl2br(e($order->shippingAddressLine())) !!}</div>
                @if($order->shipping_method_title)
                    <div class="mt-3 border-t border-[var(--line)] pt-3 text-xs text-[var(--muted)]">{{ $order->shipping_method_title }}</div>
                @endif
            </div>
        </aside>
    </div>
</div>
@endsection
