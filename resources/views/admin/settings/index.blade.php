@extends('admin.layouts.app')

@section('title', 'Settings')
@section('eyebrow', 'System')
@section('heading', 'Store settings')

@section('content')
@php
    $section = request('section', 'general');
    $tabs = [
        'general' => 'General',
        'store' => 'Store account',
        'mode' => 'Store model',
        'checkout' => 'Checkout & tax',
        'payments' => 'Payments',
        'mail' => 'Email',
        'b2b' => 'B2B',
        'subscriptions' => 'Subscriptions',
        'marketplace' => 'Marketplace',
    ];
    $modeValue = $settings['store.mode'] ?? null;
    $modeRevision = (int) ($settings['store.mode_revision'] ?? 0);
    $demo = nexus_demo();
    $demoLabel = nexus_demo_label();
    $stripeWebhook = url('/webhooks/stripe');
    $paypalWebhook = url('/webhooks/paypal');
@endphp

<div class="mb-5 flex flex-wrap gap-1 rounded-xl border border-[#e3e9e5] bg-white p-1.5">
    @foreach($tabs as $key => $label)
        <a href="{{ route('admin.settings.index', ['section' => $key]) }}" class="rounded-lg px-3.5 py-2 text-xs font-semibold transition {{ $section === $key ? 'bg-[#102522] text-white' : 'text-[#687771] hover:bg-[#f3f7f4]' }}">{{ $label }}</a>
    @endforeach
</div>

@if($section === 'general')
    <form method="post" action="{{ route('admin.settings.update') }}" enctype="multipart/form-data" class="grid gap-5 xl:grid-cols-[1.5fr_1fr]">
        @csrf
        @method('PATCH')
        <input type="hidden" name="section" value="general">
        <div class="card card-pad">
            <h2 class="font-display text-xl">Store identity</h2>
            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2"><label class="label" for="store_name">Store name</label><input id="store_name" name="store_name" value="{{ $settings['store.name'] }}" class="input" required></div>
                <div class="sm:col-span-2"><label class="label" for="store_tagline">Tagline</label><input id="store_tagline" name="store_tagline" value="{{ $settings['store.tagline'] }}" class="input"></div>
                <div><label class="label" for="store_email">Store email</label><input id="store_email" type="email" name="store_email" value="{{ $settings['store.email'] }}" class="input" required></div>
                <div><label class="label" for="store_phone">Phone</label><input id="store_phone" name="store_phone" value="{{ $settings['store.phone'] }}" class="input"></div>
                <div class="sm:col-span-2"><label class="label" for="store_address">Address</label><textarea id="store_address" name="store_address" rows="2" class="textarea">{{ $settings['store.address'] }}</textarea></div>
                <div><label class="label" for="currency">Currency</label><select id="currency" name="currency" class="select">@foreach(['USD','EUR','GBP','CAD','AUD'] as $currency)<option value="{{ $currency }}" @selected($settings['store.currency'] === $currency)>{{ $currency }}</option>@endforeach</select></div>
                <div><label class="label" for="store_country">Base country</label><input id="store_country" name="store_country" value="{{ $settings['store.country'] }}" maxlength="2" class="input" required></div>
                <div class="sm:col-span-2"><label class="label" for="announcement">Announcement bar</label><input id="announcement" name="announcement" value="{{ $settings['store.announcement'] }}" class="input"></div>
            </div>
        </div>
        <div class="space-y-5">
            <div class="card card-pad">
                <h2 class="font-display text-xl">Brand assets</h2>
                <div class="mt-4">
                    <label class="label" for="logo">Logo</label>
                    <input id="logo" type="file" name="logo" accept="image/*" class="block w-full text-xs file:mr-3 file:rounded-full file:border-0 file:bg-[#0f766e] file:px-4 file:py-2 file:text-xs file:font-bold file:text-white">
                    @if($settings['store.logo'])
                        <img src="{{ asset($settings['store.logo']) }}" alt="Logo" class="mt-3 h-14 w-auto rounded-lg border border-[#e3e9e5] bg-white p-1">
                    @endif
                </div>
                <div class="mt-4">
                    <label class="label" for="footer_text">Footer blurb</label>
                    <textarea id="footer_text" name="footer_text" rows="3" class="textarea">{{ $settings['store.footer_text'] }}</textarea>
                </div>
                <div class="mt-4">
                    <label class="label" for="social_links">Social links</label>
                    <textarea id="social_links" name="social_links" rows="3" class="textarea">{{ collect($settings['store.social_links'] ?? [])->map(fn ($url, $label) => $label.'='.$url)->implode("\n") }}</textarea>
                    <p class="mt-1 text-[11px] text-[#8a9892]">One per line: Instagram=https://…</p>
                </div>
            </div>
            <div class="card card-pad">
                <h2 class="font-display text-xl">Homepage</h2>
                <div class="mt-4 space-y-3">
                    <div><label class="label" for="hero_eyebrow">Eyebrow</label><input id="hero_eyebrow" name="hero_eyebrow" value="{{ $settings['store.hero_eyebrow'] }}" class="input"></div>
                    <div><label class="label" for="hero_title">Headline</label><input id="hero_title" name="hero_title" value="{{ $settings['store.hero_title'] }}" class="input"></div>
                    <div><label class="label" for="hero_subtitle">Subheadline</label><textarea id="hero_subtitle" name="hero_subtitle" rows="3" class="textarea">{{ $settings['store.hero_subtitle'] }}</textarea></div>
                </div>
            </div>
            <button class="btn-primary w-full">Save general settings</button>
        </div>
    </form>

