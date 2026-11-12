@php
    $image = $product->imageUrl();
    $hidePrice = nexus_mode()?->value === 'b2b' && ! auth()->user()?->hasWholesaleAccess() && nexus_setting('b2b.hide_prices_guests', true);
    $price = $product->priceFor(auth()->user(), null, 1);
@endphp
<a href="{{ route('storefront.product', $product->slug) }}" class="group block">
    <div class="relative aspect-[4/5] overflow-hidden rounded-2xl bg-[var(--surface-2)]">
        <img src="{{ $image ?: asset('images/placeholder.svg') }}" alt="{{ $product->name }}" class="h-full w-full object-cover transition duration-700 group-hover:scale-[1.04]" loading="lazy">
        @if($product->isOnSale())
            <span class="absolute left-3 top-3 rounded-full bg-[var(--sale)] px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider text-white">−{{ $product->discountPercent() }}%</span>
        @endif
        @if($product->activeSubscriptionPlans->isNotEmpty() && nexus_mode()?->value === 'subscription')
            <span class="absolute bottom-3 left-3 rounded-full bg-white/90 px-2.5 py-1 text-[10px] font-bold text-[var(--link)] backdrop-blur">Subscription</span>
        @endif
    </div>
    <div class="pt-3">
        <div class="flex items-start justify-between gap-3">
            <div>
                <h3 class="text-[15px] font-semibold tracking-tight text-[var(--ink)]">{{ $product->name }}</h3>
                @if($product->category)
                    <div class="mt-0.5 text-[11px] uppercase tracking-[.1em] text-[var(--muted)]">{{ $product->category->name }}</div>
                @endif
            </div>
            @if($hidePrice)
                <span class="whitespace-nowrap text-[11px] font-semibold text-[var(--link)]">Trade price</span>
            @else
                <span class="whitespace-nowrap text-sm font-semibold text-[var(--price)]">{{ nexus_money($price) }}</span>
            @endif
        </div>
        @if($hidePrice)
            <div class="mt-1 text-[11px] text-[var(--muted)]">Sign in for your price</div>
        @elseif($product->compare_at_price && $product->compare_at_price > $product->price)
            <div class="mt-1 text-xs text-[var(--muted)] line-through">{{ nexus_money($product->compare_at_price) }}</div>
        @endif
    </div>
</a>
