<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Opening soon — {{ nexus_setting('store.name', 'ShopKeeprr') }}</title>
    @vite(['resources/css/app.css'])
</head>
<body class="grid min-h-screen place-items-center bg-[var(--surface)] px-5 text-[var(--ink)]">
    <div class="w-full max-w-lg rounded-3xl border border-[var(--line)] bg-white p-9 text-center shadow-xl">
        <div class="mx-auto grid h-12 w-12 place-items-center rounded-2xl bg-[var(--brand)] text-lg font-bold text-white">{{ mb_substr(nexus_setting('store.name', 'ShopKeeprr'), 0, 1) }}</div>
        <div class="mt-6 text-[10px] font-bold uppercase tracking-[.18em] text-[var(--link)]">Opening soon</div>
        <h1 class="mt-3 font-display text-4xl tracking-tight">{{ nexus_setting('store.name', 'ShopKeeprr') }}</h1>
        <p class="mt-4 text-sm leading-6 text-[var(--muted)]">The storefront is being prepared. If you are the store owner, sign in to the admin panel to choose a store model and take the shop live.</p>
        <a href="{{ url('/admin/login') }}" class="btn-primary mt-7">Admin sign in</a>
    </div>
</body>
</html>