@elseif($section === 'store')
    <div class="grid gap-5 xl:grid-cols-[1.35fr_.65fr]">
        <div class="card card-pad">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <div class="text-[10px] font-bold uppercase tracking-[.14em] text-[#0f766e]">ShopKeeprr Store</div>
                    <h2 class="mt-1 font-display text-2xl">{{ $marketplaceConnection?->account_name ?: 'Connect your Store account' }}</h2>
                    <p class="mt-2 max-w-2xl text-xs leading-5 text-[#687771]">
                        @if($marketplaceConnection)
                            Linked as <strong>{{ $marketplaceConnection->account_email }}</strong>. The marketplace can install plugins this account owns and free plugins from the Store.
                        @else
                            Link an account to install plugins with one click. The Store gives this site a scoped token; your password is never stored in ShopKeeprr.
                        @endif
                    </p>
                </div>
                <span class="badge {{ $marketplaceConnection ? 'badge-emerald' : 'badge-slate' }}">{{ $marketplaceConnection ? 'Connected' : 'Not connected' }}</span>
            </div>

            @if($marketplaceConnection)
                <dl class="mt-5 grid gap-3 text-xs sm:grid-cols-2">
                    <div class="rounded-xl bg-[#f8fbf9] p-3"><dt class="text-[10px] font-bold uppercase tracking-[.12em] text-[#8a9892]">Account</dt><dd class="mt-1 font-semibold">{{ $marketplaceConnection->account_email }}</dd></div>
                    <div class="rounded-xl bg-[#f8fbf9] p-3"><dt class="text-[10px] font-bold uppercase tracking-[.12em] text-[#8a9892]">Site token</dt><dd class="mt-1 font-mono">{{ $marketplaceConnection->maskedToken() }}</dd></div>
                    <div class="rounded-xl bg-[#f8fbf9] p-3"><dt class="text-[10px] font-bold uppercase tracking-[.12em] text-[#8a9892]">Connected</dt><dd class="mt-1 font-semibold">{{ $marketplaceConnection->connected_at?->format('M j, Y') }}</dd></div>
                    <div class="rounded-xl bg-[#f8fbf9] p-3"><dt class="text-[10px] font-bold uppercase tracking-[.12em] text-[#8a9892]">Store</dt><dd class="mt-1 truncate font-mono">{{ $marketplaceConnection->store_url }}</dd></div>
                </dl>
                <div class="mt-5 flex flex-wrap items-center gap-3">
                    <a href="{{ route('admin.marketplace.index') }}" class="btn-primary !px-4 !py-2 text-xs">Open marketplace</a>
                    <form method="post" action="{{ route('admin.marketplace.disconnect') }}" onsubmit="return confirm('Disconnect this store from the ShopKeeprr Store?')">
                        @csrf
                        <button class="text-xs font-bold text-rose-600">Disconnect</button>
                    </form>
                </div>
            @else
                <form method="post" action="{{ route('admin.marketplace.connect') }}" class="mt-5">
                    @csrf
                    <button class="btn-primary">Connect Store account</button>
                </form>
            @endif
        </div>
        <div class="card card-pad !bg-[#f7f4ec] text-xs leading-5 text-[#6a6047]">
            <div class="text-[10px] font-bold uppercase tracking-[.14em] text-[#8a9892]">Why link?</div>
            <p class="mt-2">The Store verifies ownership before it releases a paid package. Linking also lets the marketplace show which plugins you already own, so you do not buy the same plugin twice.</p>
            <p class="mt-2">Disconnecting does not remove installed plugins. They stay on this store and keep working.</p>
        </div>
    </div>

