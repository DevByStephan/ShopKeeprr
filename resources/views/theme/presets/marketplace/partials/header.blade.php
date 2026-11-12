@php
    $logo = nexus_setting('store.logo');
    $mode = nexus_mode();
    $navCategories = $navCategories ?? collect();
    $accountName = auth()->user()?->name;
    $firstName = $accountName ? Str::of($accountName)->explode(' ')->first() : null;
@endphp

<div class="hidden bg-[color-mix(in_srgb,var(--header-bg)_82%,#000)] px-4 py-1.5 text-[11px] text-[var(--header-muted)] sm:block">
    <div class="container-wide flex items-center justify-between gap-4">
        <span class="flex items-center gap-1.5">
            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path d="M12 21s7-5.1 7-11a7 7 0 1 0-14 0c0 5.9 7 11 7 11Z"/><circle cx="12" cy="10" r="2.4"/></svg>
            Deliver to <strong class="font-semibold text-[var(--header-ink)]">{{ nexus_setting('store.country', 'US') }}</strong>
        </span>
        <div class="flex items-center gap-5">
            <span>Free delivery over {{ nexus_money(nexus_setting('shipping.free_threshold', 75)) }}</span>
            <a href="{{ route('storefront.page', 'shipping-returns') }}" class="hover:text-[var(--header-ink)]">Help &amp; returns</a>
            @if($mode?->value === 'b2b')
                <a href="{{ route('storefront.trade') }}" class="hover:text-[var(--header-ink)]">Business account</a>
            @endif
        </div>
    </div>
</div>

