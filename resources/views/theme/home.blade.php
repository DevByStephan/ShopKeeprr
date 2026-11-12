@extends('theme.layouts.app')

@section('title', nexus_setting('store.name', 'ShopKeeprr').' — '.nexus_setting('store.tagline', ''))
@section('description', nexus_setting('store.tagline', ''))

@section('content')
@php($heroImage = nexus_setting('store.hero_image') ? asset(nexus_setting('store.hero_image')) : $featured->first()?->imageUrl())
<section class="relative overflow-hidden">
    <div class="container-wide grid items-center gap-10 py-14 lg:grid-cols-[1.05fr_.95fr] lg:py-20">
        <div class="max-w-xl">
            <div class="inline-flex items-center gap-2 rounded-full border border-[#dfe7e2] bg-white/70 px-3 py-1.5 text-[10px] font-bold uppercase tracking-[.18em] text-[var(--link)]">
                <span class="h-1.5 w-1.5 rounded-full bg-[var(--brand)]"></span>
                {{ nexus_setting('store.hero_eyebrow', 'The everyday edit') }}
            </div>
            <h1 class="mt-5 font-display text-[44px] leading-[1.03] tracking-[-.02em] sm:text-6xl">
                {{ nexus_setting('store.hero_title', 'Good design, made to last.') }}
            </h1>
            <p class="mt-5 max-w-md text-[15px] leading-7 text-[#5c6c65]">
                {{ nexus_setting('store.hero_subtitle', 'Considered pieces for your home, your desk and your everyday rituals.') }}
            </p>
            <div class="mt-8 flex flex-wrap gap-3">
                <a href="{{ route('storefront.shop') }}" class="btn-primary">Shop the collection</a>
                @if(nexus_mode()?->value === 'b2b')
                    <a href="{{ route('storefront.trade') }}" class="btn-secondary">Apply for wholesale</a>
                @else
                    <a href="{{ route('storefront.page', 'about') }}" class="btn-secondary">Our story</a>
                @endif
            </div>
            <div class="mt-9 flex flex-wrap gap-x-7 gap-y-3 text-[11px] font-semibold uppercase tracking-[.1em] text-[#77867f]">
                <span class="flex items-center gap-2"><svg class="h-4 w-4 text-[var(--link)]" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M20 7 9.5 17.5 4 12"/></svg> Free shipping over {{ nexus_money(75, null) }}</span>
                <span class="flex items-center gap-2"><svg class="h-4 w-4 text-[var(--link)]" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M20 7 9.5 17.5 4 12"/></svg> 30-day returns</span>
            </div>
        </div>
        <div class="relative">
            <div class="absolute -left-6 -top-6 h-40 w-40 rounded-full bg-[#e5d7b8]/50 blur-2xl"></div>
            <div class="relative overflow-hidden rounded-[28px] border border-white/60 bg-[var(--surface-2)] shadow-[0_30px_70px_-30px_rgba(23,35,31,.35)]">
                @if($heroImage)
                    <img src="{{ $heroImage }}" alt="{{ nexus_setting('store.name') }}" class="aspect-[4/5] w-full object-cover">
                @else
                    <img src="{{ asset('images/placeholder.svg') }}" alt="" class="aspect-[4/5] w-full object-cover">
                @endif
                <div class="absolute inset-x-4 bottom-4 rounded-2xl bg-white/85 p-4 backdrop-blur">
                    <div class="text-[10px] font-bold uppercase tracking-[.16em] text-[var(--muted)]">This season</div>
                    <div class="mt-1 flex items-end justify-between gap-3">
                        <span class="font-display text-lg leading-tight">Made slowly. Chosen well.</span>
                        <a href="{{ route('storefront.shop') }}" class="whitespace-nowrap text-xs font-bold text-[var(--link)]">Explore →</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