@elseif($section === 'mode')
    <form method="post" action="{{ route('admin.settings.update') }}" class="space-y-5">
        @csrf
        @method('PATCH')
        <input type="hidden" name="section" value="mode">
        <div class="grid gap-4 lg:grid-cols-3">
            @foreach($modes as $mode)
                <label class="relative cursor-pointer rounded-2xl border-2 bg-white p-5 transition {{ $modeValue === $mode->value ? 'border-[#0f766e]' : 'border-[#e3e9e5] hover:border-[#c8d8d1]' }}">
                    <input type="radio" name="mode" value="{{ $mode->value }}" class="absolute right-4 top-4 accent-[#0f766e]" @checked($modeValue === $mode->value)>
                    <div class="grid h-10 w-10 place-items-center rounded-xl bg-[#e7f5f0] text-[#0f766e]">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path d="m12 3 8 4.5v9L12 21l-8-4.5v-9L12 3Z"/></svg>
                    </div>
                    <div class="mt-4 font-display text-xl">{{ $mode->label() }}</div>
                    <p class="mt-1 text-xs leading-5 text-[#687771]">{{ $mode->description() }}</p>
                    <div class="mt-3 text-[10px] font-bold uppercase tracking-[.12em] text-[#0f766e]">{{ $mode->tagline() }}</div>
                </label>
            @endforeach
        </div>
        <div class="card card-pad flex flex-wrap items-center justify-between gap-4">
            <div>
                <h2 class="font-display text-xl">Storefront availability</h2>
                <p class="mt-1 text-xs text-[#687771]">While offline, shoppers see a holding page. Admins can still preview everything.</p>
            </div>
            <label class="flex items-center gap-3 text-sm font-semibold">
                <input type="checkbox" name="live" value="1" class="h-4 w-4 accent-[#0f766e]" @checked($settings['store.live'])> Store is live
            </label>
        </div>
        <div class="card card-pad">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="font-display text-xl">Guided model switch</h2>
                    <p class="mt-1 max-w-2xl text-xs leading-5 text-[#687771]">Preview catalog archives, open orders, subscriptions, seller balances and stale carts before changing models. The switch itself is atomic and records a new configuration revision. Current revision: <strong>{{ $modeRevision }}</strong>.</p>
                </div>
                <a href="{{ route('admin.store-mode.history') }}" class="btn-secondary !px-4 !py-2 text-xs">History</a>
            </div>
            <div class="mt-4 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($modes as $mode)
                    @continue($modeValue === $mode->value)
                    <a href="{{ route('admin.store-mode.preflight', ['mode' => $mode->value, 'live' => $settings['store.live'] ? 1 : 0]) }}" class="rounded-xl border border-[#e3e9e5] bg-[#fafcfb] p-3 transition hover:border-[#0f766e]">
                        <div class="text-sm font-bold">{{ $mode->label() }}</div>
                        <div class="mt-1 text-[11px] leading-5 text-[#687771]">Preview what changes before switching.</div>
                    </a>
                @endforeach
                @if($modeValue === null)
                    <div class="rounded-xl border border-dashed border-[#c8d8d1] p-3 text-xs leading-5 text-[#687771]">Choose and save a first model above. The guided switch is for changes after the store is live.</div>
                @endif
            </div>
            <a href="{{ route('admin.store-mode.integrity') }}" class="mt-4 inline-block text-xs font-bold text-[#0f766e]">Run the catalog integrity audit →</a>
        </div>

        <button class="btn-primary">Save store model</button>
    </form>

