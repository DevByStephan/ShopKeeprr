@php
    $logo = nexus_setting('store.logo');
    $mode = nexus_mode();
    $navCategories = $navCategories ?? collect();
@endphp

<div class="bg-[var(--brand)] px-4 py-1.5 text-[11px] text-white">
    <div class="container-wide flex items-center justify-between gap-4">
        <span class="truncate font-medium">{{ nexus_setting('store.announcement', 'Free shipping on orders over $75') }}</span>
        <div class="hidden shrink-0 items-center gap-4 text-white/85 sm:flex">
            <a href="{{ route('storefront.account.orders') }}" class="hover:text-white">Track order</a>
            <a href="{{ route('storefront.page', 'shipping-returns') }}" class="hover:text-white">Help</a>
            @auth
                <a href="{{ route('storefront.account.index') }}" class="hover:text-white">Account</a>
            @else
                <a href="{{ route('login') }}" class="hover:text-white">Sign in</a>
            @endauth
        </div>
    </div>
</div>

<header class="sticky top-0 z-40 border-b border-[var(--line)] bg-[var(--header-bg)] shadow-[0_1px_3px_rgba(0,0,0,.05)]">
    <div class="container-wide flex flex-wrap items-center gap-x-5 gap-y-2.5 py-3 lg:flex-nowrap">
        <a href="{{ route('storefront.home') }}" class="flex shrink-0 items-center gap-2">
            @if($logo)
                <img src="{{ asset($logo) }}" alt="{{ nexus_setting('store.name') }}" class="h-9 w-auto">
            @else
                <span class="grid h-9 w-9 place-items-center rounded-full bg-[var(--brand)] text-lg font-black text-white">{{ mb_substr(nexus_setting('store.name', 'ShopKeeprr'), 0, 1) }}</span>
                <span class="text-[21px] font-black tracking-tight text-[var(--brand)]">{{ nexus_setting('store.name', 'ShopKeeprr') }}</span>
            @endif
        </a>

        <form action="{{ route('storefront.shop') }}" method="get" class="order-last flex w-full overflow-hidden rounded-full border-2 border-[var(--brand)] bg-white lg:order-none lg:max-w-2xl lg:flex-1">
            <input name="q" value="{{ request('q') }}" placeholder="Search the bazaar" class="min-w-0 flex-1 border-0 px-4 py-2 text-sm text-[#1a1a1a] outline-none placeholder:text-[#999]">
            <button class="shrink-0 bg-[var(--brand)] px-6 text-sm font-bold text-white transition hover:brightness-95">Search</button>
        </form>

        <div class="ml-auto flex items-center gap-4">
            <a href="{{ route('storefront.account.index') }}" class="hidden items-center gap-1.5 text-[12px] font-medium text-[var(--header-muted)] hover:text-[var(--brand)] sm:flex">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><circle cx="12" cy="8" r="3.2"/><path d="M5 20c.7-3.8 3.2-5.8 7-5.8s6.3 2 7 5.8"/></svg>
                {{ auth()->check() ? 'Account' : 'Sign in' }}
            </a>
            <a href="{{ route('storefront.cart') }}" class="relative flex items-center gap-1.5 text-[12px] font-medium text-[var(--header-muted)] hover:text-[var(--brand)]" aria-label="Cart">
                <span class="relative">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path d="M3 4h2l2.2 11.2a1.5 1.5 0 0 0 1.5 1.2h8.8a1.5 1.5 0 0 0 1.5-1.2L21 8H6"/><circle cx="10" cy="20" r="1.3" fill="currentColor" stroke="none"/><circle cx="18" cy="20" r="1.3" fill="currentColor" stroke="none"/></svg>
                    <span class="absolute -right-2 -top-1 min-w-5 rounded-full bg-[var(--brand)] px-1 text-center text-[10px] font-bold leading-5 text-white">{{ $navCartCount ?? 0 }}</span>
                </span>
                <span class="hidden sm:inline">Cart</span>
            </a>
        </div>
    </div>

    <nav class="border-t border-[var(--line)]">
        <div class="container-wide flex items-center gap-1.5 overflow-x-auto py-2 text-[12px] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
            <a href="{{ route('storefront.shop') }}" class="shrink-0 rounded-full bg-[var(--brand-soft)] px-3 py-1.5 font-semibold text-[var(--brand)]">All</a>
            @foreach($navCategories as $navCategory)
                <a href="{{ route('storefront.category', $navCategory->slug) }}" class="shrink-0 rounded-full px-3 py-1.5 text-[var(--ink)] transition hover:bg-[var(--surface-2)] hover:text-[var(--brand)]">{{ $navCategory->name }}</a>
            @endforeach
            <a href="{{ route('storefront.shop', ['sort' => 'price_asc']) }}" class="shrink-0 rounded-full px-3 py-1.5 font-semibold text-[var(--brand)] transition hover:bg-[var(--surface-2)]">SuperDeals</a>
            <a href="{{ route('storefront.shop', ['sort' => 'newest']) }}" class="shrink-0 rounded-full px-3 py-1.5 text-[var(--ink)] transition hover:bg-[var(--surface-2)] hover:text-[var(--brand)]">New in</a>
            @if($mode?->value === 'b2b')
                <a href="{{ route('storefront.trade') }}" class="shrink-0 rounded-full px-3 py-1.5 text-[var(--ink)] transition hover:bg-[var(--surface-2)] hover:text-[var(--brand)]">Wholesale</a>
            @endif
        </div>
    </nav>
</header>
