@extends('theme.layouts.app')

@section('title', $vendor->name.' — '.nexus_setting('store.name'))
@section('description', $vendor->description ?: 'Shop products from '.$vendor->name.'.')

@section('content')
<div class="border-b border-[var(--line)] bg-[var(--surface-2)]">
    <div class="container-wide py-10 sm:py-14">
        <nav class="text-[11px] font-semibold uppercase tracking-[.12em] text-[var(--muted)]">
            <a href="{{ route('storefront.home') }}" class="hover:text-[var(--link)]">Home</a>
            <span class="px-2">/</span>
            <a href="{{ route('storefront.vendors.index') }}" class="hover:text-[var(--link)]">Vendors</a>
            <span class="px-2">/</span>
            <span>{{ $vendor->name }}</span>
        </nav>
        <div class="mt-6 flex flex-wrap items-center gap-5">
            <span class="grid h-16 w-16 place-items-center rounded-2xl bg-[var(--brand)] text-lg font-bold text-white">{{ $vendor->initials() }}</span>
            <div>
                <h1 class="font-display text-4xl tracking-tight sm:text-5xl">{{ $vendor->name }}</h1>
                <p class="mt-1 text-[11px] font-bold uppercase tracking-[.12em] text-[var(--muted)]">Independent seller · {{ $products->total() }} {{ Str::plural('product', $products->total()) }}</p>
            </div>
        </div>
        @if($vendor->description)
            <p class="mt-4 max-w-2xl text-sm leading-7 text-[var(--muted)]">{{ $vendor->description }}</p>
        @endif
    </div>
</div>

<div class="container-wide py-10">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <h2 class="font-display text-2xl">From {{ $vendor->name }}</h2>
        <form method="get" class="flex items-center gap-2">
            <input name="q" value="{{ request('q') }}" class="input !w-56" placeholder="Search this seller">
            <button class="btn-secondary !px-4 !py-2.5 text-xs">Search</button>
        </form>
    </div>

    @if($products->isEmpty())
        <div class="rounded-2xl border border-dashed border-[var(--line)] bg-white/60 p-10 text-center text-sm text-[var(--muted)]">
            No products from this seller yet. <a href="{{ route('storefront.shop') }}" class="font-bold text-[var(--link)]">Browse the full shop →</a>
        </div>
    @else
        <div class="grid gap-x-5 gap-y-9 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($products as $product)
                @include('theme.partials.product-card', ['product' => $product])
            @endforeach
        </div>
        <div class="mt-10">{{ $products->links() }}</div>
    @endif
</div>
@endsection