@elseif($section === 'checkout')
    <form method="post" action="{{ route('admin.settings.update') }}" class="grid gap-5 xl:grid-cols-2">
        @csrf
        @method('PATCH')
        <input type="hidden" name="section" value="checkout">
        <div class="card card-pad">
            <h2 class="font-display text-xl">Checkout</h2>
            <div class="mt-4 space-y-4">
                <label class="flex items-center gap-2.5 text-sm"><input type="checkbox" name="guest_checkout" value="1" class="accent-[#0f766e]" @checked($settings['checkout.guest_checkout'])> Allow guest checkout</label>
                <label class="flex items-center gap-2.5 text-sm"><input type="checkbox" name="require_phone" value="1" class="accent-[#0f766e]" @checked($settings['checkout.require_phone'])> Require a phone number</label>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div><label class="label" for="min_order_total">Minimum order total</label><input id="min_order_total" type="number" step="0.01" min="0" name="min_order_total" value="{{ $settings['checkout.min_order_total'] }}" class="input"></div>
                    <div><label class="label" for="order_prefix">Order number prefix</label><input id="order_prefix" name="order_prefix" value="{{ $settings['checkout.order_prefix'] }}" maxlength="10" class="input" required></div>
                </div>
                <div class="mt-4 border-t border-[#e3e9e5] pt-4">
                    <label class="label" for="abandoned_after_hours">Cancel unpaid orders after (hours)</label>
                    <input id="abandoned_after_hours" type="number" min="1" max="720" name="abandoned_after_hours" value="{{ $settings['orders.abandoned_after_hours'] }}" class="input">
                    <p class="mt-1 text-[11px]">Abandoned orders are cancelled by the hourly maintenance task. Inventory and coupons are returned automatically.</p>
                    <label class="label mt-4" for="abandoned_retention_days">Keep abandoned orders on the order screen for (days)</label>
                    <input id="abandoned_retention_days" type="number" min="1" max="365" name="abandoned_retention_days" value="{{ $settings['orders.abandoned_retention_days'] }}" class="input">
                    <p class="mt-1 text-[11px]">Older unpaid orders are hidden from the order list (they stay in the database). Tick “Show abandoned orders” to see them. Paid and refunded orders are always listed.</p>
                </div>
            </div>
        </div>
        <div class="card card-pad">
            <h2 class="font-display text-xl">Tax</h2>
            <div class="mt-4 space-y-4">
                <label class="flex items-center gap-2.5 text-sm"><input type="checkbox" name="prices_include_tax" value="1" class="accent-[#0f766e]" @checked($settings['tax.prices_include_tax'])> Product prices include tax</label>
                <label class="flex items-center gap-2.5 text-sm"><input type="checkbox" name="shipping_taxable" value="1" class="accent-[#0f766e]" @checked($settings['tax.shipping_taxable'])> Shipping is taxable</label>
                <p class="text-xs leading-5 text-[#8a9892]">Manage rates under <a href="{{ route('admin.taxes.index') }}" class="font-bold text-[#0f766e]">Taxes</a>.</p>
            </div>
        </div>
        <div class="xl:col-span-2"><button class="btn-primary">Save checkout settings</button></div>
    </form>

