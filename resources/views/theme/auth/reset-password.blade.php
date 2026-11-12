@extends('theme.layouts.app')

@section('title', 'Choose a new password — '.nexus_setting('store.name'))

@section('content')
<div class="container-wide py-14">
    <div class="mx-auto max-w-lg rounded-3xl border border-[var(--line)] bg-white p-7 shadow-sm sm:p-9">
        <h1 class="font-display text-3xl tracking-tight">Choose a new password</h1>
        <p class="mt-2 text-sm text-[var(--muted)]">Pick something at least 10 characters long that you have not used before.</p>
        <form method="post" action="{{ route('password.update') }}" class="mt-7 space-y-4">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <div>
                <label class="label" for="email">Email address</label>
                <input id="email" type="email" name="email" value="{{ old('email', $email ?? '') }}" class="input" required autocomplete="email">
            </div>
            <div>
                <label class="label" for="password">New password</label>
                <input id="password" type="password" name="password" class="input" required autocomplete="new-password">
            </div>
            <div>
                <label class="label" for="password_confirmation">Confirm new password</label>
                <input id="password_confirmation" type="password" name="password_confirmation" class="input" required autocomplete="new-password">
            </div>
            <button class="btn-primary w-full">Update password</button>
        </form>
    </div>
</div>
@endsection
