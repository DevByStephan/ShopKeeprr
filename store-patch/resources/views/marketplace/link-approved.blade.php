@extends('layouts.account')

@section('title', 'Store linked — '.site_name())

@section('account')
<div class="mx-auto max-w-2xl rounded-2xl border border-emerald-200 bg-emerald-50 p-8 text-center">
    <div class="mx-auto grid h-12 w-12 place-items-center rounded-full bg-emerald-600 text-white">
        <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="m5 12 4 4L19 6"/></svg>
    </div>
    <h1 class="display-sm mt-5">Store approved.</h1>
    <p class="mx-auto mt-3 max-w-md text-sm leading-6 text-emerald-900">
        <strong>{{ $link->site_name ?: 'Your store' }}</strong> can now see your marketplace purchases and install the plugins you own.
    </p>
    <p class="mt-2 text-xs text-emerald-800">You can close this tab and return to the store admin. The connection will finish automatically.</p>
    <a href="{{ route('account.index') }}" class="btn-secondary btn-sm mt-6">Back to your account</a>
</div>
@endsection