@elseif($section === 'payments')
    <form method="post" action="{{ route('admin.settings.update') }}" class="space-y-5">
        @csrf
        @method('PATCH')
        <input type="hidden" name="section" value="payments">
        @if($demo)
            <div class="flex flex-wrap items-center gap-3 rounded-2xl border border-[#f1dfb5] bg-[#fffaf0] px-4 py-3 text-xs leading-5 text-[#7a5a12]">
                <span class="rounded-full bg-[#f59e0b] px-2.5 py-1 text-[10px] font-black uppercase tracking-[.12em] text-white">Demo</span>
                <span><strong>{{ $demoLabel }}:</strong> payment gateways are read-only. You can inspect the Stripe and PayPal configuration, but the fields cannot be changed on this demo.</span>
            </div>
        @endif
        <div class="grid gap-5 xl:grid-cols-2">
            <div class="card card-pad">
                <div class="flex items-center justify-between">
                    <h2 class="font-display text-xl">Stripe</h2>
                    <label class="flex items-center gap-2 text-xs font-semibold"><input type="checkbox" name="stripe_enabled" value="1" class="accent-[#0f766e]" @checked($settings['payments.stripe.enabled']) @disabled($demo)> Enabled</label>
                </div>
                <div class="mt-4 space-y-3">
                    <div><label class="label" for="stripe_mode">Mode</label><select id="stripe_mode" name="stripe_mode" class="select" @disabled($demo)><option value="test" @selected($settings['payments.stripe.mode'] === 'test')>Test / sandbox</option><option value="live" @selected($settings['payments.stripe.mode'] === 'live')>Live</option></select></div>
                    <div><label class="label" for="stripe_publishable_key">Publishable key</label><input id="stripe_publishable_key" name="stripe_publishable_key" value="{{ $settings['payments.stripe.publishable_key'] }}" class="input font-mono text-xs" @disabled($demo)></div>
                    <div><label class="label" for="stripe_secret_key">Secret key</label><input id="stripe_secret_key" type="password" name="stripe_secret_key" class="input font-mono text-xs" placeholder="{{ $demo ? 'Hidden in this demo' : 'Leave blank to keep current' }}" @disabled($demo)><p class="mt-1 text-[11px] text-[#8a9892]">Encrypted at rest. Never shown again.</p></div>
                    <div><label class="label" for="stripe_webhook_secret">Webhook signing secret</label><input id="stripe_webhook_secret" type="password" name="stripe_webhook_secret" class="input font-mono text-xs" placeholder="{{ $demo ? 'Hidden in this demo' : 'Leave blank to keep current' }}" @disabled($demo)></div>
                    <p class="text-[11px] leading-5 text-[#8a9892]">Webhook endpoint: <code class="break-all rounded bg-[#f5f8f6] px-1">{{ url('/webhooks/stripe') }}</code></p>
                </div>
            </div>
            <div class="card card-pad">
                <div class="flex items-center justify-between">
                    <h2 class="font-display text-xl">PayPal</h2>
                    <label class="flex items-center gap-2 text-xs font-semibold"><input type="checkbox" name="paypal_enabled" value="1" class="accent-[#0f766e]" @checked($settings['payments.paypal.enabled']) @disabled($demo)> Enabled</label>
                </div>
                <div class="mt-4 space-y-3">
                    <div><label class="label" for="paypal_mode">Mode</label><select id="paypal_mode" name="paypal_mode" class="select" @disabled($demo)><option value="sandbox" @selected($settings['payments.paypal.mode'] === 'sandbox')>Sandbox</option><option value="live" @selected($settings['payments.paypal.mode'] === 'live')>Live</option></select></div>
                    <div><label class="label" for="paypal_client_id">Client ID</label><input id="paypal_client_id" name="paypal_client_id" value="{{ $settings['payments.paypal.client_id'] }}" class="input font-mono text-xs" @disabled($demo)></div>
                    <div><label class="label" for="paypal_client_secret">Client secret</label><input id="paypal_client_secret" type="password" name="paypal_client_secret" class="input font-mono text-xs" placeholder="{{ $demo ? 'Hidden in this demo' : 'Leave blank to keep current' }}" @disabled($demo)></div>
                    <div><label class="label" for="paypal_webhook_id">Webhook ID</label><input id="paypal_webhook_id" type="password" name="paypal_webhook_id" class="input font-mono text-xs" placeholder="{{ $demo ? 'Hidden in this demo' : 'Leave blank to keep current' }}" @disabled($demo)></div>
                    <p class="text-[11px] leading-5 text-[#8a9892]">Webhook endpoint: <code class="break-all rounded bg-[#f5f8f6] px-1">{{ url('/webhooks/paypal') }}</code></p>
                </div>
            </div>
            <div class="card card-pad xl:col-span-2">
                <div class="flex items-center justify-between">
                    <div><h2 class="font-display text-xl">Pay by invoice</h2><p class="text-xs text-[#8a9892]">Manual payment method for bank transfers and purchase orders.</p></div>
                    <label class="flex items-center gap-2 text-xs font-semibold"><input type="checkbox" name="manual_enabled" value="1" class="accent-[#0f766e]" @checked($settings['payments.manual.enabled'])> Enabled</label>
                </div>
                <textarea name="manual_instructions" rows="2" class="textarea mt-3">{{ $settings['payments.manual.instructions'] }}</textarea>
            </div>
        </div>
        <div class="card card-pad">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="font-display text-xl">Webhook endpoints</h2>
                <span class="text-[11px] font-semibold text-[#8a9892]">Must be publicly reachable over HTTPS</span>
            </div>
            <p class="mt-1 max-w-3xl text-xs leading-5 text-[#687771]">Paste these exact URLs into the Stripe and PayPal dashboards. nexusCart verifies every delivery before processing it, so unsigned or replayed events are rejected.</p>
            <div class="mt-4 grid gap-4 lg:grid-cols-2">
                <div class="rounded-xl border border-[#e3e9e5] bg-[#f9fbfa] p-4">
                    <div class="flex items-center justify-between gap-2">
                        <h3 class="font-display text-lg">Stripe</h3>
                        <span class="rounded-full px-2.5 py-1 text-[10px] font-bold {{ $settings['payments.stripe.webhook_secret'] !== '' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}">
                            {{ $settings['payments.stripe.webhook_secret'] !== '' ? 'Signing secret saved' : 'Signing secret missing' }}
                        </span>
                    </div>
                    <div class="mt-3 flex items-start gap-2">
                        <code class="min-w-0 flex-1 break-all rounded-lg bg-white px-3 py-2 text-[11px] text-[#17231f]">{{ $stripeWebhook }}</code>
                        <span x-data="{ copied: false }">
                            <button type="button" class="rounded-lg border border-[#d7e2dc] bg-white px-3 py-2 text-[11px] font-bold" @click="navigator.clipboard.writeText(@js($stripeWebhook)); copied = true; setTimeout(() => copied = false, 1500)" x-text="copied ? 'Copied' : 'Copy'">Copy</button>
                        </span>
                    </div>
                    <p class="mt-3 text-[11px] leading-5 text-[#687771]"><strong>Events:</strong> checkout.session.completed, invoice.paid, invoice.payment_failed, customer.subscription.deleted, charge.refunded</p>
                    <p class="mt-2 text-[11px] leading-5 text-[#8a9892]">Add this endpoint in Stripe → Developers → Webhooks, then paste its signing secret into the Stripe webhook field above.</p>
                </div>
                <div class="rounded-xl border border-[#e3e9e5] bg-[#f9fbfa] p-4">
                    <div class="flex items-center justify-between gap-2">
                        <h3 class="font-display text-lg">PayPal</h3>
                        <span class="rounded-full px-2.5 py-1 text-[10px] font-bold {{ $settings['payments.paypal.webhook_id'] !== '' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}">
                            {{ $settings['payments.paypal.webhook_id'] !== '' ? 'Webhook ID saved' : 'Webhook ID missing' }}
                        </span>
                    </div>
                    <div class="mt-3 flex items-start gap-2">
                        <code class="min-w-0 flex-1 break-all rounded-lg bg-white px-3 py-2 text-[11px] text-[#17231f]">{{ $paypalWebhook }}</code>
                        <span x-data="{ copied: false }">
                            <button type="button" class="rounded-lg border border-[#d7e2dc] bg-white px-3 py-2 text-[11px] font-bold" @click="navigator.clipboard.writeText(@js($paypalWebhook)); copied = true; setTimeout(() => copied = false, 1500)" x-text="copied ? 'Copied' : 'Copy'">Copy</button>
                        </span>
                    </div>
                    <p class="mt-3 text-[11px] leading-5 text-[#687771]"><strong>Events:</strong> BILLING.SUBSCRIPTION.ACTIVATED, BILLING.SUBSCRIPTION.CANCELLED, BILLING.SUBSCRIPTION.SUSPENDED, BILLING.SUBSCRIPTION.PAYMENT.FAILED, PAYMENT.SALE.COMPLETED, PAYMENT.CAPTURE.COMPLETED, PAYMENT.CAPTURE.DENIED</p>
                    <p class="mt-2 text-[11px] leading-5 text-[#8a9892]">Create the webhook in PayPal → Apps &amp; Credentials → Webhooks, then paste its ID into the PayPal webhook field above.</p>
                </div>
            </div>
        </div>
        @if($demo)
            <div class="card card-pad border border-[#f1dfb5] bg-[#fffdf7]">
                <div class="flex flex-wrap items-center gap-2">
                    <h2 class="font-display text-xl">Payment gateways are read-only</h2>
                    <span class="rounded-full bg-[#f59e0b]/15 px-2.5 py-1 text-[10px] font-black uppercase tracking-[.12em] text-[#a16207]">{{ $demoLabel }}</span>
                </div>
                <p class="mt-2 max-w-3xl text-xs leading-5 text-[#687771]">
                    This is the {{ $demoLabel }} preview. Stripe and PayPal are preconfigured so you can walk through the full checkout, but their credentials are locked and cannot be changed here. In your own copy every field on this page is editable: add your own API keys, switch between test and live mode, and enable either gateway. The invoice option above stays editable so you can try it.
                </p>
            </div>
        @endif
        <button class="btn-primary">{{ $demo ? 'Save invoice settings' : 'Save payment settings' }}</button>
    </form>

