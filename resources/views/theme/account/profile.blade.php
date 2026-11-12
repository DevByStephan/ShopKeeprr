@extends('theme.layouts.app')

@section('title', 'Your profile — '.nexus_setting('store.name'))

@section('content')
<div class="container-wide py-10 sm:py-14">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <div class="text-[10px] font-bold uppercase tracking-[.18em] text-[var(--link)]">My account</div>
            <h1 class="mt-2 font-display text-4xl tracking-tight">Your profile</h1>
        </div>
        <a href="{{ route('storefront.account.index') }}" class="text-xs font-bold uppercase tracking-[.1em] text-[var(--muted)] hover:text-[var(--link)]">← Account overview</a>
    </div>

    <div class="mt-8 grid gap-6 lg:grid-cols-2">
        <form method="post" action="{{ route('storefront.account.profile.update') }}" class="card card-pad">
            @csrf
            @method('PATCH')
            <h2 class="font-display text-2xl">Personal details</h2>
            <p class="mt-1 text-sm text-[var(--muted)]">Used on receipts and delivery labels.</p>
            <div class="mt-5 grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="label" for="name">Full name</label>
                    <input id="name" name="name" value="{{ old('name', $user->name) }}" class="input" required autocomplete="name">
                </div>
                <div class="sm:col-span-2">
                    <label class="label" for="email">Email address</label>
                    <input id="email" type="email" name="email" value="{{ old('email', $user->email) }}" class="input" required autocomplete="email">
                </div>
                <div>
                    <label class="label" for="phone">Phone</label>
                    <input id="phone" name="phone" value="{{ old('phone', $user->phone) }}" class="input" autocomplete="tel">
                </div>
                <div>
                    <label class="label" for="company_name">Company</label>
                    <input id="company_name" name="company_name" value="{{ old('company_name', $user->company_name) }}" class="input" autocomplete="organization">
                </div>
                <div class="sm:col-span-2">
                    <label class="label" for="tax_id">Tax / VAT ID</label>
                    <input id="tax_id" name="tax_id" value="{{ old('tax_id', $user->tax_id) }}" class="input">
                </div>
            </div>
            <button class="btn-primary mt-5">Save changes</button>
        </form>

        <div class="space-y-6">
            <form method="post" action="{{ route('storefront.account.password') }}" class="card card-pad">
                @csrf
                @method('PATCH')
                <h2 class="font-display text-2xl">Password</h2>
                <p class="mt-1 text-sm text-[var(--muted)]">Choose a strong password you do not use elsewhere.</p>
                <div class="mt-5 space-y-4">
                    <div>
                        <label class="label" for="current_password">Current password</label>
                        <input id="current_password" type="password" name="current_password" class="input" required autocomplete="current-password">
                    </div>
                    <div>
                        <label class="label" for="new_password">New password</label>
                        <input id="new_password" type="password" name="password" class="input" required autocomplete="new-password">
                    </div>
                    <div>
                        <label class="label" for="password_confirmation">Confirm new password</label>
                        <input id="password_confirmation" type="password" name="password_confirmation" class="input" required autocomplete="new-password">
                    </div>
                </div>
                <button class="btn-secondary mt-5">Update password</button>
            </form>

            <div class="card card-pad">
                <div class="text-[10px] font-bold uppercase tracking-[.14em] text-[var(--muted)]">Session</div>
                <p class="mt-2 text-sm text-[var(--muted)]">Signed in as {{ $user->email }}</p>
                <form method="post" action="{{ route('logout') }}" class="mt-3">
                    @csrf
                    <button class="text-xs font-bold text-rose-600 underline underline-offset-4">Sign out</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
