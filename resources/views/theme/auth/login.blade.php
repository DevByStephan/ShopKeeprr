@extends('theme.layouts.app')

@section('title', 'Sign in — '.nexus_setting('store.name'))

@section('content')
<div class="container-wide py-14">
    <div class="mx-auto grid max-w-4xl overflow-hidden rounded-3xl border border-[var(--line)] bg-white shadow-sm sm:grid-cols-2">
        <div class="hidden bg-[var(--ink-strong)] p-9 text-white sm:flex sm:flex-col sm:justify-between">
            <div class="grid h-10 w-10 place-items-center rounded-xl bg-white/10 font-bold">{{ mb_substr(nexus_setting('store.name', 'ShopKeeprr'), 0, 1) }}</div>
            <div>
                <div class="text-[10px] font-bold uppercase tracking-[.18em] text-white/40">Welcome back</div>
                <h1 class="mt-2 font-display text-3xl leading-tight">Your orders, subscriptions and details, all in one place.</h1>
                <p class="mt-4 text-sm leading-6 text-white/55">Sign in to check order status, manage subscriptions and keep your delivery details up to date.</p>
            </div>
            <div class="text-[11px] text-white/30">© {{ date('Y') }} {{ nexus_setting('store.name') }}</div>
        </div>
        <div class="p-7 sm:p-9">
            <h1 class="font-display text-3xl tracking-tight">Sign in</h1>
            @if(nexus_demo())
                <p class="mt-2 text-sm text-[var(--muted)]">Registration is closed in this demo. Use the credentials on the <a href="{{ route('demo') }}" class="font-semibold text-[var(--link)] underline underline-offset-2">demo guide</a>.</p>
            @else
                <p class="mt-2 text-sm text-[var(--muted)]">New here? <a href="{{ route('register') }}" class="font-semibold text-[var(--link)] underline underline-offset-2">Create an account</a></p>
            @endif
            <form method="post" action="{{ route('login.store') }}" class="mt-7 space-y-4">
                @csrf
                <div>
                    <label class="label" for="email">Email address</label>
                    <input id="email" type="email" name="email" value="{{ old('email') }}" class="input" required autofocus autocomplete="email">
                </div>
                <div>
                    <div class="flex items-center justify-between">
                        <label class="label" for="password">Password</label>
                        @if(!nexus_demo())
                            <a href="{{ route('password.request') }}" class="mb-1.5 text-[11px] font-semibold text-[var(--link)]">Forgot?</a>
                        @endif
                    </div>
                    <input id="password" type="password" name="password" class="input" required autocomplete="current-password">
                </div>
                <label class="flex items-center gap-2 text-xs text-[var(--muted)]">
                    <input type="checkbox" name="remember" value="1" class="accent-[var(--brand)]"> Keep me signed in
                </label>
                <button class="btn-primary w-full">Sign in</button>
            </form>
        </div>
    </div>
</div>
@endsection