@elseif($section === 'mail')
    <form method="post" action="{{ route('admin.settings.update') }}" class="grid gap-5 xl:grid-cols-2">
        @csrf
        @method('PATCH')
        <input type="hidden" name="section" value="mail">
        <div class="card card-pad">
            <h2 class="font-display text-xl">Outgoing mail</h2>
            <div class="mt-4 space-y-3">
                <div><label class="label" for="mail_mailer">Mailer</label><select id="mail_mailer" name="mail_mailer" class="select">@foreach(['log' => 'Log (development)', 'smtp' => 'SMTP', 'sendmail' => 'Sendmail'] as $value => $label)<option value="{{ $value }}" @selected($settings['mail.mailer'] === $value)>{{ $label }}</option>@endforeach</select></div>
                <div class="grid grid-cols-[1fr_120px] gap-3">
                    <div><label class="label" for="mail_host">Host</label><input id="mail_host" name="mail_host" value="{{ $settings['mail.host'] }}" class="input"></div>
                    <div><label class="label" for="mail_port">Port</label><input id="mail_port" type="number" name="mail_port" value="{{ $settings['mail.port'] }}" class="input"></div>
                </div>
                <div><label class="label" for="mail_username">Username</label><input id="mail_username" name="mail_username" value="{{ $settings['mail.username'] }}" class="input"></div>
                <div><label class="label" for="mail_password">Password</label><input id="mail_password" type="password" name="mail_password" class="input" placeholder="Leave blank to keep current"></div>
                <div><label class="label" for="mail_encryption">Encryption</label><select id="mail_encryption" name="mail_encryption" class="select">@foreach(['tls' => 'TLS', 'ssl' => 'SSL', 'none' => 'None'] as $value => $label)<option value="{{ $value }}" @selected($settings['mail.encryption'] === $value)>{{ $label }}</option>@endforeach</select></div>
            </div>
        </div>
        <div class="space-y-5">
            <div class="card card-pad">
                <h2 class="font-display text-xl">Sender</h2>
                <div class="mt-4 space-y-3">
                    <div><label class="label" for="mail_from_address">From address</label><input id="mail_from_address" type="email" name="mail_from_address" value="{{ $settings['mail.from_address'] }}" class="input"></div>
                    <div><label class="label" for="mail_from_name">From name</label><input id="mail_from_name" name="mail_from_name" value="{{ $settings['mail.from_name'] }}" class="input"></div>
                </div>
            </div>
            <button class="btn-primary w-full">Save email settings</button>
        </div>
    </form>

