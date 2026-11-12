@php
    $social = nexus_setting('store.social_links', []);
    $navCategories = $navCategories ?? collect();
@endphp
<footer class="mt-16 border-t border-[var(--line)] bg-[var(--footer-bg)] text-[var(--footer-ink)]">
    <div class="container-wide grid gap-9 py-11 sm:grid-cols-2 lg:grid-cols-[1.35fr_1fr_1fr_1fr]">
        <div>
            <div class="flex items-center gap-2">
                <span class="grid h-8 w-8 place-items-center rounded-full bg-[var(--brand)] text-base font-black text-white">{{ mb_substr(nexus_setting('store.name', 'ShopKeeprr'), 0, 1) }}</span>
                <span class="text-xl font-black text-[var(--brand)]">{{ nexus_setting('store.name', 'ShopKeeprr') }}</span>
            </div>
            <p class="mt-3 max-w-xs text-[13px] leading-6 text-[var(--footer-muted)]">{{ nexus_setting('store.footer_text', 'Global finds, local prices.') }}</p>
            @if(is_array($social) && count($social))
                <div class="mt-4 flex flex-wrap gap-4 text-xs font-semibold">
                    @foreach($social as $label => $url)
                        <a href="{{ $url }}" target="_blank" rel="noopener" class="text-[var(--footer-muted)] hover:text-[var(--brand)]">{{ $label }}</a>
                    @endforeach
                </div>
            @endif
            <div class="mt-4 flex flex-wrap gap-1.5">
                @foreach(['Visa', 'Mastercard', 'PayPal', 'Stripe'] as $method)
                    <span class="rounded-full border border-[var(--line)] bg-white px-2.5 py-1 text-[10px] font-semibold text-[var(--footer-muted)]">{{ $method }}</span>
                @endforeach
            </div>
        </div>

        <div>
            <div class="text-[13px] font-bold text-[var(--ink)]">Shop</div>
            <div class="mt-3 grid gap-2 text-[13px] text-[var(--footer-muted)]">
                @foreach($navCategories->take(4) as $navCategory)
                    <a href="{{ route('storefront.category', $navCategory->slug) }}" class="hover:text-[var(--brand)]">{{ $navCategory->name }}</a>
                @endforeach
                <a href="{{ route('storefront.shop', ['sort' => 'price_asc']) }}" class="hover:text-[var(--brand)]">SuperDeals</a>
                <a href="{{ route('storefront.shop', ['sort' => 'newest']) }}" class="hover:text-[var(--brand)]">New arrivals</a>
            </div>
        </div>

        <div>
            <div class="text-[13px] font-bold text-[var(--ink)]">Customer care</div>
            <div class="mt-3 grid gap-2 text-[13px] text-[var(--footer-muted)]">
                <a href="{{ route('storefront.account.orders') }}" class="hover:text-[var(--brand)]">Track your order</a>
                <a href="{{ route('storefront.page', 'shipping-returns') }}" class="hover:text-[var(--brand)]">Shipping &amp; returns</a>
                <a href="{{ route('storefront.page', 'privacy') }}" class="hover:text-[var(--brand)]">Privacy</a>
                <a href="{{ route('storefront.page', 'terms') }}" class="hover:text-[var(--brand)]">Terms</a>
            </div>
        </div>

        <div>
            <div class="text-[13px] font-bold text-[var(--ink)]">ShopKeeprr</div>
            <div class="mt-3 grid gap-2 text-[13px] text-[var(--footer-muted)]">
                <a href="{{ route('storefront.page', 'about') }}" class="hover:text-[var(--brand)]">About us</a>
                @if(nexus_mode()?->value === 'b2b')
                    <a href="{{ route('storefront.trade') }}" class="hover:text-[var(--brand)]">Wholesale program</a>
                @endif
                <a href="mailto:{{ nexus_setting('store.email') }}" class="hover:text-[var(--brand)]">Contact us</a>
                <a href="{{ route('storefront.shop') }}" class="hover:text-[var(--brand)]">All products</a>
            </div>
        </div>
    </div>

    <div class="border-t border-[var(--line)]">
        <div class="container-wide flex flex-col gap-2 py-5 text-[11px] text-[var(--footer-muted)] sm:flex-row sm:items-center sm:justify-between">
            <span>© {{ date('Y') }} {{ nexus_setting('store.name', 'ShopKeeprr') }}. All rights reserved.</span>
            <span>Buyer protection · Secure payments · Powered by ShopKeeprr</span>
        </div>
    </div>
</footer>
