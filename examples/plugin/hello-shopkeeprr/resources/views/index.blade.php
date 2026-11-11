@extends('theme.layouts.app')

@section('title', 'Hello ShopKeeprr')

@section('content')
<section class="container-wide py-14 sm:py-20">
    <div class="max-w-2xl">
        <div class="text-[10px] font-bold uppercase tracking-[.18em] text-[var(--link)]">Example module</div>
        <h1 class="mt-3 font-display text-4xl tracking-tight sm:text-5xl">Hello, {{ auth()->user()->name }}.</h1>
        <p class="mt-4 text-sm leading-7 text-[var(--muted)]">
            This page is rendered by a ShopKeeprr module. It has its own manifest, service provider, route and namespaced Blade view.
        </p>

        <div class="mt-8 rounded-2xl border border-[var(--line)] bg-white p-6">
            <h2 class="font-display text-2xl">What to change next</h2>
            <ul class="mt-4 grid gap-3 text-sm leading-6 text-[var(--muted)]">
                <li>Replace this view with the account widget your module needs.</li>
                <li>Add controllers, migrations or scheduled work under the module namespace.</li>
                <li>Declare the ShopKeeprr version range in <code>module.json</code> before releasing.</li>
                <li>Test installation, upgrade and removal on a staging store.</li>
            </ul>
        </div>

        <a href="{{ route('storefront.shop') }}" class="btn-secondary mt-6">Back to the shop</a>
    </div>
</section>
@endsection
