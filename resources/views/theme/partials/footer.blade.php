@php($social = nexus_setting('store.social_links', []))
<footer class="mt-20 border-t border-[var(--line)] bg-[var(--footer-bg)] text-[var(--footer-ink)]">
    <div class="container-wide grid gap-10 py-12 sm:grid-cols-2 lg:grid-cols-4">
        <div class="lg:col-span-2">
            <div class="flex items-center gap-3">
                <span class="grid h-9 w-9 place-items-center rounded-lg bg-white/10 font-bold">{{ mb_substr(nexus_setting('store.name', 'ShopKeeprr'), 0, 1) }}</span>
                <span class="font-display text-xl">{{ nexus_setting('store.name', 'ShopKeeprr') }}</span>
            </div>
            <p class="mt-4 max-w-md text-sm leading-6 text-[var(--footer-muted)]">{{ nexus_setting('store.footer_text', 'Small-batch goods chosen with care.') }}</p>
            @if(is_array($social) && count($social))
                <div class="mt-5 flex gap-4 text-xs font-semibold text-[var(--footer-muted)]">
                    @foreach($social as $label => $url)
                        <a href="{{ $url }}" target="_blank" rel="noopener" class="hover:text-[var(--footer-ink)]">{{ $label }}</a>
                    @endforeach
                </div>
            @endif
        </div>
        <div>
            <div class="mb-3 text-[10px] font-bold uppercase tracking-[.16em] text-[var(--footer-muted)]">Shop</div>
            <div class="grid gap-2 text-sm text-[var(--footer-muted)]">
                <a href="{{ route('storefront.shop') }}" class="hover:text-[var(--footer-ink)]">All products</a>
                <a href="{{ route('storefront.shop', ['sort' => 'newest']) }}" class="hover:text-[var(--footer-ink)]">New arrivals</a>
                @if(nexus_mode()?->value === 'b2b')
                    <a href="{{ route('storefront.trade') }}" class="hover:text-[var(--footer-ink)]">Wholesale application</a>
                @endif
            </div>
        </div>
        <div>
            <div class="mb-3 text-[10px] font-bold uppercase tracking-[.16em] text-[var(--footer-muted)]">Help</div>
            <div class="grid gap-2 text-sm text-[var(--footer-muted)]">
                <a href="{{ route('storefront.page', 'shipping-returns') }}" class="hover:text-[var(--footer-ink)]">Shipping &amp; returns</a>
                <a href="{{ route('storefront.page', 'privacy') }}" class="hover:text-[var(--footer-ink)]">Privacy</a>
                <a href="{{ route('storefront.page', 'terms') }}" class="hover:text-[var(--footer-ink)]">Terms</a>
                <a href="mailto:{{ nexus_setting('store.email') }}" class="hover:text-[var(--footer-ink)]">Contact us</a>
                @if(nexus_demo())
                    <a href="{{ route('demo') }}" class="hover:text-[var(--footer-ink)]">Demo credentials &amp; guide</a>
                    <a href="{{ route('admin.login') }}" class="hover:text-[var(--footer-ink)]">Admin sign-in</a>
                @endif
            </div>
        </div>
    </div>
    <div class="border-t border-white/10">
        <div class="container-wide flex flex-col gap-2 py-5 text-[11px] text-[var(--footer-muted)] sm:flex-row sm:items-center sm:justify-between">
            <span>© {{ date('Y') }} {{ nexus_setting('store.name', 'ShopKeeprr') }}. All rights reserved.</span>
            <span>Powered by ShopKeeprr</span>
        </div>
    </div>
</footer>
