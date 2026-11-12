@php
    $logo = nexus_setting('store.logo');
    $mode = nexus_mode();
    $cartCount = $navCartCount ?? 0;
@endphp
@if(nexus_demo())
<div class="bg-[var(--brand)] px-4 py-1.5 text-[11px] font-semibold text-[var(--button-ink)]">
    <div class="container-wide flex flex-wrap items-center justify-center gap-x-4 gap-y-1">
        <span class="rounded-full bg-black/15 px-2 py-0.5 text-[9px] font-black uppercase tracking-[.12em]">{{ nexus_demo_label() }}</span>
        <a href="{{ route('storefront.home') }}" class="hover:underline">Storefront</a>
        <span class="opacity-50">·</span>
        <a href="{{ route('admin.login') }}" class="hover:underline">Admin panel</a>
        <span class="opacity-50">·</span>
        <a href="{{ route('demo') }}" class="font-bold underline underline-offset-2">Demo credentials &amp; guide</a>
    </div>
</div>
@endif

<div class="bg-[var(--ink-strong)] px-4 py-2 text-center text-[11px] font-semibold tracking-[.12em] text-white/80">
    {{ nexus_setting('store.announcement', 'Free shipping on orders over $75') }}
</div>
<header class="sticky top-0 z-30 border-b border-[var(--line)] bg-[var(--header-bg)]/95 backdrop-blur" x-data="{ mobile: false, account: false }">
    <div class="container-wide flex h-[74px] items-center justify-between gap-4">
        <a href="{{ route('storefront.home') }}" class="flex items-center gap-3">
            @if($logo)
                <img src="{{ asset($logo) }}" alt="{{ nexus_setting('store.name') }}" class="h-10 w-auto">
            @else
                <span class="grid h-10 w-10 place-items-center rounded-xl bg-[var(--brand)] text-lg font-bold text-[var(--button-ink)]">{{ mb_substr(nexus_setting('store.name', 'ShopKeeprr'), 0, 1) }}</span>
                <span class="hidden font-display text-[22px] tracking-tight sm:block">{{ nexus_setting('store.name', 'ShopKeeprr') }}</span>
            @endif
        </a>

        <nav class="hidden items-center gap-7 md:flex">
            <a href="{{ route('storefront.shop') }}" class="store-link store-link-underline">Shop</a>
            <a href="{{ route('storefront.shop', ['sort' => 'newest']) }}" class="store-link store-link-underline">New arrivals</a>
            @if($mode?->allowsVendors() && \Illuminate\Support\Facades\Route::has('storefront.vendors.index'))
                <a href="{{ route('storefront.vendors.index') }}" class="store-link store-link-underline">Vendors</a>
            @endif
            @if($mode?->value === 'b2b')
                <a href="{{ route('storefront.trade') }}" class="store-link store-link-underline">Trade program</a>
            @endif
            <a href="{{ route('storefront.page', 'about') }}" class="store-link store-link-underline">Our story</a>
        </nav>

        <div class="flex items-center gap-1 sm:gap-2">
            <form action="{{ route('storefront.shop') }}" method="get" class="hidden items-center rounded-full border border-[var(--line)] bg-[var(--card)] px-3 lg:flex">
                <svg class="h-4 w-4 text-[var(--muted)]" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                <input name="q" value="{{ request('q') }}" placeholder="Search the shop" class="w-36 border-0 bg-transparent px-2 py-2 text-xs outline-none placeholder:text-[var(--muted)]">
            </form>

            <div class="relative" @click.outside="account = false">
                <button type="button" @click="account = !account" class="btn-ghost !px-2" aria-label="Account">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><circle cx="12" cy="8" r="3.5"/><path d="M4.5 20c.8-4 3.3-6 7.5-6s6.7 2 7.5 6"/></svg>
                </button>
                <div x-show="account" x-cloak class="absolute right-0 mt-2 w-52 overflow-hidden rounded-xl border border-[var(--line)] bg-[var(--card)] p-1.5 shadow-xl">
                    @auth
                        <a href="{{ route('storefront.account.index') }}" class="block rounded-lg px-3 py-2 text-sm hover:bg-[var(--surface-2)]">My account</a>
                        <a href="{{ route('storefront.account.orders') }}" class="block rounded-lg px-3 py-2 text-sm hover:bg-[var(--surface-2)]">Orders</a>
                        @if(auth()->user()->isAdmin())
                            <a href="{{ route('admin.dashboard') }}" class="block rounded-lg px-3 py-2 text-sm font-semibold text-[var(--link)] hover:bg-[var(--surface-2)]">Admin panel</a>
                        @endif
                        <form method="post" action="{{ route('logout') }}">
                            @csrf
                            <button class="block w-full rounded-lg px-3 py-2 text-left text-sm text-rose-600 hover:bg-rose-50">Sign out</button>
                        </form>
                    @else
                        <a href="{{ route('login') }}" class="block rounded-lg px-3 py-2 text-sm hover:bg-[var(--surface-2)]">Sign in</a>
                        @if(!nexus_demo())
                            <a href="{{ route('register') }}" class="block rounded-lg px-3 py-2 text-sm hover:bg-[var(--surface-2)]">Create account</a>
                        @endif
                    @endauth
                </div>
            </div>

            <a href="{{ route('storefront.cart') }}" class="relative rounded-full p-2 text-[var(--ink)] transition hover:bg-[var(--surface-2)]" aria-label="Bag">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path d="M6 8h12l-1 13H7L6 8Z"/><path d="M9 9V6a3 3 0 0 1 6 0v3"/></svg>
                @if($cartCount > 0)
                    <span class="absolute -right-0.5 -top-0.5 grid h-4 min-w-4 place-items-center rounded-full bg-[var(--brand)] px-1 text-[10px] font-bold text-[var(--button-ink)]">{{ $cartCount }}</span>
                @endif
            </a>
            <button type="button" @click="mobile = !mobile" class="rounded-full p-2 md:hidden" aria-label="Menu">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
            </button>
        </div>
    </div>

    <div x-show="mobile" x-cloak class="border-t border-[var(--line)] bg-[var(--header-bg)] px-5 py-4 md:hidden">
        <div class="grid gap-3 text-sm font-semibold">
            <a href="{{ route('storefront.shop') }}">Shop</a>
            <a href="{{ route('storefront.shop', ['sort' => 'newest']) }}">New arrivals</a>
            @if($mode?->allowsVendors() && \Illuminate\Support\Facades\Route::has('storefront.vendors.index'))
                <a href="{{ route('storefront.vendors.index') }}">Vendors</a>
            @endif
            @if($mode?->value === 'b2b')
                <a href="{{ route('storefront.trade') }}">Trade program</a>
            @endif
            <a href="{{ route('storefront.page', 'about') }}">Our story</a>
        </div>
    </div>
</header>
