@php
    $social = nexus_setting('store.social_links', []);
    $navCategories = $navCategories ?? collect();
@endphp
<footer class="mt-16 bg-[var(--footer-bg)] text-[var(--footer-ink)]">
    <a href="#" class="block bg-[color-mix(in_srgb,var(--footer-bg)_84%,#fff)] py-3.5 text-center text-[12px] font-semibold hover:bg-[color-mix(in_srgb,var(--footer-bg)_76%,#fff)]">Back to top</a>

    <div class="container-wide grid gap-9 py-11 sm:grid-cols-2 lg:grid-cols-[1.3fr_1fr_1fr_1fr]">
        <div>
            <div class="flex items-center gap-2.5">
                <span class="grid h-9 w-9 place-items-center rounded-md bg-[var(--accent)] text-lg font-black text-[var(--ink)]">{{ mb_substr(nexus_setting('store.name', 'ShopKeeprr'), 0, 1) }}</span>
                <span class="text-xl font-bold">{{ nexus_setting('store.name', 'ShopKeeprr') }}</span>
            </div>
            <p class="mt-4 max-w-xs text-[13px] leading-6 text-[var(--footer-muted)]">{{ nexus_setting('store.footer_text', 'Everyday essentials, chosen with care.') }}</p>
            @if(is_array($social) && count($social))
                <div class="mt-4 flex flex-wrap gap-4 text-xs font-semibold text-[var(--footer-muted)]">
                    @foreach($social as $label => $url)
                        <a href="{{ $url }}" target="_blank" rel="noopener" class="hover:text-[var(--footer-ink)]">{{ $label }}</a>
                    @endforeach
                </div>
            @endif
            <div class="mt-5 flex flex-wrap gap-1.5">
                @foreach(['Visa', 'Mastercard', 'Amex', 'PayPal', 'Stripe'] as $method)
                    <span class="rounded border border-white/15 px-2 py-1 text-[10px] font-semibold text-[var(--footer-muted)]">{{ $method }}</span>
                @endforeach
            </div>
        </div>

        <div>
            <div class="text-[13px] font-bold">Shop by department</div>
            <div class="mt-3 grid gap-2 text-[13px] text-[var(--footer-muted)]">
                @foreach($navCategories->take(5) as $navCategory)
                    <a href="{{ route('storefront.category', $navCategory->slug) }}" class="hover:text-[var(--footer-ink)]">{{ $navCategory->name }}</a>
                @endforeach
                <a href="{{ route('storefront.shop') }}" class="hover:text-[var(--footer-ink)]">All products</a>
            </div>
        </div>

        <div>
            <div class="text-[13px] font-bold">Help &amp; services</div>
            <div class="mt-3 grid gap-2 text-[13px] text-[var(--footer-muted)]">
                <a href="{{ route('storefront.account.index') }}" class="hover:text-[var(--footer-ink)]">Your account</a>
                <a href="{{ route('storefront.account.orders') }}" class="hover:text-[var(--footer-ink)]">Track an order</a>
                <a href="{{ route('storefront.page', 'shipping-returns') }}" class="hover:text-[var(--footer-ink)]">Shipping &amp; returns</a>
                <a href="{{ route('storefront.page', 'privacy') }}" class="hover:text-[var(--footer-ink)]">Privacy notice</a>
                <a href="{{ route('storefront.page', 'terms') }}" class="hover:text-[var(--footer-ink)]">Conditions of use</a>
            </div>
        </div>

        <div>
            <div class="text-[13px] font-bold">Company</div>
            <div class="mt-3 grid gap-2 text-[13px] text-[var(--footer-muted)]">
                <a href="{{ route('storefront.page', 'about') }}" class="hover:text-[var(--footer-ink)]">About us</a>
                @if(nexus_mode()?->value === 'b2b')
                    <a href="{{ route('storefront.trade') }}" class="hover:text-[var(--footer-ink)]">Business accounts</a>
                @endif
                <a href="mailto:{{ nexus_setting('store.email') }}" class="hover:text-[var(--footer-ink)]">Contact us</a>
                <a href="{{ route('storefront.shop', ['sort' => 'newest']) }}" class="hover:text-[var(--footer-ink)]">New arrivals</a>
            </div>
        </div>
    </div>

    <div class="border-t border-white/10">
        <div class="container-wide flex flex-col gap-2 py-5 text-[11px] text-[var(--footer-muted)] sm:flex-row sm:items-center sm:justify-between">
            <span>© {{ date('Y') }} {{ nexus_setting('store.name', 'ShopKeeprr') }}. All rights reserved.</span>
            <span>Secure checkout · Powered by ShopKeeprr</span>
        </div>
    </div>
</footer>