<header class="sticky top-0 z-40 bg-[var(--header-bg)] text-[var(--header-ink)] shadow-[0_1px_0_rgba(255,255,255,.08)]" x-data="{ mobileSearch: false }">
    <div class="container-wide flex flex-wrap items-center gap-x-4 gap-y-2 py-2.5 lg:flex-nowrap">
        <a href="{{ route('storefront.home') }}" class="flex shrink-0 items-center gap-2.5">
            @if($logo)
                <img src="{{ asset($logo) }}" alt="{{ nexus_setting('store.name') }}" class="h-9 w-auto rounded bg-white/95 p-1">
            @else
                <span class="grid h-9 w-9 place-items-center rounded-md bg-[var(--accent)] text-lg font-black text-[var(--ink)]">{{ mb_substr(nexus_setting('store.name', 'ShopKeeprr'), 0, 1) }}</span>
                <span class="text-[19px] font-bold tracking-tight">{{ nexus_setting('store.name', 'ShopKeeprr') }}</span>
            @endif
        </a>

        <form action="{{ route('storefront.shop') }}" method="get" class="order-last flex w-full overflow-hidden rounded-md bg-white ring-2 ring-transparent transition focus-within:ring-[var(--accent)] lg:order-none lg:flex-1">
            <label for="mk-category" class="sr-only">Category</label>
            <select id="mk-category" name="category" class="hidden max-w-[10rem] shrink-0 cursor-pointer border-0 border-r border-[#cdcdcd] bg-[#f3f3f3] py-2.5 pl-3 pr-7 text-xs font-medium text-[#555] outline-none sm:block">
                <option value="">All departments</option>
                @foreach($navCategories as $navCategory)
                    <option value="{{ $navCategory->slug }}" @selected(request('category') === $navCategory->slug)>{{ $navCategory->name }}</option>
                @endforeach
            </select>
            <input name="q" value="{{ request('q') }}" placeholder="Search {{ nexus_setting('store.name', 'ShopKeeprr') }}" class="min-w-0 flex-1 border-0 px-3 py-2.5 text-sm text-[#111] outline-none placeholder:text-[#767676]">
            <button class="grid w-12 shrink-0 place-items-center bg-[var(--accent)] text-[var(--ink)] transition hover:brightness-95" aria-label="Search">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
            </button>
        </form>

        <div class="ml-auto flex items-center gap-3 lg:ml-0">
            <div class="relative hidden sm:block" x-data="{ open: false }" @click.outside="open = false">
                <button type="button" @click="open = !open" class="block rounded-sm px-1 py-1 text-left leading-tight hover:outline hover:outline-1 hover:outline-white/70">
                    <span class="block text-[11px] text-[var(--header-muted)]">{{ $firstName ? 'Hello, '.$firstName : 'Hello, sign in' }}</span>
                    <span class="flex items-center gap-1 text-[13px] font-bold">Account &amp; Lists <svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg></span>
                </button>
                <div x-show="open" x-cloak class="absolute right-0 mt-2 w-56 overflow-hidden rounded-md border border-[var(--line)] bg-white p-1.5 text-[var(--ink)] shadow-2xl">
                    @auth
                        <div class="px-3 py-2 text-xs text-[#565959]">Signed in as <strong class="block truncate text-[#0f1111]">{{ auth()->user()->email }}</strong></div>
                        <a href="{{ route('storefront.account.index') }}" class="block rounded px-3 py-2 text-sm hover:bg-[#f0f2f2]">Your account</a>
                        <a href="{{ route('storefront.account.orders') }}" class="block rounded px-3 py-2 text-sm hover:bg-[#f0f2f2]">Your orders</a>
                        @if(auth()->user()->isAdmin())
                            <a href="{{ route('admin.dashboard') }}" class="block rounded px-3 py-2 text-sm font-semibold text-[var(--link)] hover:bg-[#f0f2f2]">Admin panel</a>
                        @endif
                        <form method="post" action="{{ route('logout') }}">@csrf<button class="block w-full rounded px-3 py-2 text-left text-sm text-[#b12704] hover:bg-[#f0f2f2]">Sign out</button></form>
                    @else
                        <a href="{{ route('login') }}" class="block rounded px-3 py-2 text-sm hover:bg-[#f0f2f2]">Sign in</a>
                        @if(!nexus_demo())
                            <a href="{{ route('register') }}" class="block rounded px-3 py-2 text-sm hover:bg-[#f0f2f2]">Create your account</a>
                        @endif
                    @endauth
                </div>
            </div>

            <a href="{{ route('storefront.account.orders') }}" class="hidden rounded-sm px-1 py-1 leading-tight hover:outline hover:outline-1 hover:outline-white/70 sm:block">
                <span class="block text-[11px] text-[var(--header-muted)]">Returns</span>
                <span class="block text-[13px] font-bold">&amp; Orders</span>
            </a>

            <a href="{{ route('storefront.cart') }}" class="relative flex items-end gap-1.5 rounded-sm px-1 py-1 hover:outline hover:outline-1 hover:outline-white/70" aria-label="Cart">
                <span class="relative">
                    <svg class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path d="M3 4h2l2.2 11.2a1.5 1.5 0 0 0 1.5 1.2h8.8a1.5 1.5 0 0 0 1.5-1.2L21 8H6"/><circle cx="10" cy="20" r="1.3" fill="currentColor" stroke="none"/><circle cx="18" cy="20" r="1.3" fill="currentColor" stroke="none"/></svg>
                    <span class="absolute -right-2 -top-1 min-w-5 rounded-full bg-[var(--accent)] px-1 text-center text-[11px] font-bold leading-5 text-[var(--ink)]">{{ $navCartCount ?? 0 }}</span>
                </span>
                <span class="hidden text-[13px] font-bold sm:block">Cart</span>
            </a>
        </div>
    </div>

    <nav class="bg-[color-mix(in_srgb,var(--header-bg)_88%,#fff)] text-[var(--header-ink)]">
        <div class="container-wide flex items-center gap-1 overflow-x-auto py-1.5 text-[12.5px] font-medium [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
            <a href="{{ route('storefront.shop') }}" class="flex shrink-0 items-center gap-1.5 rounded-sm px-2.5 py-1.5 font-bold hover:outline hover:outline-1 hover:outline-white/60">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
                All
            </a>
            @foreach($navCategories as $navCategory)
                <a href="{{ route('storefront.category', $navCategory->slug) }}" class="shrink-0 rounded-sm px-2.5 py-1.5 hover:outline hover:outline-1 hover:outline-white/60">{{ $navCategory->name }}</a>
            @endforeach
            <a href="{{ route('storefront.shop', ['sort' => 'newest']) }}" class="shrink-0 rounded-sm px-2.5 py-1.5 hover:outline hover:outline-1 hover:outline-white/60">New arrivals</a>
            @if($mode?->value === 'b2b')
                <a href="{{ route('storefront.trade') }}" class="shrink-0 rounded-sm px-2.5 py-1.5 hover:outline hover:outline-1 hover:outline-white/60">Business</a>
            @endif
            <a href="{{ route('storefront.page', 'about') }}" class="shrink-0 rounded-sm px-2.5 py-1.5 hover:outline hover:outline-1 hover:outline-white/60">About</a>
        </div>
    </nav>
</header>
