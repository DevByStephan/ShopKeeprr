@extends('theme.layouts.app')

@php
    $heroImage = nexus_setting('store.hero_image') ? asset(nexus_setting('store.hero_image')) : $featured->first()?->imageUrl();
    $dealProducts = $deals->concat($featured)->concat($newArrivals)->unique('id')->take(5);
    $trending = $featured->concat($newArrivals)->unique('id')->take(10);
@endphp

@section('title', nexus_setting('store.name', 'ShopKeeprr').' — Global finds, local prices')
@section('description', nexus_setting('store.tagline', 'Shop trending finds at prices you will love.'))

@section('content')
<div class="container-wide pt-4">
    <div class="grid gap-3 md:grid-cols-[1.65fr_1fr]">
        <section class="relative overflow-hidden rounded-[var(--card-radius)] bg-[linear-gradient(115deg,#c72400_0%,#e62e04_56%,#ff6a00_100%)] p-6 text-white sm:p-8 lg:p-10">
            <div class="relative z-10 grid items-center gap-6 sm:grid-cols-[1.3fr_.7fr]">
                <div>
                    <div class="inline-flex rounded-full bg-white/15 px-3 py-1 text-[10px] font-bold uppercase tracking-[.14em]">Global finds · local prices</div>
                    <h1 class="mt-3 text-3xl font-black leading-[1.06] tracking-tight sm:text-4xl lg:text-[44px]">Big finds.<br>Small prices.</h1>
                    <p class="mt-3 max-w-md text-[13px] leading-6 text-white/80">Trending products, everyday essentials and one-off discoveries — with free shipping on orders over {{ nexus_money(nexus_setting('shipping.free_threshold', 75)) }}.</p>
                    <div class="mt-5 flex flex-wrap gap-2.5">
                        <a href="{{ route('storefront.shop') }}" class="rounded-full bg-white px-5 py-2.5 text-[13px] font-bold text-[var(--brand)] transition hover:bg-[#fff4ef]">Shop now</a>
                        <a href="{{ route('storefront.shop', ['sort' => 'price_asc']) }}" class="rounded-full border border-white/40 px-5 py-2.5 text-[13px] font-bold text-white transition hover:bg-white/10">SuperDeals</a>
                    </div>
                </div>
                @if($heroImage)
                    <div class="hidden items-end justify-center sm:flex">
                        <img src="{{ $heroImage }}" alt="" class="max-h-[310px] w-auto object-contain drop-shadow-2xl">
                    </div>
                @endif
            </div>
            <div class="absolute -right-12 -top-16 h-64 w-64 rounded-full bg-white/10 blur-2xl"></div>
        </section>

        <div class="grid gap-3 sm:grid-cols-2 md:grid-cols-1">
            <a href="{{ route('storefront.shop', ['sort' => 'price_asc']) }}" class="group relative overflow-hidden rounded-[var(--card-radius)] bg-[#fff1e8] p-5">
                <div class="relative z-10">
                    <div class="text-[10px] font-black uppercase tracking-[.14em] text-[var(--brand)]">SuperDeals</div>
                    <div class="mt-1.5 max-w-[13rem] text-lg font-black leading-tight text-[#1a1a1a]">Up to 40% off everyday finds</div>
                    <span class="mt-3 inline-block text-[12px] font-semibold text-[var(--brand)] group-hover:underline">Shop the deals →</span>
                </div>
                <span class="absolute -bottom-7 -right-5 h-28 w-28 rounded-full bg-[var(--accent)]/20"></span>
            </a>
            <a href="{{ route('storefront.shop', ['sort' => 'newest']) }}" class="group relative overflow-hidden rounded-[var(--card-radius)] bg-[#fff8e8] p-5">
                <div class="relative z-10">
                    <div class="text-[10px] font-black uppercase tracking-[.14em] text-[#b45309]">New in</div>
                    <div class="mt-1.5 max-w-[13rem] text-lg font-black leading-tight text-[#1a1a1a]">Fresh picks added this week</div>
                    <span class="mt-3 inline-block text-[12px] font-semibold text-[var(--brand)] group-hover:underline">See what's new →</span>
                </div>
                <span class="absolute -bottom-7 -right-5 h-28 w-28 rounded-full bg-[#f5af02]/15"></span>
            </a>
        </div>
    </div>

    @if($categories->isNotEmpty())
        <div class="mt-3 flex gap-2 overflow-x-auto rounded-[var(--card-radius)] bg-[var(--card)] p-3 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
            @foreach($categories as $department)
                <a href="{{ route('storefront.category', $department->slug) }}" class="flex min-w-[100px] flex-1 flex-col items-center gap-1.5 rounded-lg px-3 py-2 text-center transition hover:bg-[var(--brand-soft)]">
                    <span class="grid h-10 w-10 place-items-center rounded-full bg-[var(--brand-soft)] text-[13px] font-black text-[var(--brand)]">{{ Str::upper(Str::substr($department->name, 0, 1)) }}</span>
                    <span class="text-[12px] font-semibold text-[var(--ink)]">{{ $department->name }}</span>
                    <span class="text-[10px] text-[var(--muted)]">{{ $department->products_count }} items</span>
                </a>
            @endforeach
        </div>
    @endif
