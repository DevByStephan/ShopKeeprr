@php
    $image = $product->imageUrl();
    $hidePrice = nexus_mode()?->value === 'b2b' && ! auth()->user()?->hasWholesaleAccess() && nexus_setting('b2b.hide_prices_guests', true);
    $price = $product->priceFor(auth()->user(), null, 1);
    $freeThreshold = (float) nexus_setting('shipping.free_threshold', 75);
@endphp
<a href="{{ route('storefront.product', $product->slug) }}" class="group flex h-full flex-col rounded-[var(--card-radius)] border border-[var(--line)] bg-[var(--card)] p-3 transition duration-200 hover:shadow-[0_4px_18px_rgba(15,17,17,.14)]">
    <div class="relative aspect-square overflow-hidden rounded-[calc(var(--card-radius)-2px)] bg-[var(--surface-2)]">
        <img src="{{ $image ?: asset('images/placeholder.svg') }}" alt="{{ $product->name }}" class="h-full w-full object-cover transition duration-500 group-hover:scale-[1.03]" loading="lazy">
        @if($product->isOnSale())
            <span class="absolute left-2 top-2 rounded bg-[var(--sale)] px-2 py-1 text-[10px] font-bold uppercase tracking-wide text-white">Save {{ $product->discountPercent() }}%</span>
        @endif
        @if($product->activeSubscriptionPlans->isNotEmpty() && nexus_mode()?->value === 'subscription')
            <span class="absolute bottom-2 left-2 rounded bg-white/95 px-2 py-1 text-[10px] font-bold text-[var(--link)]">Subscribe &amp; save</span>
        @endif
    </div>

    <div class="flex flex-1 flex-col pt-3">
        <h3 class="line-clamp-2 text-[13px] font-medium leading-5 text-[var(--ink)] group-hover:text-[var(--link)]">{{ $product->name }}</h3>
        @if($product->category)
            <div class="mt-1 text-[11px] text-[var(--muted)]">{{ $product->category->name }}</div>
        @endif

        <div class="mt-auto pt-2">
            @if($hidePrice)
                <div class="text-[13px] font-bold text-[var(--link)]">Sign in for trade pricing</div>
            @else
                <div class="flex flex-wrap items-baseline gap-2">
                    <span class="text-[19px] font-bold leading-none text-[var(--price)]">{{ nexus_money($price) }}</span>
                    @if($product->compare_at_price && $product->compare_at_price > $product->price)
                        <span class="text-[11px] text-[var(--muted)] line-through">{{ nexus_money($product->compare_at_price) }}</span>
                    @endif
                </div>
                <div class="mt-1.5 text-[11px] text-[var(--ink)]">
                    @if($freeThreshold > 0 && $price >= $freeThreshold)
                        <span class="font-semibold">FREE delivery</span>
                    @else
                        <span class="font-semibold">Fast delivery</span> available
                    @endif
                </div>
            @endif
            <div class="mt-1 flex items-center gap-1 text-[11px] font-medium text-[var(--success)]">
                @if($product->manage_stock && $product->stock_quantity <= 0)
                    <span class="text-[var(--sale)]">Currently unavailable</span>
                @elseif($product->manage_stock && $product->stock_quantity <= $product->low_stock_threshold)
                    <span class="text-[#b12704]">Only {{ $product->stock_quantity }} left</span>
                @else
                    In stock
                @endif
            </div>
        </div>
    </div>
</a>
