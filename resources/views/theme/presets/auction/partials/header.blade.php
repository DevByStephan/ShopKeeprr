@php
    $logo = nexus_setting('store.logo');
    $mode = nexus_mode();
    $navCategories = $navCategories ?? collect();
    $accountName = auth()->user()?->name;
@endphp

<div class="border-b border-[var(--line)] bg-[var(--surface-2)] text-[11px] text-[var(--muted)]">
    <div class="container-wide flex items-center justify-between gap-4 py-1.5">
        <span class="hidden sm:inline">Free delivery over {{ nexus_money(nexus_setting('shipping.free_threshold', 75)) }} · 30-day returns</span>
        <div class="ml-auto flex items-center gap-4">
            @auth
                <span>Hi {{ Str::of($accountName)->explode(' ')->first() }}!</span>
                <a href="{{ route('storefront.account.orders') }}" class="hover:text-[var(--link)]">My orders</a>
                <a href="{{ route('storefront.account.index') }}" class="hover:text-[var(--link)]">Account</a>
            @else
                <span>Hi! <a href="{{ route('login') }}" class="font-semibold text-[var(--link)] hover:underline">Sign in</a>@if(!nexus_demo()) or <a href="{{ route('register') }}" class="text-[var(--link)] hover:underline">register</a>@endif</span>
            @endauth
            <a href="{{ route('storefront.page', 'shipping-returns') }}" class="hidden hover:text-[var(--link)] sm:inline">Help</a>
        </div>
    </div>
</div>

<header class="sticky top-0 z-40 border-b border-[var(--line)] bg-[var(--header-bg)] text-[var(--header-ink)] shadow-[0_1px_3px_rgba(0,0,0,.06)]">
    <div class="container-wide flex flex-wrap items-center gap-x-5 gap-y-2.5 py-3 lg:flex-nowrap">
        <a href="{{ route('storefront.home') }}" class="flex shrink-0 items-center gap-2">
            @if($logo)
                <img src="{{ asset($logo) }}" alt="{{ nexus_setting('store.name') }}" class="h-10 w-auto">
            @else
                <span class="grid grid-cols-2 overflow-hidden rounded-sm text-[15px] font-black leading-none text-white">
                    <span class="grid h-4.5 w-4.5 place-items-center bg-[#e53238]">S</span><span class="grid h-4.5 w-4.5 place-items-center bg-[#0064d2]">K</span>
                    <span class="grid h-4.5 w-4.5 place-items-center bg-[#f5af02]">C</span><span class="grid h-4.5 w-4.5 place-items-center bg-[#86b817]">S</span>
                </span>
                <span class="text-[22px] font-bold tracking-tight text-[var(--ink)]">{{ nexus_setting('store.name', 'ShopKeeprr') }}</span>
            @endif
        </a>

        <form action="{{ route('storefront.shop') }}" method="get" class="order-last flex w-full overflow-hidden rounded-full border-2 border-[var(--brand)] bg-white lg:order-none lg:flex-1">
            <label for="au-category" class="sr-only">Category</label>
            <select id="au-category" name="category" class="hidden max-w-[10rem] cursor-pointer border-0 border-r border-[var(--line)] bg-[#f5f5f5] py-2 pl-4 pr-7 text-xs text-[#555] outline-none sm:block">
                <option value="">All categories</option>
                @foreach($navCategories as $navCategory)
                    <option value="{{ $navCategory->slug }}" @selected(request('category') === $navCategory->slug)>{{ $navCategory->name }}</option>
                @endforeach
            </select>
            <input name="q" value="{{ request('q') }}" placeholder="Search for anything" class="min-w-0 flex-1 border-0 px-4 py-2 text-sm text-[#191919] outline-none placeholder:text-[#888]">
            <button class="shrink-0 bg-[var(--brand)] px-6 text-sm font-bold text-white transition hover:brightness-90">Search</button>
        </form>

        <div class="ml-auto flex items-center gap-4 lg:ml-0">
            <a href="{{ route('storefront.account.orders') }}" class="hidden text-center text-[11px] leading-tight text-[var(--muted)] hover:text-[var(--link)] sm:block">
                <svg class="mx-auto h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path d="M6 3h12v18l-6-4-6 4V3Z"/></svg>
                <span class="mt-0.5 block">Orders</span>
            </a>
            <a href="{{ route('storefront.cart') }}" class="relative flex items-center gap-1.5 text-[13px] font-semibold hover:text-[var(--link)]" aria-label="Cart">
                <span class="relative">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path d="M3 4h2l2.2 11.2a1.5 1.5 0 0 0 1.5 1.2h8.8a1.5 1.5 0 0 0 1.5-1.2L21 8H6"/><circle cx="10" cy="20" r="1.3" fill="currentColor" stroke="none"/><circle cx="18" cy="20" r="1.3" fill="currentColor" stroke="none"/></svg>
                    <span class="absolute -right-2 -top-1 min-w-4 rounded-full bg-[var(--brand)] px-1 text-center text-[10px] font-bold leading-4 text-white">{{ $navCartCount ?? 0 }}</span>
                </span>
                <span class="hidden sm:inline">Cart</span>
            </a>
        </div>
    </div>

    <nav class="border-t border-[var(--line)]">
        <div class="container-wide flex items-center gap-0.5 overflow-x-auto py-1 text-[12.5px] font-medium [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
            <a href="{{ route('storefront.shop') }}" class="shrink-0 px-3 py-1.5 hover:bg-[var(--surface-2)] hover:text-[var(--link)]">All listings</a>
            @foreach($navCategories as $navCategory)
                <a href="{{ route('storefront.category', $navCategory->slug) }}" class="shrink-0 px-3 py-1.5 hover:bg-[var(--surface-2)] hover:text-[var(--link)]">{{ $navCategory->name }}</a>
            @endforeach
            <a href="{{ route('storefront.shop', ['sort' => 'price_asc']) }}" class="shrink-0 px-3 py-1.5 font-semibold text-[var(--sale)] hover:bg-[var(--surface-2)]">Deals</a>
            <a href="{{ route('storefront.shop', ['sort' => 'newest']) }}" class="shrink-0 px-3 py-1.5 hover:bg-[var(--surface-2)] hover:text-[var(--link)]">New arrivals</a>
            @if($mode?->value === 'b2b')
                <a href="{{ route('storefront.trade') }}" class="shrink-0 px-3 py-1.5 hover:bg-[var(--surface-2)] hover:text-[var(--link)]">Business</a>
            @endif
            <a href="{{ route('storefront.page', 'about') }}" class="shrink-0 px-3 py-1.5 hover:bg-[var(--surface-2)] hover:text-[var(--link)]">About</a>
        </div>
    </nav>
</header>