</div>

<section class="container-wide py-7">
    <div class="mb-4 flex flex-wrap items-end justify-between gap-2">
        <div>
            <div class="flex items-center gap-2">
                <h2 class="text-2xl font-black tracking-tight text-[var(--brand)]">SuperDeals</h2>
                <span class="rounded-full bg-[var(--brand)] px-2 py-0.5 text-[9px] font-bold uppercase tracking-wide text-white">Limited time</span>
            </div>
            <p class="mt-1 text-[12px] text-[var(--muted)]">The biggest markdowns in the bazaar, refreshed regularly.</p>
        </div>
        <a href="{{ route('storefront.shop', ['sort' => 'price_asc']) }}" class="text-[12px] font-semibold text-[var(--brand)] hover:underline">See all deals →</a>
    </div>
    <div class="grid grid-cols-2 gap-2.5 sm:grid-cols-3 lg:grid-cols-5">
        @foreach($dealProducts as $product)
            @include(nexus_partial('partials.product-card'), ['product' => $product])
        @endforeach
    </div>
</section>

<section class="container-wide py-3">
    <div class="relative overflow-hidden rounded-[var(--card-radius)] bg-[linear-gradient(110deg,#1a1a1a_0%,#2c2c2c_60%,#3a241d_100%)] px-6 py-7 text-white sm:px-9">
        <div class="relative z-10 flex flex-wrap items-center justify-between gap-4">
            <div>
                <div class="text-[10px] font-bold uppercase tracking-[.18em] text-[var(--accent)]">The everyday edit</div>
                <div class="mt-1.5 text-2xl font-black tracking-tight">More of what you love, for less.</div>
                <p class="mt-1 max-w-lg text-[12px] text-white/65">Free shipping over {{ nexus_money(nexus_setting('shipping.free_threshold', 75)) }}, 30-day returns and secure checkout on every order.</p>
            </div>
            <a href="{{ route('storefront.shop') }}" class="rounded-full bg-[var(--brand)] px-5 py-2.5 text-[12px] font-bold text-white transition hover:brightness-95">Browse everything</a>
        </div>
        <span class="absolute -right-10 -top-20 h-56 w-56 rounded-full bg-[var(--brand)]/25 blur-3xl"></span>
    </div>
</section>

<section class="container-wide py-7">
    <div class="mb-4 flex items-end justify-between gap-3">
        <div>
            <div class="text-[10px] font-bold uppercase tracking-[.16em] text-[var(--brand)]">Trending now</div>
            <h2 class="mt-1 text-2xl font-black tracking-tight text-[var(--ink)]">Everyone's shopping</h2>
        </div>
        <a href="{{ route('storefront.shop') }}" class="text-[12px] font-semibold text-[var(--brand)] hover:underline">View all →</a>
    </div>
    <div class="grid grid-cols-2 gap-2.5 sm:grid-cols-3 lg:grid-cols-5">
        @foreach($trending as $product)
            @include(nexus_partial('partials.product-card'), ['product' => $product])
        @endforeach
    </div>
</section>

<section class="container-wide py-3">
    <div class="grid gap-2.5 sm:grid-cols-2 lg:grid-cols-4">
        @foreach([
            ['Free shipping', 'On orders over '.nexus_money(nexus_setting('shipping.free_threshold', 75))],
            ['Buyer protection', 'Shop with confidence, every time'],
            ['Easy returns', '30 days to change your mind'],
            ['Secure payments', 'Encrypted checkout'],
        ] as [$title, $copy])
            <div class="flex items-start gap-3 rounded-[var(--card-radius)] bg-[var(--card)] p-4">
                <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-[var(--brand-soft)] text-[var(--brand)]">
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
            <div class="text-[10px] font-bold uppercase tracking-[.16em] text-[var(--brand)]">Just landed</div>
            <h2 class="mt-1 text-2xl font-black tracking-tight text-[var(--ink)]">New arrivals</h2>
        </div>
        <a href="{{ route('storefront.shop', ['sort' => 'newest']) }}" class="text-[12px] font-semibold text-[var(--brand)] hover:underline">Shop new →</a>
    </div>
    <div class="grid grid-cols-2 gap-2.5 sm:grid-cols-3 lg:grid-cols-5">
        @foreach($newArrivals->take(5) as $product)
            @include(nexus_partial('partials.product-card'), ['product' => $product])
        @endforeach
    </div>
</section>
@endif
@endsection
