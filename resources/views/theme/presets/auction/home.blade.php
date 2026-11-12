@extends('theme.layouts.app')

@php
    $heroImage = nexus_setting('store.hero_image') ? asset(nexus_setting('store.hero_image')) : $featured->first()?->imageUrl();
    $dealProducts = $deals->concat($featured)->concat($newArrivals)->unique('id')->take(4);
    $listings = $featured->concat($newArrivals)->unique('id')->take(8);
@endphp

@section('title', nexus_setting('store.name', 'ShopKeeprr').' — Find it. Love it.')
@section('description', nexus_setting('store.tagline', 'Browse thousands of listings from trusted sellers.'))

@section('content')
<div class="container-wide pt-4">
    <div class="grid gap-3 md:grid-cols-[1.7fr_1fr]">
        <section class="relative overflow-hidden rounded-[var(--card-radius)] bg-[linear-gradient(115deg,#004b9e_0%,#0064d2_58%,#2f8ae8_100%)] p-6 text-white sm:p-8 lg:p-10">
            <div class="relative z-10 grid items-center gap-6 sm:grid-cols-[1.3fr_.7fr]">
                <div>
                    <div class="text-[11px] font-bold uppercase tracking-[.18em] text-white/65">The marketplace edit</div>
                    <h1 class="mt-2 text-3xl font-bold leading-[1.08] tracking-tight sm:text-4xl lg:text-[42px]">Find it. Love it.<br>Make it yours.</h1>
                    <p class="mt-3 max-w-md text-[13px] leading-6 text-white/75">Thousands of carefully chosen listings, protected payments and free returns on eligible items.</p>
                    <div class="mt-5 flex flex-wrap gap-2.5">
                        <a href="{{ route('storefront.shop') }}" class="rounded-[var(--btn-radius)] bg-white px-5 py-2.5 text-[13px] font-bold text-[#004b9e] transition hover:bg-[#eef5ff]">Start exploring</a>
                        <a href="{{ route('storefront.shop', ['sort' => 'price_asc']) }}" class="rounded-[var(--btn-radius)] border border-white/35 px-5 py-2.5 text-[13px] font-bold text-white transition hover:bg-white/10">Shop deals</a>
                    </div>
                </div>
                @if($heroImage)
                    <div class="hidden items-end justify-center sm:flex">
                        <img src="{{ $heroImage }}" alt="" class="max-h-[310px] w-auto object-contain drop-shadow-2xl">
                    </div>
                @endif
            </div>
            <div class="absolute -right-10 -top-16 h-64 w-64 rounded-full bg-white/10 blur-2xl"></div>
        </section>

        <div class="grid gap-3 sm:grid-cols-2 md:grid-cols-1">
            <a href="{{ route('storefront.shop', ['sort' => 'price_asc']) }}" class="group relative overflow-hidden rounded-[var(--card-radius)] bg-[#fff7dc] p-5">
                <div class="relative z-10">
                    <div class="text-[10px] font-bold uppercase tracking-[.14em] text-[#8a5b00]">Great value</div>
                    <div class="mt-1.5 max-w-[13rem] text-lg font-bold leading-tight text-[#111820]">Deals under {{ nexus_money(50) }}</div>
                    <span class="mt-3 inline-block text-[12px] font-semibold text-[#004b9e] group-hover:underline">Shop the deals →</span>
                </div>
                <span class="absolute -bottom-8 -right-6 h-28 w-28 rounded-full bg-[#f5af02]/30"></span>
            </a>
            <a href="{{ route('storefront.shop', ['sort' => 'newest']) }}" class="group relative overflow-hidden rounded-[var(--card-radius)] bg-[#eff8e4] p-5">
                <div class="relative z-10">
                    <div class="text-[10px] font-bold uppercase tracking-[.14em] text-[#3f6b00]">Just landed</div>
                    <div class="mt-1.5 max-w-[13rem] text-lg font-bold leading-tight text-[#111820]">New arrivals, fresh this week</div>
                    <span class="mt-3 inline-block text-[12px] font-semibold text-[#004b9e] group-hover:underline">See what's new →</span>
                </div>
                <span class="absolute -bottom-8 -right-6 h-28 w-28 rounded-full bg-[#86b817]/20"></span>
            </a>
        </div>
    </div>

    @if($categories->isNotEmpty())
        <div class="mt-3 flex gap-2 overflow-x-auto rounded-[var(--card-radius)] border border-[var(--line)] bg-[var(--card)] p-3 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
            @foreach($categories as $department)
                <a href="{{ route('storefront.category', $department->slug) }}" class="flex min-w-[110px] flex-1 flex-col items-center gap-1.5 rounded-md px-3 py-2.5 text-center transition hover:bg-[var(--surface-2)]">
                    <span class="grid h-9 w-9 place-items-center rounded-full bg-[var(--brand-soft)] text-[13px] font-bold text-[var(--link)]">{{ Str::upper(Str::substr($department->name, 0, 1)) }}</span>
                    <span class="text-[12px] font-semibold text-[var(--ink)]">{{ $department->name }}</span>
                    <span class="text-[10px] text-[var(--muted)]">{{ $department->products_count }} listings</span>
                </a>
            @endforeach
        </div>
    @endif
