@extends('layouts.account')

@section('title', 'Link a store — '.site_name())

@section('account')
<div class="mx-auto max-w-2xl">
    <div class="eyebrow"><span class="eyebrow-dot"></span> ShopKeeprr Store</div>
    <h1 class="display-md mt-2">Link a store.</h1>
    <p class="mt-3 text-sm leading-6 text-[var(--muted)]">
        Approve this connection to let the store admin browse your purchases and install plugins with one click.
        The store receives a scoped site token, never your password.
    </p>

    @if($error)
        <div class="mt-6 rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm leading-6 text-rose-900">
            {{ $error }}
        </div>
    @elseif($link)
        <div class="mt-6 rounded-2xl border border-[var(--line)] bg-white p-5">
            <div class="text-[10px] font-bold uppercase tracking-[.14em] text-[var(--link)]">Connection request</div>
            <div class="mt-2 font-display text-2xl">{{ $link->site_name ?: 'Your ShopKeeprr store' }}</div>
            <div class="mt-1 break-all font-mono text-xs text-[var(--muted)]">{{ $link->site_url }}</div>

            <dl class="mt-5 grid gap-3 text-sm sm:grid-cols-2">
                <div class="rounded-xl bg-[#f8fbf9] p-3">
                    <dt class="text-[10px] font-bold uppercase tracking-[.12em] text-[var(--muted)]">Platform</dt>
                    <dd class="mt-1 font-semibold">ShopKeeprr {{ $link->platform_version ?: '1.1' }}</dd>
                </div>
                <div class="rounded-xl bg-[#f8fbf9] p-3">
                    <dt class="text-[10px] font-bold uppercase tracking-[.12em] text-[var(--muted)]">Expires</dt>
                    <dd class="mt-1 font-semibold">{{ $link->expires_at->diffForHumans() }}</dd>
                </div>
            </dl>

            <form method="post" action="{{ route('marketplace.link.approve') }}" class="mt-6">
                @csrf
                <input type="hidden" name="code" value="{{ $link->user_code }}">
                <div class="rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs leading-5 text-amber-900">
                    Only approve a store you control. A linked site can see which products this account owns and download those packages.
                </div>
                <button class="btn-primary mt-4 w-full">Approve this store</button>
            </form>
        </div>
    @else
        <form method="get" action="{{ route('marketplace.link') }}" class="mt-6 rounded-2xl border border-[var(--line)] bg-white p-5">
            <label class="label" for="code">Connection code</label>
            <input id="code" name="code" value="{{ $code }}" class="input mt-2 font-mono uppercase tracking-[.18em]" maxlength="12" autocomplete="off" required>
            <p class="mt-2 text-xs leading-5 text-[var(--muted)]">The code appears in your store admin under Settings → Store account.</p>
            <button class="btn-secondary mt-4">Find the request</button>
        </form>
    @endif
</div>
@endsection
