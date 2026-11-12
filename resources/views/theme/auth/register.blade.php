@extends('theme.layouts.app')

@section('title', 'Create account — '.nexus_setting('store.name'))

@section('content')
<div class="container-wide py-14">
    <div class="mx-auto max-w-lg rounded-3xl border border-[var(--line)] bg-white p-7 shadow-sm sm:p-9">
        <div class="text-[10px] font-bold uppercase tracking-[.18em] text-[var(--link)]">Join us</div>
        <h1 class="mt-2 font-display text-3xl tracking-tight">Create your account</h1>
        <p class="mt-2 text-sm text-[var(--muted)]">Already have one? <a href="{{ route('login') }}" class="font-semibold text-[var(--link)] underline underline-offset-2">Sign in</a></p>
        <form method="post" action="{{ route('register.store') }}" class="mt-7 space-y-4">
            @csrf
            <div>
                <label class="label" for="name">Full name</label>
                <input id="name" name="name" value="{{ old('name') }}" class="input" required autofocus autocomplete="name">
            </div>
            <div>
                <label class="label" for="email">Email address</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" class="input" required autocomplete="email">
            </div>
            <div>
                <label class="label" for="password">Password</label>
                <input id="password" type="password" name="password" class="input" required autocomplete="new-password">
                <p class="mt-1.5 text-[11px] text-[var(--muted)]">At least 10 characters.</p>
            </div>
            <div>
                <label class="label" for="password_confirmation">Confirm password</label>
                <input id="password_confirmation" type="password" name="password_confirmation" class="input" required autocomplete="new-password">
            </div>
            <label class="flex items-start gap-2.5 text-xs leading-5 text-[var(--muted)]">
                <input type="checkbox" name="terms" value="1" class="mt-0.5 accent-[var(--brand)]" required @checked(old('terms'))>
                <span>I agree to the <a href="{{ route('storefront.page', 'terms') }}" class="font-semibold text-[var(--link)] underline underline-offset-2">terms</a> and <a href="{{ route('storefront.page', 'privacy') }}" class="font-semibold text-[var(--link)] underline underline-offset-2">privacy policy</a>.</span>
            </label>
            <button class="btn-primary w-full">Create account</button>
        </form>
    </div>
</div>
@endsection