</div>

<section class="container-wide py-7">
    <div class="mb-4 flex flex-wrap items-end justify-between gap-2">
        <div>
            <div class="text-[10px] font-bold uppercase tracking-[.16em] text-[var(--sale)]">Limited-time prices</div>
            <h2 class="mt-1 text-2xl font-bold tracking-tight text-[var(--ink)]">Today's deals</h2>
        </div>
        <a href="{{ route('storefront.shop', ['sort' => 'price_asc']) }}" class="text-[12px] font-semibold text-[var(--link)] hover:underline">See all deals →</a>
    </div>
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
        @foreach($dealProducts as $product)
            @include(nexus_partial('partials.product-card'), ['product' => $product])
        @endforeach
    </div>
</section>

<section class="container-wide py-3">
    <div class="rounded-[var(--card-radius)] border border-[var(--line)] bg-[var(--card)] px-5 py-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <span class="grid h-9 w-9 place-items-center rounded-full bg-[#e8f2ff] text-[#0064d2]">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.9" viewBox="0 0 24 24"><path d="M12 3 4 6v6c0 5 3.4 8 8 9 4.6-1 8-4 8-9V6l-8-3Z"/><path d="m9 12 2 2 4-4"/></svg>
                </span>
                <div>
                    <div class="text-[13px] font-bold text-[var(--ink)]">Buy with confidence</div>
                    <div class="text-[11px] text-[var(--muted)]">Secure payments, buyer protection and tracked delivery on every order.</div>
                </div>
            </div>
            <a href="{{ route('storefront.page', 'shipping-returns') }}" class="text-[12px] font-semibold text-[var(--link)] hover:underline">Learn more →</a>
        </div>
    </div>
</section>

<section class="container-wide py-7">
    <div class="mb-4 flex items-end justify-between gap-3">
        <div>
            <div class="text-[10px] font-bold uppercase tracking-[.16em] text-[var(--link)]">Handpicked for you</div>
            <h2 class="mt-1 text-2xl font-bold tracking-tight text-[var(--ink)]">Featured listings</h2>
        </div>
        <a href="{{ route('storefront.shop') }}" class="text-[12px] font-semibold text-[var(--link)] hover:underline">Browse all →</a>
    </div>
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
        @foreach($listings as $product)
            @include(nexus_partial('partials.product-card'), ['product' => $product])
        @endforeach
    </div>
</section>

<section class="container-wide py-3">
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        @foreach([
            ['Free shipping', 'On thousands of eligible listings'],
            ['Easy returns', '30 days, no questions asked'],
            ['Secure checkout', 'Encrypted payment processing'],
            ['Real support', 'Help from actual humans'],
        ] as [$title, $copy])
            <div class="flex items-start gap-3 rounded-[var(--card-radius)] border border-[var(--line)] bg-[var(--card)] p-4">
                <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-[var(--brand-soft)] text-[var(--link)]">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20 7 9.5 17.5 4 12"/></svg>
                </span>
                <div><div class="text-[13px] font-bold text-[var(--ink)]">{{ $title }}</div><div class="mt-0.5 text-[11px] leading-5 text-[var(--muted)]">{{ $copy }}</div></div>
            </div>
        @endforeach
    </div>
</section>

@if($newArrivals->isNotEmpty())
<section class="container-wide py-7">
    <div class="mb-4 flex items-end justify-between gap-3">
        <div>
            <div class="text-[10px] font-bold uppercase tracking-[.16em] text-[var(--link)]">Just added</div>
            <h2 class="mt-1 text-2xl font-bold tracking-tight text-[var(--ink)]">New arrivals</h2>
        </div>
        <a href="{{ route('storefront.shop', ['sort' => 'newest']) }}" class="text-[12px] font-semibold text-[var(--link)] hover:underline">Shop new →</a>
    </div>
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
        @foreach($newArrivals->take(4) as $product)
            @include(nexus_partial('partials.product-card'), ['product' => $product])
        @endforeach
    </div>
</section>
@endif
@endsection
