@extends('theme.layouts.app')

@section('title', 'Order '.$order->number.' — '.nexus_setting('store.name'))

@section('content')
<div class="container-wide py-10 sm:py-14">
    <div class="mx-auto max-w-3xl">
        <div class="rounded-3xl border border-[var(--line)] bg-white p-6 sm:p-9">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <div class="text-[10px] font-bold uppercase tracking-[.18em] text-[var(--link)]">
                        {{ $order->isPaid() ? 'Payment received' : 'Order placed' }}
                    </div>
                    <h1 class="mt-2 font-display text-4xl tracking-tight">Thank you{{ $order->customer_name ? ', '.Str::before($order->customer_name, ' ') : '' }}.</h1>
                    <p class="mt-2 text-sm leading-6 text-[var(--muted)]">
                        Order <span class="font-bold text-[var(--ink)]">{{ $order->number }}</span> was placed {{ $order->created_at?->format('F j, Y \a\t g:i a') }}.
                        @if($order->isPaid())
                            A receipt is on its way to {{ $order->customer_email }}.
                        @elseif(nexus_demo())
                            This demo does not send email. The payment instructions are shown below.
                        @else
                            We have emailed the payment instructions to {{ $order->customer_email }}.
                        @endif
                    </p>
                </div>
                <span class="badge badge-{{ nexus_status_badge($order->status->value ?? $order->status) }}">{{ $order->status->label() }}</span>
            </div>

            @if(! $order->isPaid())
                <div class="mt-6 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                    <strong>Awaiting payment.</strong> {{ nexus_setting('payments.manual.instructions', 'We will email you payment instructions.') }}
                </div>
            @endif

            <div class="mt-7 overflow-hidden rounded-2xl border border-[var(--line)]">
                <table class="data-table">
                    <thead><tr><th>Item</th><th class="text-center">Qty</th><th class="text-right">Total</th></tr></thead>
                    <tbody>
                        @foreach($order->items as $item)
                            <tr>
                                <td>
                                    <div class="font-semibold">{{ $item->product_name }}</div>
                                    @if($item->variant_name)<div class="text-xs text-[var(--muted)]">{{ $item->variant_name }}</div>@endif
                                    @if($item->is_subscription)
                                        <div class="text-xs font-semibold text-[var(--link)]">Subscription · every {{ $item->subscription_interval_count }} {{ $item->subscription_interval }}(s)</div>
                                    @endif
                                </td>
                                <td class="text-center">{{ $item->quantity }}</td>
                                <td class="text-right font-semibold">{{ nexus_money($item->line_total, $order->currency) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-6 grid gap-6 sm:grid-cols-2">
                <div>
                    <div class="text-[10px] font-bold uppercase tracking-[.14em] text-[var(--muted)]">Delivery address</div>
                    <div class="mt-2 text-sm leading-6 text-[#4a5a53]">{!! nl2br(e($order->shippingAddressLine())) !!}</div>
                    @if($order->shipping_method_title)
                        <div class="mt-3 text-xs text-[var(--muted)]">Via {{ $order->shipping_method_title }}</div>
                    @endif
                </div>
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-[var(--muted)]">Subtotal</dt><dd>{{ nexus_money($order->subtotal, $order->currency) }}</dd></div>
                    @if($order->discount_total > 0)
                        <div class="flex justify-between text-emerald-700"><dt>Discount</dt><dd>−{{ nexus_money($order->discount_total, $order->currency) }}</dd></div>
                    @endif
                    <div class="flex justify-between"><dt class="text-[var(--muted)]">Shipping</dt><dd>{{ $order->shipping_total > 0 ? nexus_money($order->shipping_total, $order->currency) : 'Free' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-[var(--muted)]">Tax</dt><dd>{{ nexus_money($order->tax_total, $order->currency) }}</dd></div>
                    <div class="flex justify-between border-t border-[var(--line)] pt-2 text-base font-bold"><dt>Total</dt><dd>{{ nexus_money($order->grand_total, $order->currency) }}</dd></div>
                </dl>
            </div>

            @if($order->notes->isNotEmpty())
                <div class="mt-6 rounded-2xl bg-[#f5f8f6] p-4">
                    <div class="text-[10px] font-bold uppercase tracking-[.14em] text-[var(--muted)]">Notes</div>
                    @foreach($order->notes as $note)
                        <p class="mt-2 whitespace-pre-line text-sm leading-6 text-[#4a5a53]">{{ $note->note }}</p>
                    @endforeach
                </div>
            @endif

            <div class="mt-7 flex flex-wrap gap-3">
                <a href="{{ route('storefront.shop') }}" class="btn-primary">Continue shopping</a>
                @auth
                    <a href="{{ route('storefront.account.order', $order) }}" class="btn-secondary">View in your account</a>
                @endauth
            </div>
        </div>
    </div>
</div>
@endsection
