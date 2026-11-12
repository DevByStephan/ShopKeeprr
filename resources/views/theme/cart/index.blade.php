@extends('theme.layouts.app')

@section('title', 'Your bag — '.nexus_setting('store.name'))

@section('content')
<div class="container-wide py-10 sm:py-14">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <div class="text-[10px] font-bold uppercase tracking-[.18em] text-[var(--link)]">Step 1 of 2</div>
            <h1 class="mt-2 font-display text-4xl tracking-tight">Your bag</h1>
        </div>
        <a href="{{ route('storefront.shop') }}" class="text-xs font-bold uppercase tracking-[.1em] text-[var(--muted)] hover:text-[var(--link)]">← Continue shopping</a>
    </div>

    @if($cart->isEmpty())
        <div class="mt-8 rounded-3xl border border-dashed border-[var(--line)] bg-white p-12 text-center">
            <div class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-[var(--surface-2)] text-[var(--muted)]">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path d="M6 8h12l-1 13H7L6 8Z"/><path d="M9 9V6a3 3 0 0 1 6 0v3"/></svg>
            </div>
            <h2 class="mt-5 font-display text-2xl">Your bag is empty</h2>
            <p class="mt-2 text-sm text-[var(--muted)]">Once you add something it will show up here.</p>
            <a href="{{ route('storefront.shop') }}" class="btn-primary mt-6">Browse the collection</a>
        </div>
    @else
        <div class="mt-8 grid gap-8 lg:grid-cols-[1.4fr_.6fr]">
            <div class="space-y-3">
                @foreach($cart->items as $item)
                    @php($product = $item->product)
                    <div class="flex gap-4 rounded-2xl border border-[var(--line)] bg-white p-4">
                        <a href="{{ $product ? route('storefront.product', $product->slug) : '#' }}" class="h-24 w-20 shrink-0 overflow-hidden rounded-xl bg-[var(--surface-2)]">
                            @if($product?->imageUrl())
                                <img src="{{ $product->imageUrl() }}" alt="{{ $product->name }}" class="h-full w-full object-cover">
                            @else
                                <img src="{{ asset('images/placeholder.svg') }}" alt="" class="h-full w-full object-cover">
                            @endif
                        </a>
                        <div class="flex min-w-0 flex-1 flex-col justify-between gap-3 sm:flex-row sm:items-center">
                            <div class="min-w-0">
                                <a href="{{ $product ? route('storefront.product', $product->slug) : '#' }}" class="block truncate text-sm font-semibold hover:text-[var(--link)]">{{ $product?->name ?? 'Product unavailable' }}</a>
                                @if($item->variant)
                                    <div class="mt-0.5 text-xs text-[var(--muted)]">{{ $item->variant->displayName() }}</div>
                                @endif
                                @if($item->plan)
                                    <div class="mt-0.5 text-xs font-semibold text-[var(--link)]">Subscription · every {{ $item->plan->billingLabel() }}</div>
                                @endif
                                <div class="mt-1 text-xs text-[var(--muted)]">{{ nexus_money($item->unit_price) }} each</div>
                            </div>
                            <div class="flex items-center gap-4">
                                <form method="post" action="{{ route('storefront.cart.update', $item) }}" class="flex items-center rounded-full border border-[var(--line)] bg-white">
                                    @csrf
                                    @method('PATCH')
                                    <input type="number" name="quantity" value="{{ $item->quantity }}" min="0" max="999" class="w-14 border-0 bg-transparent py-1.5 pl-3 text-center text-sm outline-none" onchange="this.form.submit()">
                                </form>
                                <div class="w-20 text-right text-sm font-semibold">{{ nexus_money($item->lineTotal()) }}</div>
                                <form method="post" action="{{ route('storefront.cart.remove', $item) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button class="rounded-full p-1.5 text-[var(--muted)] transition hover:bg-rose-50 hover:text-rose-600" aria-label="Remove">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M4 7h16M9 7V5h6v2M7 7l1 13h8l1-13"/></svg>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <aside class="h-fit rounded-2xl border border-[var(--line)] bg-white p-5 sm:p-6">
                <h2 class="font-display text-xl">Order summary</h2>
                <dl class="mt-4 space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-[var(--muted)]">Subtotal</dt><dd class="font-medium">{{ nexus_money($totals['subtotal']) }}</dd></div>
                    @if($totals['discount'] > 0)
                        <div class="flex justify-between text-emerald-700"><dt>Discount {{ $cart->coupon?->code ? '('.$cart->coupon->code.')' : '' }}</dt><dd>−{{ nexus_money($totals['discount']) }}</dd></div>
                    @endif
                    <div class="flex justify-between"><dt class="text-[var(--muted)]">Shipping</dt><dd>{{ $totals['shipping'] > 0 ? nexus_money($totals['shipping']) : 'Free' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-[var(--muted)]">Tax</dt><dd>{{ nexus_money($totals['tax']) }}</dd></div>
                    <div class="mt-3 flex justify-between border-t border-[var(--line)] pt-3 text-base font-bold"><dt>Total</dt><dd>{{ nexus_money($totals['total']) }}</dd></div>
                </dl>

                <div class="mt-5 border-t border-[var(--line)] pt-4">
                    @if($cart->coupon)
                        <div class="flex items-center justify-between rounded-xl bg-[var(--surface-2)] px-3 py-2 text-xs">
                            <span class="font-semibold text-[var(--link)]">{{ $cart->coupon->code }} applied</span>
                            <form method="post" action="{{ route('storefront.cart.coupon.remove') }}">@csrf @method('DELETE')<button class="font-semibold text-rose-600">Remove</button></form>
                        </div>
                    @else
                        <form method="post" action="{{ route('storefront.cart.coupon') }}" class="flex gap-2">
                            @csrf
                            <input name="coupon" class="input" placeholder="Discount code">
                            <button class="btn-secondary whitespace-nowrap !px-4">Apply</button>
                        </form>
                    @endif
                </div>

                <a href="{{ route('storefront.checkout') }}" class="btn-primary mt-5 w-full">Continue to checkout</a>
                <p class="mt-3 text-center text-[11px] text-[var(--muted)]">Taxes and shipping are calculated at checkout.</p>
            </aside>
        </div>
    @endif
</div>
@endsection
