@extends('theme.layouts.app')

@section('title', nexus_setting('store.name', 'ShopKeeprr').' — '.nexus_setting('store.tagline', ''))

@section('content')
@php
    $featured = $featured ?? collect();
    $categories = $categories ?? collect();
    $newArrivals = $newArrivals ?? collect();
@endphp

<section class="container-wide py-14 sm:py-20">
    <div class="grid items-center gap-10 lg:grid-cols-[1.05fr_.95fr]">
        <div>
            <div class="inline-flex items-center gap-2 rounded-full border border-[var(--line)] bg-white/70 px-3 py-1.5 text-[10px] font-bold uppercase tracking-[.18em] text-[var(--link)]">
                <span class="h-1.5 w-1.5 rounded-full bg-[var(--brand)]"></span>
                {{ nexus_setting('store.hero_eyebrow', 'The everyday edit') }}
            </div>
            <h1 class="mt-5 font-display text-5xl leading-[1.02] tracking-[-.02em] sm:text-6xl">
                {{ nexus_setting('store.hero_title', 'Good design, made to last.') }}
            </h1>
            <p class="mt-5 max-w-md text-[15px] leading-7 text-[var(--muted)]">
                {{ nexus_setting('store.hero_subtitle', 'Considered pieces for your home, your desk and your everyday rituals.') }}
            </p>
            <a href="{{ route('storefront.shop') }}" class="btn-primary mt-7">Shop the collection</a>
        </div>

        <div class="overflow-hidden rounded-[28px] border border-[var(--line)] bg-white p-3 shadow-[0_24px_60px_-35px_rgba(23,35,31,.4)]">
            @if($featured->first()?->imageUrl())
                <img src="{{ $featured->first()->imageUrl() }}" alt="{{ $featured->first()->name }}" class="aspect-[4/3] w-full rounded-[20px] object-cover">
            @else
                <div class="grid aspect-[4/3] place-items-center rounded-[20px] bg-[var(--surface-2)] font-display text-2xl text-[var(--muted)]">
                    Your storefront
                </div>
            @endif
        </div>
    </div>
</section>

@if($categories->isNotEmpty())
<section class="container-wide pb-10">
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        @foreach($categories->take(4) as $category)
            <a href="{{ route('storefront.category', $category->slug) }}" class="rounded-2xl border border-[var(--line)] bg-white/70 p-5 transition hover:-translate-y-0.5 hover:shadow-lg">
                <div class="text-[10px] font-bold uppercase tracking-[.14em] text-[var(--muted)]">{{ $category->products_count }} products</div>
                <div class="mt-2 font-display text-xl">{{ $category->name }}</div>
            </a>
        @endforeach
    </div>
</section>
@endif

@if($featured->isNotEmpty())
<section class="container-wide py-10">
    <div class="flex items-end justify-between gap-4 border-b border-[var(--line)] pb-5">
        <div>
            <div class="text-[10px] font-bold uppercase tracking-[.18em] text-[var(--link)]">Curated for you</div>
            <h2 class="mt-2 font-display text-3xl tracking-tight">Featured pieces</h2>
        </div>
        <a href="{{ route('storefront.shop') }}" class="text-xs font-bold uppercase tracking-[.1em] text-[var(--muted)] hover:text-[var(--link)]">View all</a>
    </div>
    <div class="mt-7 grid grid-cols-2 gap-x-5 gap-y-9 lg:grid-cols-3">
        @foreach($featured as $product)
            @include(nexus_partial('partials.product-card'), ['product' => $product])
        @endforeach
    </div>
</section>
@endif

@if($newArrivals->isNotEmpty())
<section class="container-wide py-10">
    <div class="rounded-[28px] border border-[var(--line)] bg-white p-6 sm:p-9">
        <div class="flex items-end justify-between gap-4">
            <div>
                <div class="text-[10px] font-bold uppercase tracking-[.18em] text-[var(--link)]">Just landed</div>
                <h2 class="mt-2 font-display text-3xl tracking-tight">New arrivals</h2>
            </div>
            <a href="{{ route('storefront.shop', ['sort' => 'newest']) }}" class="text-xs font-bold uppercase tracking-[.1em] text-[var(--muted)] hover:text-[var(--link)]">Shop new</a>
        </div>
        <div class="mt-7 grid grid-cols-2 gap-x-5 gap-y-9 lg:grid-cols-4">
            @foreach($newArrivals->take(4) as $product)
                @include(nexus_partial('partials.product-card'), ['product' => $product])
            @endforeach
        </div>
    </div>
</section>
@endif
@endsection