@elseif($section === 'b2b')
    <form method="post" action="{{ route('admin.settings.update') }}" class="grid gap-5 xl:grid-cols-2">
        @csrf
        @method('PATCH')
        <input type="hidden" name="section" value="b2b">
        <div class="card card-pad">
            <h2 class="font-display text-xl">Wholesale rules</h2>
            <div class="mt-4 space-y-4">
                <label class="flex items-center gap-2.5 text-sm"><input type="checkbox" name="hide_prices_guests" value="1" class="accent-[#0f766e]" @checked($settings['b2b.hide_prices_guests'])> Hide prices from signed-out visitors</label>
                <label class="flex items-center gap-2.5 text-sm"><input type="checkbox" name="require_approval" value="1" class="accent-[#0f766e]" @checked($settings['b2b.require_approval'])> Require approval for trade accounts</label>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div><label class="label" for="b2b_min_order_total">Minimum order total</label><input id="b2b_min_order_total" type="number" step="0.01" min="0" name="b2b_min_order_total" value="{{ $settings['b2b.min_order_total'] }}" class="input"></div>
                    <div><label class="label" for="b2b_default_tier">Default tier name</label><input id="b2b_default_tier" name="b2b_default_tier" value="{{ $settings['b2b.default_tier'] }}" class="input"></div>
                </div>
            </div>
        </div>
        <div class="card card-pad text-xs leading-5 text-[#687771]">
            <div class="text-[10px] font-bold uppercase tracking-[.14em] text-[#8a9892]">How trade pricing is applied</div>
            <p class="mt-2">Approved customers are assigned a tier in their customer profile. Product price breaks override the tier's default percentage discount. Minimum order quantities are enforced in the cart and again when the order is placed.</p>
            <a href="{{ route('admin.customers.index', ['wholesale_status' => 'pending']) }}" class="mt-3 inline-block font-bold text-[#0f766e]">Review pending applications →</a>
        </div>
        <div class="xl:col-span-2"><button class="btn-primary">Save B2B settings</button></div>
    </form>

