@extends('theme.layouts.app')

@php
    $theme = nexus_theme();
    $heroProduct = $featured->first();
    $heroImage = nexus_setting('store.hero_image') ? asset(nexus_setting('store.hero_image')) : $heroProduct?->imageUrl();
    $dealProducts = $deals->concat($featured)->concat($newArrivals)->unique('id')->take(4);
    $catalog = $newArrivals->take(8);
    $freeThreshold = (float) nexus_setting('shipping.free_threshold', 75);
@endphp

@section('title', nexus_setting('store.name', 'ShopKeeprr').' — Everyday essentials, delivered')
@section('description', nexus_setting('store.tagline', 'Shop everyday essentials at everyday low prices.'))

@section('content')
<div class="bg-[var(--surface)] pb-4">
    <div class="container-wide pt-4">
        <div class="grid gap-3 lg:grid-cols-[232px_1fr]">
            <aside class="hidden overflow-hidden rounded-[var(--card-radius)] bg-[var(--card)] p-4 shadow-[0_1px_3px_rgba(15,17,17,.08)] lg:block">
                <div class="flex items-center gap-2 text-[15px] font-bold text-[var(--ink)]">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
                    Shop by department
                </div>
                <nav class="mt-2.5 grid">
                    @foreach($categories->take(7) as $department)
                        <a href="{{ route('storefront.category', $department->slug) }}" class="group flex items-center justify-between border-b border-[var(--line)] py-2 text-[13px] text-[var(--ink)] last:border-0 hover:text-[var(--link)]">
                            <span>{{ $department->name }}</span>
                            <span class="text-[11px] text-[var(--muted)] group-hover:text-[var(--link)]">{{ $department->products_count }} ›</span>
                        </a>
                    @endforeach
                    <a href="{{ route('storefront.shop') }}" class="pt-2.5 text-[12px] font-semibold text-[var(--link)]">See all departments →</a>
                </nav>
                <div class="mt-4 rounded-md bg-[var(--surface-2)] p-3.5">
                    <div class="text-[12px] font-bold text-[var(--ink)]">Free delivery over {{ nexus_money($freeThreshold) }}</div>
                    <p class="mt-1 text-[11px] leading-5 text-[var(--muted)]">Stock up on the essentials and we will cover the shipping.</p>
                </div>
            </aside>

            <div class="grid gap-3 md:grid-cols-[1.55fr_.85fr]">
                <section class="relative overflow-hidden rounded-[var(--card-radius)] bg-[linear-gradient(120deg,#1c2b3a_0%,#23384d_52%,#315068_100%)] p-6 text-white sm:p-8 lg:p-10">
                    <div class="relative z-10 grid items-center gap-6 sm:grid-cols-[1.3fr_.7fr]">
                        <div>
                            <div class="inline-flex rounded-sm bg-[var(--accent)] px-2 py-1 text-[10px] font-bold uppercase tracking-[.14em] text-[var(--ink)]">{{ $theme['tagline'] ?? 'Everyday low prices' }}</div>
                            <h1 class="mt-4 text-3xl font-bold leading-[1.1] tracking-tight sm:text-4xl lg:text-[42px]">Everything you need,<br>delivered fast.</h1>
                            <p class="mt-3 max-w-sm text-[13px] leading-6 text-white/70">Top-quality everyday essentials, honest prices and free delivery on orders over {{ nexus_money($freeThreshold) }}.</p>
                            <div class="mt-5 flex flex-wrap gap-2.5">
                                <a href="{{ route('storefront.shop') }}" class="inline-flex items-center rounded-[var(--btn-radius)] bg-[var(--accent)] px-5 py-2.5 text-[13px] font-bold text-[var(--ink)] transition hover:brightness-95">Start shopping</a>
                                <a href="{{ route('storefront.shop', ['sort' => 'newest']) }}" class="inline-flex items-center rounded-[var(--btn-radius)] border border-white/30 px-5 py-2.5 text-[13px] font-bold text-white transition hover:bg-white/10">New arrivals</a>
                            </div>
                        </div>
                        @if($heroImage)
                            <div class="hidden items-end justify-center sm:flex">
                                <img src="{{ $heroImage }}" alt="" class="max-h-[310px] w-auto object-contain drop-shadow-2xl">
                            </div>
                        @endif
                    </div>
                    <div class="absolute -left-16 -top-16 h-52 w-52 rounded-full bg-[var(--accent)]/10 blur-2xl"></div>
                </section>

                <div class="grid gap-3 sm:grid-cols-2 md:grid-cols-1">
                    <a href="{{ route('storefront.shop', ['sort' => 'price_asc']) }}" class="group flex flex-col justify-between overflow-hidden rounded-[var(--card-radius)] bg-[var(--card)] p-5 shadow-[0_1px_3px_rgba(15,17,17,.08)]">
                        <div>
                            <div class="text-[10px] font-bold uppercase tracking-[.14em] text-[var(--sale)]">Budget picks</div>
                            <div class="mt-1.5 text-lg font-bold leading-tight text-[var(--ink)]">Everyday value, without the compromise.</div>
                        </div>
                        <div class="mt-4 flex items-center justify-between">
                            <span class="text-[12px] font-semibold text-[var(--link)] group-hover:underline">Shop value picks</span>
                            <span class="grid h-8 w-8 place-items-center rounded-full bg-[var(--surface-2)] text-[var(--ink)]">→</span>
                        </div>
                    </a>
                    <a href="{{ route('storefront.shop') }}" class="group flex flex-col justify-between overflow-hidden rounded-[var(--card-radius)] bg-[var(--card)] p-5 shadow-[0_1px_3px_rgba(15,17,17,.08)]">
                        <div>
                            <div class="text-[10px] font-bold uppercase tracking-[.14em] text-[var(--link)]">Top categories</div>
                            <div class="mt-2 flex flex-wrap gap-1.5">
                                @foreach($categories->take(4) as $department)
                                    <span class="rounded bg-[var(--surface-2)] px-2 py-1 text-[11px] font-medium text-[var(--ink)]">{{ $department->name }}</span>
                                @endforeach
                            </div>
                        </div>
                        <div class="mt-4 flex items-center justify-between">
                            <span class="text-[12px] font-semibold text-[var(--link)] group-hover:underline">Browse departments</span>
                            <span class="grid h-8 w-8 place-items-center rounded-full bg-[var(--surface-2)] text-[var(--ink)]">→</span>
                        </div>
                    </a>
                </div>
            </div>
        </div>

        <section class="mt-4 overflow-hidden rounded-[var(--card-radius)] bg-[var(--card)] shadow-[0_1px_3px_rgba(15,17,17,.08)]">
            <div class="flex flex-wrap items-end justify-between gap-2 border-b border-[var(--line)] px-5 py-4">
                <div>
                    <h2 class="text-xl font-bold tracking-tight text-[var(--ink)]">Today's deals</h2>
                    <p class="mt-0.5 text-[12px] text-[var(--muted)]">Limited-time prices on customer favourites.</p>
                </div>
                <a href="{{ route('storefront.shop', ['sort' => 'price_asc']) }}" class="text-[12px] font-semibold text-[var(--link)] hover:underline">See all deals →</a>
            </div>
            @if($dealProducts->isNotEmpty())
                <div class="grid grid-cols-2 gap-3 p-4 sm:grid-cols-4">
                    @foreach($dealProducts as $product)
                        @include(nexus_partial('partials.product-card'), ['product' => $product])
                    @endforeach
                </div>
            @endif
        </section>
    </div>
