@extends('theme.layouts.app')

@section('title', $product->name.' — '.nexus_setting('store.name'))
@section('description', $product->short_description ?: Str::limit(strip_tags((string) $product->description), 150))

@section('content')
@php
    $hidePrice = nexus_mode()?->value === 'b2b' && ! auth()->user()?->hasWholesaleAccess() && nexus_setting('b2b.hide_prices_guests', true);
    $variants = $product->activeVariants;
    $plans = $product->activeSubscriptionPlans;
    $subscriptionMode = nexus_mode()?->value === 'subscription';
    $minQty = nexus_mode()?->value === 'b2b' ? max(1, (int) $product->min_order_quantity) : 1;
    $unitPrice = $product->priceFor(auth()->user(), null, $minQty);
@endphp

<div class="container-wide py-8">
    <nav class="text-[11px] font-semibold uppercase tracking-[.12em] text-[var(--muted)]">
        <a href="{{ route('storefront.home') }}" class="hover:text-[var(--link)]">Home</a>
        <span class="px-2">/</span>
        <a href="{{ route('storefront.shop') }}" class="hover:text-[var(--link)]">Shop</a>
        @if($product->category)
            <span class="px-2">/</span>
            <a href="{{ route('storefront.category', $product->category->slug) }}" class="hover:text-[var(--link)]">{{ $product->category->name }}</a>
        @endif
    </nav>

    <div class="mt-6 grid gap-8 lg:grid-cols-[1.05fr_.95fr] lg:gap-12" x-data="{ variant: {{ $variants->first()?->id ?? 'null' }}, plan: {{ $plans->first()?->id ?? 'null' }}, quantity: {{ $minQty }} }">
        <div class="space-y-3">
            <div class="overflow-hidden rounded-3xl border border-[var(--line)] bg-[var(--surface-2)]">
                @if($product->imageUrl())
                    <img src="{{ $product->imageUrl() }}" alt="{{ $product->name }}" class="aspect-square w-full object-cover">
                @else
                    <img src="{{ asset('images/placeholder.svg') }}" alt="" class="aspect-square w-full object-cover">
                @endif
            </div>
            @if($product->images->count() > 1)
                <div class="grid grid-cols-4 gap-3">
                    @foreach($product->images as $image)
                        <div class="overflow-hidden rounded-xl border border-[var(--line)] bg-white">
                            <img src="{{ asset($image->path) }}" alt="{{ $image->alt ?: $product->name }}" class="aspect-square w-full object-cover">
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <div>
            <div class="flex flex-wrap items-center gap-2">
                @if($product->category)
                    <span class="badge badge-slate">{{ $product->category->name }}</span>
                @endif
                @if($product->is_featured)
                    <span class="badge badge-emerald">Staff pick</span>
                @endif
                @if($product->isOnSale())
                    <span class="badge badge-rose">Save {{ $product->discountPercent() }}%</span>
                @endif
                @if($product->vendor)
                    <a href="{{ route('storefront.vendors.show', $product->vendor->slug) }}" class="badge badge-slate hover:text-[var(--link)]">Sold by {{ $product->vendor->name }}</a>
                @endif
            </div>
            <h1 class="mt-3 font-display text-4xl leading-tight tracking-tight sm:text-5xl">{{ $product->name }}</h1>
            @if($product->short_description)
                <p class="mt-3 text-[15px] leading-7 text-[var(--muted)]">{{ $product->short_description }}</p>
            @endif

            <div class="mt-5 flex flex-wrap items-end gap-3">
                @if($hidePrice)
                    <div class="font-display text-2xl text-[var(--link)]">Sign in for trade pricing</div>
                @else
                    <div class="font-display text-3xl">{{ nexus_money($unitPrice) }}</div>
                    @if($product->compare_at_price && $product->compare_at_price > $product->price)
                        <div class="pb-1 text-sm text-[var(--muted)] line-through">{{ nexus_money($product->compare_at_price) }}</div>
                    @endif
                    @if($product->manage_stock && $product->stock_quantity <= 0)
                        <span class="badge badge-rose">Sold out</span>
                    @elseif($product->manage_stock && $product->stock_quantity <= $product->low_stock_threshold)
                        <span class="badge badge-amber">Only {{ $product->stock_quantity }} left</span>
                    @endif
                @endif
            </div>

            <form action="{{ route('storefront.cart.add') }}" method="post" class="mt-7 space-y-5">
                @csrf
                <input type="hidden" name="product_id" value="{{ $product->id }}">

                @if($variants->isNotEmpty())
                    <div>
                        <label class="label">Option</label>
                        <div class="grid gap-2">
                            @foreach($variants as $variant)
                                <label class="flex cursor-pointer items-center justify-between rounded-xl border border-[var(--line)] bg-white px-4 py-3 text-sm transition" :class="variant === {{ $variant->id }} ? '!border-[var(--brand)] ring-2 ring-[var(--brand)]/15' : ''">
                                    <span class="flex items-center gap-3">
                                        <input type="radio" name="variant_id" value="{{ $variant->id }}" class="accent-[var(--brand)]" x-model.number="variant" @checked($loop->first)>
                                        <span class="font-semibold">{{ $variant->displayName() }}</span>
                                    </span>
                                    <span class="text-[var(--muted)]">{{ nexus_money($variant->price ?: $product->price) }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if($subscriptionMode && $plans->isNotEmpty())
                    <div>
                        <label class="label">Billing plan</label>
                        <div class="grid gap-2">
                            @foreach($plans as $plan)
                                <label class="flex cursor-pointer items-center justify-between rounded-xl border border-[var(--line)] bg-white px-4 py-3 text-sm" :class="plan === {{ $plan->id }} ? '!border-[var(--brand)] ring-2 ring-[var(--brand)]/15' : ''">
                                    <span class="flex items-center gap-3">
                                        <input type="radio" name="subscription_plan_id" value="{{ $plan->id }}" class="accent-[var(--brand)]" x-model.number="plan" @checked($loop->first)>
                                        <span>
                                            <span class="block font-semibold">{{ $plan->name }}</span>
                                            <span class="text-xs text-[var(--muted)]">Billed every {{ $plan->billingLabel() }}@if($plan->trial_days) · {{ $plan->trial_days }}-day trial @endif</span>
                                        </span>
                                    </span>
                                    <span class="font-semibold">{{ nexus_money($plan->price) }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endif

                <div class="flex flex-wrap items-end gap-3">
                    <div class="w-28">
                        <label class="label" for="quantity">Quantity</label>
                        <input id="quantity" type="number" name="quantity" min="{{ $minQty }}" max="999" x-model.number="quantity" class="input">
                    </div>
                    <button class="btn-primary flex-1 disabled:cursor-not-allowed disabled:opacity-50" @if(! $hidePrice && $product->manage_stock && $product->stock_quantity <= 0) disabled @endif>
                        Add to bag
                        @unless($hidePrice) · {{ nexus_money($unitPrice) }}{{ $minQty > 1 ? ' each' : '' }} @endunless
                    </button>
                </div>
                @if($minQty > 1)
                    <p class="text-xs text-[var(--muted)]">Minimum order quantity: {{ $minQty }} units.</p>
                @endif
                @if($hidePrice)
                    <p class="text-xs text-[var(--muted)]">Trade pricing is visible after your wholesale account is approved.</p>
                @endif
            </form>

            <div class="mt-7 grid gap-2 rounded-2xl border border-[var(--line)] bg-white p-4 text-xs text-[var(--muted)] sm:grid-cols-2">
                <div class="flex items-center gap-2"><span class="h-1.5 w-1.5 rounded-full bg-[var(--brand)]"></span> Free shipping over {{ nexus_money(75) }}</div>
                <div class="flex items-center gap-2"><span class="h-1.5 w-1.5 rounded-full bg-[var(--brand)]"></span> 30-day returns</div>
                <div class="flex items-center gap-2"><span class="h-1.5 w-1.5 rounded-full bg-[var(--brand)]"></span> Secure checkout</div>
                <div class="flex items-center gap-2"><span class="h-1.5 w-1.5 rounded-full bg-[var(--brand)]"></span> Real humans on support</div>
            </div>

            @if($product->description)
                <div class="prose-nexus mt-8 border-t border-[var(--line)] pt-6">
                    {!! nexus_page_content(strip_tags(str_replace(['</p>', '<br>', '<br/>', '<br />'], ["\n\n", "\n", "\n", "\n"], $product->description))) !!}
                </div>
            @endif

            @includeIf('wishlist::button', ['product' => $product, 'class' => 'mt-4'])
            @includeIf('product-comparison::product-button', ['product' => $product])

            @if($product->sku)
                <div class="mt-5 text-xs text-[var(--muted)]">SKU: {{ $product->sku }}</div>
            @endif
        </div>
    </div>

    @includeIf('bundles::product-section', ['product' => $product])
    @includeIf('reviews::product-section', ['product' => $product])
    @includeIf('product-questions-answers::product-section', ['product' => $product])
    @includeIf('preorders-backorders::product-section', ['product' => $product])
    @includeIf('recently-viewed-products::tracker', ['product' => $product])
</div>

@if($related->isNotEmpty())
<section class="container-wide border-t border-[var(--line)] py-12">
    <div class="flex items-end justify-between">
        <h2 class="font-display text-3xl tracking-tight">You may also like</h2>
        <a href="{{ route('storefront.shop') }}" class="text-xs font-bold uppercase tracking-[.1em] text-[var(--muted)] hover:text-[var(--link)]">View all →</a>
    </div>
    <div class="mt-6 grid grid-cols-2 gap-x-5 gap-y-8 sm:grid-cols-4">
        @foreach($related as $item)
            @include(nexus_partial('partials.product-card'), ['product' => $item])
        @endforeach
    </div>
</section>
@endif
@endsection
