@php
    $social = nexus_setting('store.social_links', []);
    $navCategories = $navCategories ?? collect();
@endphp
<footer class="mt-16 border-t border-[var(--line)] bg-[var(--footer-bg)] text-[var(--footer-ink)]">
    <div class="container-wide grid gap-9 py-11 sm:grid-cols-2 lg:grid-cols-[1.3fr_1fr_1fr_1fr]">
        <div>
            <div class="flex items-center gap-2">
                <span class="grid grid-cols-2 overflow-hidden rounded-sm text-[13px] font-black leading-none text-white">
                    <span class="grid h-4 w-4 place-items-center bg-[#e53238]">S</span><span class="grid h-4 w-4 place-items-center bg-[#0064d2]">K</span>
                    <span class="grid h-4 w-4 place-items-center bg-[#f5af02]">C</span><span class="grid h-4 w-4 place-items-center bg-[#86b817]">S</span>
                </span>
                <span class="text-xl font-bold">{{ nexus_setting('store.name', 'ShopKeeprr') }}</span>
            </div>
            <p class="mt-3 max-w-xs text-[13px] leading-6 text-[var(--footer-muted)]">{{ nexus_setting('store.footer_text', 'A marketplace for the things you actually want.') }}</p>
            @if(is_array($social) && count($social))
                <div class="mt-4 flex flex-wrap gap-4 text-xs font-semibold">
                    @foreach($social as $label => $url)
                        <a href="{{ $url }}" target="_blank" rel="noopener" class="text-[var(--footer-muted)] hover:text-[var(--link)]">{{ $label }}</a>
                    @endforeach
                </div>
            @endif
        </div>

        <div>
            <div class="text-[13px] font-bold">Buy</div>
            <div class="mt-3 grid gap-2 text-[13px] text-[var(--footer-muted)]">
                @foreach($navCategories->take(4) as $navCategory)
                    <a href="{{ route('storefront.category', $navCategory->slug) }}" class="hover:text-[var(--link)]">{{ $navCategory->name }}</a>
                @endforeach
                <a href="{{ route('storefront.shop') }}" class="hover:text-[var(--link)]">All listings</a>
                <a href="{{ route('storefront.shop', ['sort' => 'newest']) }}" class="hover:text-[var(--link)]">New arrivals</a>
            </div>
        </div>

        <div>
            <div class="text-[13px] font-bold">Sell &amp; trade</div>
            <div class="mt-3 grid gap-2 text-[13px] text-[var(--footer-muted)]">
                @if(nexus_mode()?->value === 'b2b')
                    <a href="{{ route('storefront.trade') }}" class="hover:text-[var(--link)]">Business accounts</a>
                @endif
                <a href="{{ route('storefront.page', 'about') }}" class="hover:text-[var(--link)]">About the marketplace</a>
                <a href="mailto:{{ nexus_setting('store.email') }}" class="hover:text-[var(--link)]">Contact the team</a>
            </div>
        </div>

        <div>
            <div class="text-[13px] font-bold">Help</div>
            <div class="mt-3 grid gap-2 text-[13px] text-[var(--footer-muted)]">
                <a href="{{ route('storefront.account.orders') }}" class="hover:text-[var(--link)]">Order status</a>
                <a href="{{ route('storefront.page', 'shipping-returns') }}" class="hover:text-[var(--link)]">Shipping &amp; returns</a>
                <a href="{{ route('storefront.page', 'privacy') }}" class="hover:text-[var(--link)]">Privacy</a>
                <a href="{{ route('storefront.page', 'terms') }}" class="hover:text-[var(--link)]">Terms of use</a>
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