</div>

<section class="container-wide py-7">
    <div class="mb-4 flex items-end justify-between gap-3">
        <div>
            <div class="text-[10px] font-bold uppercase tracking-[.16em] text-[var(--sale)]">Recommended for you</div>
            <h2 class="mt-1 text-2xl font-bold tracking-tight text-[var(--ink)]">Featured in the shop</h2>
        </div>
        <a href="{{ route('storefront.shop') }}" class="text-[12px] font-semibold text-[var(--link)] hover:underline">View all →</a>
    </div>
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
        @foreach($featured as $product)
            @include(nexus_partial('partials.product-card'), ['product' => $product])
        @endforeach
    </div>
</section>

<section class="container-wide py-3">
    <div class="grid gap-3 rounded-[var(--card-radius)] bg-[var(--card)] p-5 shadow-[0_1px_3px_rgba(15,17,17,.08)] sm:grid-cols-2 lg:grid-cols-4">
        @foreach([
            ['Free delivery', 'On orders over '.nexus_money($freeThreshold)],
            ['Secure payments', 'Encrypted checkout, every time'],
            ['Easy returns', '30 days to change your mind'],
            ['Real support', 'A person, not a bot'],
        ] as [$title, $copy])
            <div class="flex items-start gap-3 border-[var(--line)] lg:border-r lg:last:border-0 lg:pr-4">
                <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-[var(--brand-soft)] text-[var(--link)]">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20 7 9.5 17.5 4 12"/></svg>
                </span>
                <div><div class="text-[13px] font-bold text-[var(--ink)]">{{ $title }}</div><div class="mt-0.5 text-[11px] leading-5 text-[var(--muted)]">{{ $copy }}</div></div>
            </div>
        @endforeach
    </div>
</section>

<section class="container-wide py-7">
    <div class="mb-4 flex items-end justify-between gap-3">
        <div>
            <div class="text-[10px] font-bold uppercase tracking-[.16em] text-[var(--link)]">Just added</div>
            <h2 class="mt-1 text-2xl font-bold tracking-tight text-[var(--ink)]">New arrivals</h2>
        </div>
        <a href="{{ route('storefront.shop', ['sort' => 'newest']) }}" class="text-[12px] font-semibold text-[var(--link)] hover:underline">Shop new →</a>
    </div>
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
        @foreach($catalog as $product)
            @include(nexus_partial('partials.product-card'), ['product' => $product])
        @endforeach
    </div>
</section>
@endsection
