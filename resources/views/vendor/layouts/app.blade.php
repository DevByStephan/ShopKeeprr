<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Seller') — {{ $currentVendor?->name ?? nexus_setting('store.name', 'ShopKeeprr') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        :root { --brand: #0f766e; --link: #0f766e; --button-ink: #ffffff; }
        .btn-primary { background: var(--brand) !important; color: #ffffff !important; }
    </style>
    @stack('head')
</head>
<body class="admin-shell" x-data="{ sidebar: false }">
    <div class="admin-sidebar" :class="sidebar ? 'is-open' : ''">
        <div class="flex items-center justify-between px-3">
            <a href="{{ route('vendor.dashboard') }}" class="flex items-center gap-2.5">
                <span class="grid h-9 w-9 place-items-center rounded-xl bg-white/10 text-sm font-bold">{{ $currentVendor?->initials() }}</span>
                <span class="min-w-0">
                    <span class="block truncate font-display text-base leading-tight">{{ $currentVendor?->name }}</span>
                    <span class="block text-[10px] uppercase tracking-[.14em] text-white/40">Seller portal</span>
                </span>
            </a>
            <button type="button" class="rounded-lg p-1 text-white/60 lg:hidden" @click="sidebar = false" aria-label="Close menu">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m6 6 12 12M18 6 6 18"/></svg>
            </button>
        </div>

        <nav class="mt-6 max-h-[calc(100vh-10rem)] overflow-y-auto pb-10">
            <div class="admin-nav-label">Overview</div>
            <a href="{{ route('vendor.dashboard') }}" class="admin-nav-link {{ request()->routeIs('vendor.dashboard') ? 'active' : '' }}">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M4 13h6V4H4v9Zm10 7h6v-9h-6v9ZM4 20h6v-4H4v4Zm10-11h6V4h-6v5Z"/></svg> Dashboard
            </a>

            <div class="admin-nav-label">Catalog</div>
            <a href="{{ route('vendor.products.index') }}" class="admin-nav-link {{ request()->routeIs('vendor.products.*') ? 'active' : '' }}">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="m12 3 8 4.5v9L12 21l-8-4.5v-9L12 3Z"/><path d="m4 7.5 8 4.5 8-4.5M12 12v9"/></svg> Products
            </a>
            @if(nexus_mode()?->value !== 'multivendor')
                <p class="mx-3 mt-2 rounded-lg border border-amber-400/20 bg-amber-400/10 px-3 py-2 text-[10px] leading-4 text-amber-100/80">New listings are paused while the store runs in {{ nexus_mode()?->label() ?? 'another' }} mode. Historical orders and payouts remain available.</p>
            @endif

            <div class="admin-nav-label">Sales</div>
            <a href="{{ route('vendor.orders.index') }}" class="admin-nav-link {{ request()->routeIs('vendor.orders.*') ? 'active' : '' }}">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M6 8h12l-1 13H7L6 8Z"/><path d="M9 9V6a3 3 0 0 1 6 0v3"/></svg> Orders
            </a>
            <a href="{{ route('vendor.payouts.index') }}" class="admin-nav-link {{ request()->routeIs('vendor.payouts.*') ? 'active' : '' }}">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M3 7h18v11H3z"/><path d="M3 11h18M7 15h3"/></svg> Payouts
            </a>

            <div class="admin-nav-label">Store</div>
            <a href="{{ route('storefront.home') }}" target="_blank" class="admin-nav-link">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M14 4h6v6M20 4l-9 9"/><path d="M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5"/></svg> View store
            </a>
        </nav>

        <div class="absolute inset-x-4 bottom-4 rounded-xl border border-white/10 bg-white/[.04] p-3">
            <div class="truncate text-xs font-semibold">{{ auth()->user()?->name }}</div>
            <div class="truncate text-[10px] text-white/40">{{ auth()->user()?->email }}</div>
            <form method="post" action="{{ route('logout') }}" class="mt-2">
                @csrf
                <button class="text-[10px] font-bold uppercase tracking-[.12em] text-white/50 hover:text-white">Sign out</button>
            </form>
        </div>
    </div>

    <div x-show="sidebar" x-cloak class="fixed inset-0 z-30 bg-black/40 lg:hidden" @click="sidebar = false"></div>

    <div class="admin-main">
        <header class="admin-topbar">
            <div class="flex items-center gap-3">
                <button type="button" class="rounded-lg border border-[#e3e9e5] p-2 lg:hidden" @click="sidebar = true" aria-label="Open menu">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
                </button>
                <div>
                    <div class="text-[10px] font-bold uppercase tracking-[.16em] text-[#8a9892]">@yield('eyebrow', 'Seller')</div>
                    <div class="text-sm font-bold text-[#17231f]">@yield('heading', 'Dashboard')</div>
                </div>
            </div>
            <div class="flex items-center gap-2">
                @if($currentVendor?->payout_method === 'stripe')
                    <span class="hidden rounded-full bg-emerald-50 px-3 py-1.5 text-[11px] font-bold text-emerald-700 sm:inline">Stripe {{ $currentVendor?->stripe_payouts_enabled ? 'connected' : 'pending' }}</span>
                @else
                    <span class="hidden rounded-full bg-amber-50 px-3 py-1.5 text-[11px] font-bold text-amber-700 sm:inline">{{ $currentVendor?->payoutMethodLabel() }}</span>
                @endif
                <a href="{{ route('vendor.products.create') }}" class="btn-primary !px-4 !py-2 text-xs">+ Product</a>
            </div>
        </header>

        <div class="admin-content">
            @if(session('status'))
                <div class="mb-5 flex items-start gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-900">
                    <svg class="mt-0.5 h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20 7 9.5 17.5 4 12"/></svg>
                    <span>{{ session('status') }}</span>
                </div>
            @endif
            @if($errors->any())
                <div class="mb-5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900">
                    <div class="font-bold">Please check the form.</div>
                    <ul class="mt-1 list-disc space-y-0.5 pl-5">
                        @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                    </ul>
                </div>
            @endif

            @yield('content')
        </div>
    </div>
    @stack('scripts')
</body>
</html>
