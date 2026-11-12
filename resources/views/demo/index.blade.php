@extends('theme.layouts.app')

@section('title', 'Live demo — ShopKeeprr')

@section('content')
<div class="container-wide py-12 sm:py-16">
    <div class="mx-auto max-w-5xl">
        <div class="eyebrow"><span class="eyebrow-dot"></span> {{ nexus_demo_label() }}</div>
        <h1 class="display-xl mt-4 max-w-3xl text-balance">Explore ShopKeeprr from both sides.</h1>
        <p class="lede mt-5 max-w-2xl">This is a live ShopKeeprr store backed by demo data. Browse the storefront as a shopper, then sign in to the admin panel with the credentials below. The admin is read-only, so the demo stays consistent for the next visitor.</p>

        <div class="mt-8 grid gap-4 sm:grid-cols-3">
            <a href="{{ route('storefront.home') }}" class="card card-pad">
                <div class="text-[10px] font-bold uppercase tracking-[.16em] text-[var(--muted)]">Storefront</div>
                <div class="mt-2 font-display text-2xl">Shop the demo</div>
                <p class="mt-2 text-sm text-[var(--muted)]">Browse ten products, add to the bag and complete a demo checkout.</p>
                <span class="mt-4 inline-block text-xs font-bold text-[var(--link)]">Open storefront →</span>
            </a>
            <a href="{{ route('admin.login') }}" class="card card-pad">
                <div class="text-[10px] font-bold uppercase tracking-[.16em] text-[var(--muted)]">Admin</div>
                <div class="mt-2 font-display text-2xl">Merchant dashboard</div>
                <p class="mt-2 text-sm text-[var(--muted)]">Orders, products, customers, reports, themes and settings.</p>
                <span class="mt-4 inline-block text-xs font-bold text-[var(--link)]">Open admin →</span>
            </a>
            <a href="#walkthrough" class="card card-pad">
                <div class="text-[10px] font-bold uppercase tracking-[.16em] text-[var(--muted)]">Guide</div>
                <div class="mt-2 font-display text-2xl">Admin walkthrough</div>
                <p class="mt-2 text-sm text-[var(--muted)]">A suggested path through the screens worth showing.</p>
                <span class="mt-4 inline-block text-xs font-bold text-[var(--link)]">Jump to guide →</span>
            </a>
        </div>

        <!--email_off-->
        <div class="mt-6 grid gap-6 lg:grid-cols-2">
            <div class="card card-pad">
                <div class="text-[10px] font-bold uppercase tracking-[.16em] text-[var(--link)]">Administrator</div>
                <h2 class="mt-2 font-display text-3xl">Admin sign-in</h2>
                <p class="mt-2 text-sm text-[var(--muted)]">Use this shared account to inspect the merchant side. It is read-only.</p>
                <dl class="mt-5 space-y-3 text-sm">
                    <div>
                        <dt class="label">Email</dt>
                        <dd class="mt-1 rounded-lg bg-[var(--surface-2)] px-3 py-2 font-mono text-xs">{!! str_replace('@', '&#64;', e(config('nexuscart.demo.admin_email'))) !!}</dd>
                    </div>
                    <div>
                        <dt class="label">Password</dt>
                        <dd class="mt-1 rounded-lg bg-[var(--surface-2)] px-3 py-2 font-mono text-xs">{{ config('nexuscart.demo.admin_password') }}</dd>
                    </div>
                </dl>
                <a href="{{ route('admin.login') }}" class="btn-primary mt-5">Open admin panel</a>
            </div>

            <div class="card card-pad">
                <div class="text-[10px] font-bold uppercase tracking-[.16em] text-[var(--link)]">Shopper</div>
                <h2 class="mt-2 font-display text-3xl">Customer account</h2>
                <p class="mt-2 text-sm text-[var(--muted)]">Optional. Sign in to see the account area and a sample order history.</p>
                <dl class="mt-5 space-y-3 text-sm">
                    <div>
                        <dt class="label">Email</dt>
                        <dd class="mt-1 rounded-lg bg-[var(--surface-2)] px-3 py-2 font-mono text-xs">{!! str_replace('@', '&#64;', e(config('nexuscart.demo.shopper_email'))) !!}</dd>
                    </div>
                    <div>
                        <dt class="label">Password</dt>
                        <dd class="mt-1 rounded-lg bg-[var(--surface-2)] px-3 py-2 font-mono text-xs">{{ config('nexuscart.demo.shopper_password') }}</dd>
                    </div>
                </dl>
                <a href="{{ route('login') }}" class="btn-secondary mt-5">Open shopper sign-in</a>
            </div>
        </div>
        <!--/email_off-->

        <div class="card card-pad mt-6">
            <h2 class="font-display text-2xl">What is locked in this demo</h2>
            <div class="mt-4 grid gap-3 text-sm text-[var(--muted)] sm:grid-cols-2">
                <div class="rounded-xl border border-[var(--line)] p-4"><strong class="text-[var(--ink)]">Admin changes are disabled.</strong> Settings, products, orders, themes, plugins, updates and store-mode changes cannot be saved.</div>
                <div class="rounded-xl border border-[var(--line)] p-4"><strong class="text-[var(--ink)]">Registration is closed.</strong> New customer accounts and password resets are turned off.</div>
                <div class="rounded-xl border border-[var(--line)] p-4"><strong class="text-[var(--ink)]">No real payments.</strong> Checkout uses a demo invoice. No card is charged and no email is sent.</div>
                <div class="rounded-xl border border-[var(--line)] p-4"><strong class="text-[var(--ink)]">Demo data resets.</strong> The operator can restore the original catalog, orders and accounts with one command.</div>
            </div>
        </div>

        <div id="walkthrough" class="card card-pad mt-6">
            <div class="text-[10px] font-bold uppercase tracking-[.16em] text-[var(--link)]">Suggested path</div>
            <h2 class="mt-2 font-display text-3xl">Admin walkthrough</h2>
            <p class="mt-2 text-sm text-[var(--muted)]">Sign in, then open these screens in order. Every page is viewable; only write actions are blocked.</p>
            <div class="mt-5 grid gap-3 sm:grid-cols-2">
                <a href="{{ route('admin.dashboard') }}" class="rounded-xl border border-[var(--line)] p-4 text-sm hover:border-[var(--link)]"><strong>Dashboard</strong><span class="mt-1 block text-[var(--muted)]">Revenue, recent orders and store status.</span></a>
                <a href="{{ route('admin.orders.index') }}" class="rounded-xl border border-[var(--line)] p-4 text-sm hover:border-[var(--link)]"><strong>Orders</strong><span class="mt-1 block text-[var(--muted)]">Paid, pending and failed demo orders.</span></a>
                <a href="{{ route('admin.products.index') }}" class="rounded-xl border border-[var(--line)] p-4 text-sm hover:border-[var(--link)]"><strong>Products</strong><span class="mt-1 block text-[var(--muted)]">Catalog, variants, stock and pricing.</span></a>
                <a href="{{ route('admin.customers.index') }}" class="rounded-xl border border-[var(--line)] p-4 text-sm hover:border-[var(--link)]"><strong>Customers</strong><span class="mt-1 block text-[var(--muted)]">Demo customer records and trade tiers.</span></a>
                <a href="{{ route('admin.reports.index') }}" class="rounded-xl border border-[var(--line)] p-4 text-sm hover:border-[var(--link)]"><strong>Reports</strong><span class="mt-1 block text-[var(--muted)]">Sales summary and CSV export.</span></a>
                <a href="{{ route('admin.settings.index') }}" class="rounded-xl border border-[var(--line)] p-4 text-sm hover:border-[var(--link)]"><strong>Settings</strong><span class="mt-1 block text-[var(--muted)]">Store, checkout, payments and mail configuration.</span></a>
            </div>
        </div>
    </div>
</div>
@endsection