@elseif($section === 'marketplace')
    <form method="post" action="{{ route('admin.settings.update') }}" class="grid gap-5 xl:grid-cols-2">
        @csrf
        @method('PATCH')
        <input type="hidden" name="section" value="marketplace">
        <div class="card card-pad">
            <h2 class="font-display text-xl">Platform commission</h2>
            <p class="mt-1 text-xs leading-5 text-[#687771]">This rule applies to every seller that inherits the platform setting. Commission is taken from the item subtotal after discount; shipping and tax stay with the platform.</p>
            <div class="mt-4 grid gap-3 sm:grid-cols-3">
                <div>
                    <label class="label" for="commission_type">Rule</label>
                    <select id="commission_type" name="commission_type" class="select">
                        @foreach(['percentage' => 'Percentage', 'fixed' => 'Fixed per order', 'combined' => 'Percentage + fixed'] as $value => $label)
                            <option value="{{ $value }}" @selected($settings['marketplace.commission_type'] === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div><label class="label" for="commission_percent">Percent (%)</label><input id="commission_percent" type="number" step="0.01" min="0" max="100" name="commission_percent" value="{{ $settings['marketplace.commission_percent'] }}" class="input"></div>
                <div><label class="label" for="commission_fixed">Fixed amount</label><input id="commission_fixed" type="number" step="0.01" min="0" name="commission_fixed" value="{{ $settings['marketplace.commission_fixed'] }}" class="input"></div>
            </div>
            <div class="mt-4 rounded-lg bg-[#f5f8f6] p-3 text-[11px] leading-5 text-[#687771]">
                Examples at {{ nexus_money(100) }}: percentage 10% = {{ nexus_money(10) }}; fixed {{ nexus_money(5) }} = {{ nexus_money(5) }}; combined = {{ nexus_money(15) }}. A fixed fee is capped at the seller's subtotal so a payout can never go negative.
            </div>
        </div>
        <div class="card card-pad">
            <h2 class="font-display text-xl">Payouts</h2>
            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                <div><label class="label" for="payout_hold_days">Hold period (days)</label><input id="payout_hold_days" type="number" min="0" max="365" name="payout_hold_days" value="{{ $settings['marketplace.payout_hold_days'] }}" class="input"><p class="mt-1 text-[11px] text-[#8a9892]">Time a seller balance stays refundable before a PayPal or manual payout.</p></div>
                <div><label class="label" for="minimum_payout">Minimum payout</label><input id="minimum_payout" type="number" step="0.01" min="0" name="minimum_payout" value="{{ $settings['marketplace.minimum_payout'] }}" class="input"></div>
            </div>
            <label class="mt-4 flex items-start gap-2.5 text-sm"><input type="checkbox" name="auto_payout" value="1" class="mt-0.5 accent-[#0f766e]" @checked($settings['marketplace.auto_payout'])> <span>Send Stripe Connect transfers automatically when an order is paid<p class="mt-0.5 text-[11px] text-[#8a9892]">Transfers can be reversed automatically if the order is refunded. Turn this off to review every transfer first.</p></span></label>
            <a href="{{ route('admin.vendors.index') }}" class="mt-4 inline-block text-xs font-bold text-[#0f766e]">Open the seller payout queue →</a>
        </div>
        <div class="card card-pad xl:col-span-2 !bg-[#f7f4ec] text-xs leading-5 text-[#6a6047]">
            <div class="text-[10px] font-bold uppercase tracking-[.14em] text-[#8a9892]">Payment provider rules</div>
            <p class="mt-2"><strong>Stripe Connect:</strong> each seller completes Stripe-hosted onboarding. ShopKeeprr uses separate charges and transfers, the supported pattern when one cart contains several sellers. Stripe's fee schedule and the platform's Connect agreement apply.</p>
            <p class="mt-2"><strong>PayPal:</strong> a standard PayPal checkout cannot split one payment between receivers. Orders are collected on the store account and sellers are paid through the PayPal Payouts API after the hold period, subject to PayPal's approval of the Payouts capability. Never promise instant PayPal splits that PayPal does not permit.</p>
        </div>
        <div class="xl:col-span-2"><button class="btn-primary">Save marketplace settings</button></div>
    </form>

@elseif($section === 'subscriptions')
    <form method="post" action="{{ route('admin.settings.update') }}" class="grid gap-5 xl:grid-cols-2">
        @csrf
        @method('PATCH')
        <input type="hidden" name="section" value="subscriptions">
        <div class="card card-pad">
            <h2 class="font-display text-xl">Renewals</h2>
            <div class="mt-4 space-y-4">
                <label class="flex items-center gap-2.5 text-sm"><input type="checkbox" name="auto_renew" value="1" class="accent-[#0f766e]" @checked($settings['subscriptions.auto_renew'])> Attempt automatic renewals</label>
                <div><label class="label" for="grace_days">Grace period (days)</label><input id="grace_days" type="number" min="0" max="30" name="grace_days" value="{{ $settings['subscriptions.grace_days'] }}" class="input"><p class="mt-1 text-[11px] text-[#8a9892]">How long access continues after a failed payment.</p></div>
            </div>
        </div>
        <div class="card card-pad text-xs leading-5 text-[#687771]">
            <div class="text-[10px] font-bold uppercase tracking-[.14em] text-[#8a9892]">Requirements</div>
            <p class="mt-2">Subscription mode requires Stripe (recommended) or PayPal to be enabled. Add plans to products from the product editor. Renewals are driven by the payment gateways' webhooks.</p>
            <a href="{{ route('admin.products.index') }}" class="mt-3 inline-block font-bold text-[#0f766e]">Manage product plans →</a>
        </div>
        <div class="xl:col-span-2"><button class="btn-primary">Save subscription settings</button></div>
    </form>
@endif
@endsection
