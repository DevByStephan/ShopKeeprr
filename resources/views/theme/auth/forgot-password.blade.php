@extends('theme.layouts.app')

@section('title', 'Reset password — '.nexus_setting('store.name'))

@section('content')
<div class="container-wide py-14">
    <div class="mx-auto max-w-lg rounded-3xl border border-[var(--line)] bg-white p-7 shadow-sm sm:p-9">
        <h1 class="font-display text-3xl tracking-tight">Forgot your password?</h1>
        <p class="mt-2 text-sm leading-6 text-[var(--muted)]">Enter the email address on your account and we will send you a link to choose a new password.</p>
        <form method="post" action="{{ route('password.email') }}" class="mt-7 space-y-4">
            @csrf
            <div>
                <label class="label" for="email">Email address</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" class="input" required autofocus autocomplete="email">
            </div>
            <button class="btn-primary w-full">Email me a reset link</button>
            <a href="{{ route('login') }}" class="block text-center text-xs font-semibold text-[var(--muted)] hover:text-[var(--link)]">Back to sign in</a>
        </form>
    </div>
</div>
@endsection
