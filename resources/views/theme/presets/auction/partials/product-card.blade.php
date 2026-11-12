@php
    $image = $product->imageUrl();
    $hidePrice = nexus_mode()?->value === 'b2b' && ! auth()->user()?->hasWholesaleAccess() && nexus_setting('b2b.hide_prices_guests', true);
    $price = $product->priceFor(auth()->user(), null, 1);
    $freeThreshold = (float) nexus_setting('shipping.free_threshold', 75);
@endphp
<a href="{{ route('storefront.product', $product->slug) }}" class="group flex h-full flex-col overflow-hidden rounded-[var(--card-radius)] border border-[var(--line)] bg-[var(--card)] transition hover:border-[#b6c3d4] hover:shadow-[0_3px_12px_rgba(17,24,32,.10)]">
    <div class="relative aspect-square overflow-hidden bg-[var(--surface-2)] p-2">
        <img src="{{ $image ?: asset('images/placeholder.svg') }}" alt="{{ $product->name }}" class="h-full w-full object-contain transition duration-500 group-hover:scale-[1.03]" loading="lazy">
        @if($product->isOnSale())
            <span class="absolute left-2 top-2 rounded-sm bg-[var(--sale)] px-2 py-1 text-[10px] font-bold uppercase tracking-wide text-white">−{{ $product->discountPercent() }}%</span>
        @endif
    </div>

    <div class="flex flex-1 flex-col border-t border-[var(--line)] p-3">
        <h3 class="line-clamp-2 text-[13.5px] font-medium leading-5 text-[var(--link)] group-hover:underline">{{ $product->name }}</h3>
        @if($product->category)
            <div class="mt-1 text-[11px] text-[var(--muted)]">{{ $product->category->name }}</div>
        @endif
        <div class="mt-auto pt-2.5">
            @if($hidePrice)
                <div class="text-[13px] font-bold text-[var(--link)]">Sign in for trade pricing</div>
            @else
                <div class="flex items-baseline gap-2">
                    <span class="text-[19px] font-bold leading-none text-[var(--price)]">{{ nexus_money($price) }}</span>
                    @if($product->compare_at_price && $product->compare_at_price > $product->price)
                        <span class="text-[11px] text-[var(--muted)] line-through">{{ nexus_money($product->compare_at_price) }}</span>
                    @endif
                </div>
                <div class="mt-1 text-[11px] text-[var(--success)]">
                    @if($product->manage_stock && $product->stock_quantity <= 0)
                        <span class="text-[var(--sale)]">Out of stock</span>
                    @elseif($freeThreshold > 0 && $price < $freeThreshold)
                        Free shipping over {{ nexus_money($freeThreshold) }}
                    @else
                        Free shipping
                    @endif
                </div>
                <span class="mt-2 inline-flex rounded-sm border border-[var(--line)] px-2.5 py-1 text-[11px] font-semibold text-[var(--ink)] group-hover:border-[var(--brand)] group-hover:text-[var(--link)]">View listing</span>
            @endif
        </div>
    </div>
</a>
