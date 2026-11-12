<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin sign in — {{ nexus_setting('store.name', 'ShopKeeprr') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="grid min-h-screen bg-[#102522] lg:grid-cols-2">
    <div class="hidden flex-col justify-between p-12 text-white lg:flex">
        <div class="flex items-center gap-3">
            <span class="grid h-10 w-10 place-items-center rounded-xl bg-white/10 font-bold">{{ mb_substr(nexus_setting('store.name', 'ShopKeeprr'), 0, 1) }}</span>
            <span class="font-display text-xl">{{ nexus_setting('store.name', 'ShopKeeprr') }}</span>
        </div>
        <div class="max-w-md">
            <div class="text-[10px] font-bold uppercase tracking-[.2em] text-white/40">Merchant dashboard</div>
            <h1 class="mt-4 font-display text-5xl leading-[1.05] tracking-tight">Everything your store does, in one place.</h1>
            <p class="mt-5 text-sm leading-7 text-white/50">Orders, customers, subscriptions, pricing tiers and the storefront — without a dozen plugins holding it together.</p>
        </div>
        <div class="text-[11px] text-white/25">© {{ date('Y') }} {{ nexus_setting('store.name') }} · Powered by ShopKeeprr</div>
    </div>
    <div class="grid place-items-center bg-[#f6f3ec] px-5 py-12">
        <div class="w-full max-w-md">
            <div class="mb-8 flex items-center gap-3 lg:hidden">
                <span class="grid h-10 w-10 place-items-center rounded-xl bg-[#102522] font-bold text-white">{{ mb_substr(nexus_setting('store.name', 'ShopKeeprr'), 0, 1) }}</span>
                <span class="font-display text-xl">{{ nexus_setting('store.name', 'ShopKeeprr') }}</span>
            </div>
            <div class="rounded-3xl border border-[#e3e9e5] bg-white p-7 shadow-sm sm:p-9">
                <div class="text-[10px] font-bold uppercase tracking-[.18em] text-[#0f766e]">Secure area</div>
                <h1 class="mt-2 font-display text-3xl tracking-tight">Admin sign in</h1>
                <p class="mt-2 text-sm text-[#687771]">Use the administrator account created during installation.</p>
                @if(nexus_demo())
                    <!--email_off-->
                    <div class="mt-5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                        <div class="font-bold">Live demo access</div>
                        <div class="mt-1">Email: <code class="font-mono text-xs">{!! str_replace('@', '&#64;', e(config('nexuscart.demo.admin_email'))) !!}</code></div>
                        <div>Password: <code class="font-mono text-xs">{{ config('nexuscart.demo.admin_password') }}</code></div>
                        <div class="mt-2 text-xs">The dashboard is read-only. <a href="{{ route('demo') }}" class="font-bold underline underline-offset-2">Demo guide &amp; credentials</a></div>
                    </div>
                    <!--/email_off-->
                @endif
                @if($errors->any())
                    <div class="mt-5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900">
                        @foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach
                    </div>
                @endif
                <form method="post" action="{{ route('admin.login.store') }}" class="mt-6 space-y-4">
                    @csrf
                    <div>
                        <label class="label" for="email">Email address</label>
                        <input id="email" type="email" name="email" value="{{ old('email') }}" class="input" required autofocus autocomplete="email">
                    </div>
                    <div>
                        <label class="label" for="password">Password</label>
                        <input id="password" type="password" name="password" class="input" required autocomplete="current-password">
                    </div>
                    <label class="flex items-center gap-2 text-xs text-[#5f6f67]">
                        <input type="checkbox" name="remember" value="1" class="accent-[#0f766e]"> Keep me signed in on this device
                    </label>
                    <button class="btn-primary w-full">Sign in to dashboard</button>
                </form>
            </div>
            <div class="mt-5 text-center text-xs text-[#8a9892]">
                <a href="{{ route('storefront.home') }}" class="font-semibold hover:text-[#0f766e]">← Back to storefront</a>
            </div>
        </div>
    </div>
</body>
</html>
