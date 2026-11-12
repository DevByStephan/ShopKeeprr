<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Admin') — {{ nexus_setting('store.name', 'ShopKeeprr') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        :root { --brand: #0f766e; --link: #0f766e; --button-ink: #ffffff; }
        .btn-primary { background: var(--brand) !important; color: #ffffff !important; }
        .text-brand { color: var(--brand); }
    </style>
    @stack('head')
</head>
<body class="admin-shell" x-data="{ sidebar: false, account: false }">
    <div class="admin-sidebar" :class="sidebar ? 'is-open' : ''">
        <div class="flex items-center justify-between px-3">
            <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-2.5">
                <span class="grid h-9 w-9 place-items-center rounded-xl bg-white/10 text-sm font-bold">{{ mb_substr(nexus_setting('store.name', 'ShopKeeprr'), 0, 1) }}</span>
                <span class="font-display text-lg">{{ nexus_setting('store.name', 'ShopKeeprr') }}</span>
            </a>
            <button type="button" class="rounded-lg p-1 text-white/60 lg:hidden" @click="sidebar = false" aria-label="Close menu">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m6 6 12 12M18 6 6 18"/></svg>
            </button>
        </div>

        <nav class="mt-6 max-h-[calc(100vh-10rem)] overflow-y-auto pb-10">
            <div class="admin-nav-label">Overview</div>
            <a href="{{ route('admin.dashboard') }}" class="admin-nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M4 13h6V4H4v9Zm10 7h6v-9h-6v9ZM4 20h6v-4H4v4Zm10-11h6V4h-6v5Z"/></svg> Dashboard
            </a>

            <div class="admin-nav-label">Sell</div>
            <a href="{{ route('admin.orders.index') }}" class="admin-nav-link {{ request()->routeIs('admin.orders.*') ? 'active' : '' }}">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M6 8h12l-1 13H7L6 8Z"/><path d="M9 9V6a3 3 0 0 1 6 0v3"/></svg> Orders
            </a>
            <a href="{{ route('admin.products.index') }}" class="admin-nav-link {{ request()->routeIs('admin.products.*') ? 'active' : '' }}">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="m12 3 8 4.5v9L12 21l-8-4.5v-9L12 3Z"/><path d="m4 7.5 8 4.5 8-4.5M12 12v9"/></svg> Products
            </a>
            <a href="{{ route('admin.categories.index') }}" class="admin-nav-link {{ request()->routeIs('admin.categories.*') ? 'active' : '' }}">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M4 6h16M4 12h10M4 18h7"/></svg> Categories
            </a>
            @if(\Illuminate\Support\Facades\Route::has('admin.vendors.index'))
                <a href="{{ route('admin.vendors.index') }}" class="admin-nav-link {{ request()->routeIs('admin.vendors.*') ? 'active' : '' }}">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M16 20v-1.5a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4V20"/><circle cx="9.5" cy="7" r="3"/><path d="M17 11a3 3 0 1 0-1.5-5.6M21 20v-1.2a3.6 3.6 0 0 0-3-3.5"/></svg> Vendors
                </a>
            @endif
            <a href="{{ route('admin.customers.index') }}" class="admin-nav-link {{ request()->routeIs('admin.customers.*') ? 'active' : '' }}">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><circle cx="12" cy="8" r="3.5"/><path d="M4.5 20c.8-4 3.3-6 7.5-6s6.7 2 7.5 6"/></svg> Customers
            </a>
            <a href="{{ route('admin.subscribers.index') }}" class="admin-nav-link {{ request()->routeIs('admin.subscribers.*') ? 'active' : '' }}">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M4 6h16v12H4z"/><path d="m4 7 8 6 8-6"/></svg> Subscribers
            </a>
            <a href="{{ route('admin.subscriptions.index') }}" class="admin-nav-link {{ request()->routeIs('admin.subscriptions.*') ? 'active' : '' }}">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M20 12a8 8 0 1 1-2.3-5.6M20 4v5h-5"/></svg> Subscriptions
            </a>
            @if(\Illuminate\Support\Facades\Route::has('emailhub.admin.index'))
                <a href="{{ route('emailhub.admin.index') }}" class="admin-nav-link {{ request()->routeIs('emailhub.admin.*') ? 'active' : '' }}">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M4 6h16v12H4z"/><path d="m4 7 8 6 8-6"/><path d="m14 15 3 3M17 15l-3 3"/></svg> Email marketing
                </a>
            @endif
            <a href="{{ route('admin.coupons.index') }}" class="admin-nav-link {{ request()->routeIs('admin.coupons.*') ? 'active' : '' }}">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M4 7a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v3a2 2 0 0 0 0 4v3a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-3a2 2 0 0 0 0-4V7Z"/><path d="M13 5v14"/></svg> Coupons
            </a>

            <div class="admin-nav-label">Operations</div>
            <a href="{{ route('admin.shipping.index') }}" class="admin-nav-link {{ request()->routeIs('admin.shipping.*') ? 'active' : '' }}">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M3 7h11v9H3zM14 11h4l3 3v2h-7z"/><circle cx="7" cy="18" r="1.5"/><circle cx="18" cy="18" r="1.5"/></svg> Shipping
            </a>
            <a href="{{ route('admin.taxes.index') }}" class="admin-nav-link {{ request()->routeIs('admin.taxes.*') ? 'active' : '' }}">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M19 5 5 19"/><circle cx="7" cy="7" r="2"/><circle cx="17" cy="17" r="2"/></svg> Taxes
            </a>
            <a href="{{ route('admin.pages.index') }}" class="admin-nav-link {{ request()->routeIs('admin.pages.*') ? 'active' : '' }}">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M6 3h9l3 3v15H6z"/><path d="M14 3v4h4M9 12h6M9 16h6"/></svg> Pages
            </a>
            <a href="{{ route('admin.reports.index') }}" class="admin-nav-link {{ request()->routeIs('admin.reports.*') ? 'active' : '' }}">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M5 20V10M12 20V4M19 20v-7"/></svg> Reports
            </a>

            <div class="admin-nav-label">Storefront</div>
            <a href="{{ route('admin.builder.index') }}" class="admin-nav-link {{ request()->routeIs('admin.builder.*') ? 'active' : '' }}">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M4 5h7v7H4zM13 5h7v4h-7zM13 11h7v8h-7zM4 14h7v5H4z"/></svg> Storefront builder
            </a>
            <a href="{{ route('admin.theme.index') }}" class="admin-nav-link {{ request()->routeIs('admin.theme.*') ? 'active' : '' }}">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M12 3a9 9 0 1 0 0 18c1.4 0 2-1 2-2 0-1.4-1-1.8-1-3 0-1 .8-2 2-2h2a5 5 0 0 0 5-5c0-3.5-4.5-6-10-6Z"/><circle cx="7.5" cy="10" r="1"/><circle cx="10" cy="6.5" r="1"/><circle cx="15" cy="7" r="1"/></svg> Theme
            </a>
            <a href="{{ route('admin.plugins.index') }}" class="admin-nav-link {{ request()->routeIs('admin.plugins.*') ? 'active' : '' }}">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M9 4h6v3a2 2 0 1 0 4 0h1v6h-3a2 2 0 1 0 0 4v3H9v-3a2 2 0 1 0-4 0H4v-6h3a2 2 0 1 0 0-4H4V4h5Z"/></svg> Plugins
            </a>
            <a href="{{ route('admin.marketplace.index') }}" class="admin-nav-link {{ request()->routeIs('admin.marketplace.*') ? 'active' : '' }}">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M4 7h16v12H4z"/><path d="M4 11h16M8 7V5h8v2M9 14h6"/></svg> Marketplace
            </a>
            @if(\Illuminate\Support\Facades\Route::has('admin.reviews.index'))
                <a href="{{ route('admin.reviews.index') }}" class="admin-nav-link {{ request()->routeIs('admin.reviews.*') ? 'active' : '' }}">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="m12 3 2.7 5.7 6.3.9-4.5 4.3 1.1 6.1-5.6-3-5.6 3 1.1-6.1L3 9.6l6.3-.9L12 3Z"/></svg> Reviews
                </a>
            @endif
            @if(\Illuminate\Support\Facades\Route::has('admin.bundles.index'))
                <a href="{{ route('admin.bundles.index') }}" class="admin-nav-link {{ request()->routeIs('admin.bundles.*') ? 'active' : '' }}">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M4 7h16v12H4z"/><path d="M4 11h16M9 7v12"/></svg> Bundles
                </a>
            @endif
            @if(\Illuminate\Support\Facades\Route::has('admin.loyalty.index'))
                <a href="{{ route('admin.loyalty.index') }}" class="admin-nav-link {{ request()->routeIs('admin.loyalty.*') ? 'active' : '' }}">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="m12 3 2.7 5.7 6.3.9-4.5 4.3 1.1 6.1-5.6-3-5.6 3 1.1-6.1L3 9.6l6.3-.9L12 3Z"/></svg> Loyalty
                </a>
            @endif
            @if(\Illuminate\Support\Facades\Route::has('admin.abandoned.index'))
                <a href="{{ route('admin.abandoned.index') }}" class="admin-nav-link {{ request()->routeIs('admin.abandoned.*') ? 'active' : '' }}">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M6 8h12l-1 13H7L6 8Z"/><path d="M9 9V6a3 3 0 0 1 6 0v3"/></svg> Recovery
                </a>
            @endif
            <a href="{{ route('storefront.home') }}" target="_blank" class="admin-nav-link">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M14 4h6v6M20 4l-9 9"/><path d="M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5"/></svg> View store
            </a>

            <div class="admin-nav-label">System</div>
            <a href="{{ route('admin.updates.index') }}" class="admin-nav-link {{ request()->routeIs('admin.updates.*') ? 'active' : '' }}">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M12 3v12M8 11l4 4 4-4"/><path d="M5 20h14"/></svg> Updates
                <span class="ml-auto rounded-full bg-white/10 px-2 py-0.5 text-[9px] font-bold tracking-wide text-white/60">{{ nexus_demo() ? nexus_demo_label() : 'v'.config('app.version', '1.0.0') }}</span>
            </a>
            <a href="{{ route('admin.audit.index') }}" class="admin-nav-link {{ request()->routeIs('admin.audit.*') ? 'active' : '' }}">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M9 6h11M9 12h11M9 18h11M4 6h.01M4 12h.01M4 18h.01"/></svg> Audit log
            </a>
            <a href="{{ route('admin.settings.index') }}" class="admin-nav-link {{ request()->routeIs('admin.settings.*') ? 'active' : '' }}">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.9.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.9 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.9l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.9.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.9-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.9V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1Z"/></svg> Settings
            </a>
        </nav>

        <div class="absolute inset-x-4 bottom-4 rounded-xl border border-white/10 bg-white/[.04] p-3">
            <div class="truncate text-xs font-semibold">{{ auth()->user()?->name }}</div>
            <div class="truncate text-[10px] text-white/40">{{ auth()->user()?->email }}</div>
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
                    <div class="text-[10px] font-bold uppercase tracking-[.16em] text-[#8a9892]">@yield('eyebrow', 'Admin')</div>
                    <div class="text-sm font-bold text-[#17231f]">@yield('heading', 'Dashboard')</div>
                </div>
            </div>
            <div class="flex items-center gap-2">
                @if(! nexus_store_is_live())
                    <span class="hidden rounded-full bg-amber-50 px-3 py-1.5 text-[11px] font-bold text-amber-700 sm:inline">Store offline</span>
                @else
                    <span class="hidden rounded-full bg-emerald-50 px-3 py-1.5 text-[11px] font-bold text-emerald-700 sm:inline">Store live</span>
                @endif
                <div class="relative" @click.outside="account = false">
                    <button type="button" @click="account = !account" class="grid h-9 w-9 place-items-center rounded-full bg-[#102522] text-xs font-bold text-white">{{ auth()->user()?->initials() }}</button>
                    <div x-show="account" x-cloak class="absolute right-0 mt-2 w-48 overflow-hidden rounded-xl border border-[#e3e9e5] bg-white p-1.5 shadow-xl">
                        <a href="{{ route('storefront.home') }}" class="block rounded-lg px-3 py-2 text-sm hover:bg-[#f3f7f4]">View storefront</a>
                        <a href="{{ route('demo') }}" class="block rounded-lg px-3 py-2 text-sm hover:bg-[#f3f7f4]">Demo guide &amp; credentials</a>
                        <form method="post" action="{{ route('admin.logout') }}">
                            @csrf
                            <button class="block w-full rounded-lg px-3 py-2 text-left text-sm text-rose-600 hover:bg-rose-50">Sign out</button>
                        </form>
                    </div>
                </div>
            </div>
        </header>

        <div class="admin-content">
            @if(nexus_demo())
                <div class="mb-5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                    <div class="font-bold">{{ nexus_demo_label() }} · read-only admin</div>
                    <p class="mt-1">You can inspect every screen, but changes are disabled so the store stays consistent. <a href="{{ route('demo') }}" class="font-bold underline underline-offset-2">Demo guide</a> · <a href="{{ route('storefront.home') }}" class="font-bold underline underline-offset-2">Storefront</a></p>
                </div>
            @endif
            @if(session('demo_error'))
                <div class="mb-5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-900">{{ session('demo_error') }}</div>
            @endif
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
