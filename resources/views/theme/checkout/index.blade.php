@extends('theme.layouts.app')

@section('title', 'Checkout — '.nexus_setting('store.name'))

@section('content')
@php
    $config = [
        'country' => old('country', $address['country'] ?? nexus_setting('store.country', 'US')),
        'state' => old('state', $address['state'] ?? ''),
        'shippingMethodId' => $totals['shipping_method']['id'] ?? null,
        'options' => $totals['options'],
        'totals' => collect($totals)->only(['subtotal', 'discount', 'shipping', 'tax', 'total'])->all(),
        'shippingUrl' => route('storefront.checkout.shipping'),
        'csrf' => csrf_token(),
    ];
@endphp

<div class="container-wide py-10 sm:py-14">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <div class="text-[10px] font-bold uppercase tracking-[.18em] text-[var(--link)]">Step 2 of 2</div>
            <h1 class="mt-2 font-display text-4xl tracking-tight">Checkout</h1>
        </div>
        <a href="{{ route('storefront.cart') }}" class="text-xs font-bold uppercase tracking-[.1em] text-[var(--muted)] hover:text-[var(--link)]">← Back to bag</a>
    </div>

    <form method="post" action="{{ route('storefront.checkout.place') }}" class="mt-8 grid gap-8 lg:grid-cols-[1.4fr_.6fr]" x-data='checkoutForm(@json($config))'>
        @csrf

        <div class="space-y-6">
            <section class="rounded-2xl border border-[var(--line)] bg-white p-5 sm:p-6">
                <h2 class="font-display text-xl">Contact</h2>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label class="label" for="customer_email">Email address</label>
                        <input id="customer_email" type="email" name="customer_email" value="{{ old('customer_email', auth()->user()?->email) }}" class="input" required autocomplete="email">
                    </div>
                    <div>
                        <label class="label" for="customer_name">Full name</label>
                        <input id="customer_name" name="customer_name" value="{{ old('customer_name', auth()->user()?->name) }}" class="input" required autocomplete="name">
                    </div>
                    <div>
                        <label class="label" for="customer_phone">Phone {{ nexus_setting('checkout.require_phone') ? '' : '(optional)' }}</label>
                        <input id="customer_phone" name="customer_phone" value="{{ old('customer_phone', auth()->user()?->phone) }}" class="input" autocomplete="tel" @required(nexus_setting('checkout.require_phone'))>
                    </div>
                </div>
            </section>

            <section class="rounded-2xl border border-[var(--line)] bg-white p-5 sm:p-6">
                <h2 class="font-display text-xl">Shipping address</h2>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label class="label" for="line1">Address</label>
                        <input id="line1" name="line1" value="{{ old('line1', $address['line1'] ?? '') }}" class="input" required autocomplete="address-line1">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="label" for="line2">Apartment, suite, etc. (optional)</label>
                        <input id="line2" name="line2" value="{{ old('line2', $address['line2'] ?? '') }}" class="input" autocomplete="address-line2">
                    </div>
                    <div>
                        <label class="label" for="city">City</label>
                        <input id="city" name="city" value="{{ old('city', $address['city'] ?? '') }}" class="input" required autocomplete="address-level2">
                    </div>
                    <div>
                        <label class="label" for="state">State / region</label>
                        <input id="state" name="state" x-model="state" value="{{ old('state', $address['state'] ?? '') }}" class="input" autocomplete="address-level1">
                    </div>
                    <div>
                        <label class="label" for="postcode">Postcode</label>
                        <input id="postcode" name="postcode" value="{{ old('postcode', $address['postcode'] ?? '') }}" class="input" required autocomplete="postal-code">
                    </div>
                    <div>
                        <label class="label" for="country">Country</label>
                        <select id="country" name="country" x-model="country" @change="refreshShipping()" class="select">
                            @foreach(['US' => 'United States', 'CA' => 'Canada', 'GB' => 'United Kingdom', 'AU' => 'Australia', 'DE' => 'Germany', 'FR' => 'France', 'NL' => 'Netherlands', 'IE' => 'Ireland', 'NZ' => 'New Zealand'] as $code => $name)
                                <option value="{{ $code }}" @selected(old('country', $address['country'] ?? 'US') === $code)>{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </section>

            <section class="rounded-2xl border border-[var(--line)] bg-white p-5 sm:p-6">
                <div class="flex items-center justify-between">
                    <h2 class="font-display text-xl">Shipping method</h2>
                    <span x-show="loadingShipping" x-cloak class="text-[11px] font-semibold text-[var(--muted)]">Updating…</span>
                </div>
                <div class="mt-4 grid gap-2">
                    <template x-for="option in options" :key="option.id ?? 'free'">
                        <label class="flex cursor-pointer items-center justify-between rounded-xl border border-[var(--line)] px-4 py-3 text-sm" :class="Number(shippingMethodId) === Number(option.id) ? '!border-[var(--brand)] ring-2 ring-[var(--brand)]/15' : ''">
                            <span class="flex items-center gap-3">
                                <input type="radio" name="shipping_method_id" :value="option.id" x-model.number="shippingMethodId" class="accent-[var(--brand)]">
                                <span>
                                    <span class="block font-semibold" x-text="option.name"></span>
                                    <span class="text-xs text-[var(--muted)]" x-text="option.zone || ''"></span>
                                </span>
                            </span>
                            <span class="font-semibold" x-text="Number(option.cost) > 0 ? '{{ nexus_setting('store.currency_symbol', '$') }}' + Number(option.cost).toFixed(2) : 'Free'"></span>
                        </label>
                    </template>
                    <p x-show="! options.length" class="rounded-xl bg-[#f5f8f6] px-4 py-3 text-sm text-[var(--muted)]">Enter your address to see delivery options.</p>
                </div>
            </section>

            <section class="rounded-2xl border border-[var(--line)] bg-white p-5 sm:p-6">
                <h2 class="font-display text-xl">Payment</h2>
                <div class="mt-4 grid gap-2">
                    @foreach($gateways as $code => $label)
                        <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-[var(--line)] px-4 py-3 text-sm">
                            <input type="radio" name="payment_method" value="{{ $code }}" class="accent-[var(--brand)]" @checked(old('payment_method', array_key_first($gateways)) === $code) required>
                            <span class="font-semibold">{{ $label }}</span>
                            @if($code === 'manual')
                                <span class="ml-auto text-[11px] text-[var(--muted)]">Invoice</span>
                            @endif
                        </label>
                    @endforeach
                </div>
                @if(nexus_mode()?->value === 'subscription')
                    <p class="mt-3 text-xs text-[var(--muted)]">Your subscription renews automatically until cancelled. You can manage it from your account.</p>
                @endif
                <div class="mt-4">
                    <label class="label" for="customer_note">Order note (optional)</label>
                    <textarea id="customer_note" name="customer_note" class="textarea" rows="3" placeholder="Delivery instructions, gift note, purchase order number…">{{ old('customer_note') }}</textarea>
                </div>
                @if(! empty($emailMarketingHubEnabled))
                    <label class="mt-3 flex items-start gap-2.5 text-xs leading-5 text-[var(--muted)]">
                        <input type="checkbox" name="marketing_opt_in" value="1" class="mt-0.5 accent-[var(--brand)]" @checked(old('marketing_opt_in'))>
                        <span>Email me occasional news and offers. This is optional and separate from my order updates.</span>
                    </label>
                @endif
                <label class="mt-4 flex items-start gap-2.5 text-xs leading-5 text-[var(--muted)]">
                    <input type="checkbox" name="terms" value="1" class="mt-0.5 accent-[var(--brand)]" required @checked(old('terms'))>
                    <span>I agree to the <a href="{{ route('storefront.page', 'terms') }}" class="font-semibold text-[var(--link)] underline underline-offset-2">terms of service</a> and <a href="{{ route('storefront.page', 'privacy') }}" class="font-semibold text-[var(--link)] underline underline-offset-2">privacy policy</a>.</span>
                </label>
            </section>
        </div>

        <aside class="h-fit space-y-4 lg:sticky lg:top-24">
            <div class="rounded-2xl border border-[var(--line)] bg-white p-5">
                <h2 class="font-display text-xl">Your order</h2>
                <div class="mt-4 space-y-3 border-b border-[var(--line)] pb-4">
                    @foreach($cart->items as $item)
                        <div class="flex gap-3">
                            <div class="h-14 w-12 shrink-0 overflow-hidden rounded-lg bg-[var(--surface-2)]">
                                @if($item->product?->imageUrl())
                                    <img src="{{ $item->product->imageUrl() }}" alt="" class="h-full w-full object-cover">
                                @else
                                    <img src="{{ asset('images/placeholder.svg') }}" alt="" class="h-full w-full object-cover">
                                @endif
                            </div>
                            <div class="min-w-0 flex-1 text-xs">
                                <div class="truncate font-semibold">{{ $item->product?->name }}</div>
                                <div class="text-[var(--muted)]">{{ $item->variant?->displayName() ?: $item->plan?->name }} · Qty {{ $item->quantity }}</div>
                            </div>
                            <div class="text-xs font-semibold">{{ nexus_money($item->lineTotal()) }}</div>
                        </div>
                    @endforeach
                </div>
                <dl class="mt-4 space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-[var(--muted)]">Subtotal</dt><dd x-text="'{{ nexus_setting('store.currency_symbol', '$') }}' + Number(totals.subtotal || 0).toFixed(2)"></dd></div>
                    <div class="flex justify-between" x-show="Number(totals.discount || 0) > 0"><dt class="text-[var(--muted)]">Discount</dt><dd class="text-emerald-700" x-text="'−{{ nexus_setting('store.currency_symbol', '$') }}' + Number(totals.discount || 0).toFixed(2)"></dd></div>
                    <div class="flex justify-between"><dt class="text-[var(--muted)]">Shipping</dt><dd x-text="Number(totals.shipping || 0) > 0 ? '{{ nexus_setting('store.currency_symbol', '$') }}' + Number(totals.shipping).toFixed(2) : 'Free'"></dd></div>
                    <div class="flex justify-between"><dt class="text-[var(--muted)]">Tax</dt><dd x-text="'{{ nexus_setting('store.currency_symbol', '$') }}' + Number(totals.tax || 0).toFixed(2)"></dd></div>
                    <div class="flex justify-between border-t border-[var(--line)] pt-3 text-base font-bold"><dt>Total</dt><dd x-text="'{{ nexus_setting('store.currency_symbol', '$') }}' + Number(totals.total || 0).toFixed(2)"></dd></div>
                </dl>
                <button class="btn-primary mt-5 w-full">Place order</button>
                <p class="mt-3 text-center text-[11px] leading-5 text-[var(--muted)]">
                    @if(nexus_demo())
                        This demo does not send email. Your order appears in the admin immediately.
                    @else
                        You will receive an email confirmation as soon as the order is placed.
                    @endif
                </p>
            </div>
            <div class="rounded-2xl border border-[var(--line)] bg-[var(--surface-2)] p-4 text-xs leading-5 text-[var(--muted)]">
                @if(nexus_demo())
                    <strong class="text-[var(--ink)]">Demo checkout.</strong> This demo uses a manual invoice. No card or payment details are collected.
                @elseif(count($gateways) === 1 && isset($gateways['manual']))
                    <strong class="text-[var(--ink)]">Invoice checkout.</strong> This store sends payment instructions after the order is placed. No card details are collected.
                @else
                    <strong class="text-[var(--ink)]">Secure checkout.</strong> Payments are processed by Stripe or PayPal. ShopKeeprr never stores card details.
                @endif
            </div>
        </aside>
    </form>
</div>
@endsection
