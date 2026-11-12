@php
    $image = $product->imageUrl();
    $hidePrice = nexus_mode()?->value === 'b2b' && ! auth()->user()?->hasWholesaleAccess() && nexus_setting('b2b.hide_prices_guests', true);
    $price = $product->priceFor(auth()->user(), null, 1);
    $freeThreshold = (float) nexus_setting('shipping.free_threshold', 75);
@endphp
<a href="{{ route('storefront.product', $product->slug) }}" class="group flex h-full flex-col overflow-hidden rounded-[var(--card-radius)] border border-transparent bg-[var(--card)] transition hover:border-[var(--brand)]/25 hover:shadow-[0_6px_20px_rgba(230,46,4,.12)]">
    <div class="relative aspect-square overflow-hidden bg-[var(--surface-2)]">
        <img src="{{ $image ?: asset('images/placeholder.svg') }}" alt="{{ $product->name }}" class="h-full w-full object-cover transition duration-500 group-hover:scale-[1.04]" loading="lazy">
        @if($product->isOnSale())
            <span class="absolute left-0 top-0 rounded-br-[var(--card-radius)] bg-[var(--brand)] px-2.5 py-1.5 text-[11px] font-black text-white">−{{ $product->discountPercent() }}%</span>
        @endif
        @if($product->is_featured)
            <span class="absolute right-2 top-2 rounded-full bg-[var(--ink-strong)]/85 px-2 py-0.5 text-[9px] font-bold uppercase tracking-[.1em] text-white">Nexus Pick</span>
        @endif
    </div>

    <div class="flex flex-1 flex-col p-3">
        <h3 class="line-clamp-2 text-[13px] leading-5 text-[var(--ink)] group-hover:text-[var(--brand)]">{{ $product->name }}</h3>
        <div class="mt-auto pt-2">
            @if($hidePrice)
                <div class="text-[13px] font-bold text-[var(--brand)]">Sign in for trade pricing</div>
            @else
                <div class="flex flex-wrap items-baseline gap-1.5">
                    <span class="text-[19px] font-black leading-none text-[var(--price)]">{{ nexus_money($price) }}</span>
                    @if($product->compare_at_price && $product->compare_at_price > $product->price)
                        <span class="text-[10px] text-[var(--muted)] line-through">{{ nexus_money($product->compare_at_price) }}</span>
                    @endif
                </div>
                <div class="mt-1.5 flex items-center justify-between gap-2 text-[10px] text-[var(--muted)]">
                    <span>{{ $freeThreshold > 0 && $price < $freeThreshold ? 'Free shipping over '.nexus_money($freeThreshold) : 'Free shipping' }}</span>
                    <span class="shrink-0 text-[var(--success)]">In stock</span>
                </div>
            @endif
        </div>
    </div>
</a>