@if($categories->isNotEmpty())
<section class="container-wide py-6">
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach($categories->take(4) as $category)
            <a href="{{ route('storefront.category', $category->slug) }}" class="group relative flex h-36 items-end overflow-hidden rounded-2xl border border-[var(--line)] bg-[#e9efeb] p-5 transition hover:-translate-y-0.5 hover:shadow-lg">
                @if($category->imageUrl())
                    <img src="{{ $category->imageUrl() }}" alt="{{ $category->name }}" class="absolute inset-0 h-full w-full object-cover opacity-80 transition duration-500 group-hover:scale-105">
                    <div class="absolute inset-0 bg-gradient-to-t from-[var(--ink-strong)]/65 to-transparent"></div>
                @endif
                <div class="relative">
                    <div class="text-[10px] font-bold uppercase tracking-[.14em] {{ $category->imageUrl() ? 'text-white/60' : 'text-[var(--muted)]' }}">{{ $category->products_count }} products</div>
                    <div class="mt-1 font-display text-xl {{ $category->imageUrl() ? 'text-white' : 'text-[var(--ink)]' }}">{{ $category->name }}</div>
                </div>
            </a>
        @endforeach
    </div>
</section>
@endif

@if($featured->isNotEmpty())
<section class="container-wide py-12">
    <div class="flex items-end justify-between gap-4 border-b border-[var(--line)] pb-5">
        <div>
            <div class="text-[10px] font-bold uppercase tracking-[.18em] text-[var(--link)]">Curated for you</div>
            <h2 class="mt-2 font-display text-3xl tracking-tight">Featured pieces</h2>
        </div>
        <a href="{{ route('storefront.shop') }}" class="text-xs font-bold uppercase tracking-[.1em] text-[var(--muted)] hover:text-[var(--link)]">View all →</a>
    </div>
    <div class="mt-7 grid grid-cols-2 gap-x-5 gap-y-9 lg:grid-cols-4">
        @foreach($featured as $product)
            @include(nexus_partial('partials.product-card'), ['product' => $product])
        @endforeach
    </div>
</section>
@endif

@if($newArrivals->isNotEmpty())
<section class="container-wide py-10">
    <div class="grid gap-10 rounded-[28px] border border-[var(--line)] bg-white p-6 sm:p-9 lg:grid-cols-[.7fr_1.3fr]">
        <div>
            <div class="text-[10px] font-bold uppercase tracking-[.18em] text-[var(--link)]">Just landed</div>
            <h2 class="mt-2 font-display text-3xl tracking-tight">New arrivals</h2>
            <p class="mt-3 text-sm leading-6 text-[var(--muted)]">Fresh additions to the shelf, restocked weekly and made in small runs.</p>
            <a href="{{ route('storefront.shop', ['sort' => 'newest']) }}" class="btn-dark mt-6">Shop new arrivals</a>
        </div>
        <div class="grid grid-cols-2 gap-x-5 gap-y-8 sm:grid-cols-4">
            @foreach($newArrivals->take(4) as $product)
                @include(nexus_partial('partials.product-card'), ['product' => $product])
            @endforeach
        </div>
    </div>
</section>
@endif

<section class="container-wide py-12">
    <div class="grid gap-4 sm:grid-cols-3">
        @foreach([
            ['Free delivery', 'On every order over '.nexus_money(75).', anywhere we ship.'],
            ['Made with care', 'Small-batch production and materials we stand behind.'],
            ['Easy returns', 'Thirty days to change your mind, no questions asked.'],
        ] as [$title, $copy])
            <div class="rounded-2xl border border-[var(--line)] bg-white/60 p-6">
                <div class="grid h-9 w-9 place-items-center rounded-full bg-[#e7f5f0] text-[var(--link)]">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20 7 9.5 17.5 4 12"/></svg>
                </div>
                <div class="mt-4 text-sm font-bold">{{ $title }}</div>
                <p class="mt-1 text-sm leading-6 text-[var(--muted)]">{{ $copy }}</p>
            </div>
        @endforeach
    </div>
</section>
@endsection
