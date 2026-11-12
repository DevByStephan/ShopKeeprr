@extends('theme.layouts.app')

@section('title', isset($category) ? $category->name.' — '.nexus_setting('store.name') : 'Shop — '.nexus_setting('store.name'))
@section('description', isset($category) ? $category->description : 'Browse the full collection.')

@section('content')
<div class="border-b border-[var(--line)] bg-[var(--surface-2)]">
    <div class="container-wide py-10 sm:py-14">
        <nav class="text-[11px] font-semibold uppercase tracking-[.12em] text-[var(--muted)]">
            <a href="{{ route('storefront.home') }}" class="hover:text-[var(--link)]">Home</a>
            <span class="px-2">/</span>
            <span class="text-[var(--muted)]">{{ isset($category) ? $category->name : 'Shop' }}</span>
        </nav>
        <h1 class="mt-4 font-display text-4xl tracking-tight sm:text-5xl">{{ isset($category) ? $category->name : 'The full collection' }}</h1>
        <p class="mt-3 max-w-xl text-sm leading-6 text-[var(--muted)]">{{ isset($category) && $category->description ? $category->description : 'Everything currently on the shelf, from everyday essentials to the pieces we cannot stop talking about.' }}</p>
    </div>
</div>

<div class="container-wide grid gap-8 py-10 lg:grid-cols-[220px_1fr]">
    <aside class="space-y-6">
        <form action="{{ route('storefront.shop') }}" method="get" class="space-y-4 rounded-2xl border border-[var(--line)] bg-white p-5">
            <div>
                <label class="label" for="q">Search</label>
                <input id="q" name="q" value="{{ request('q') }}" class="input" placeholder="Search products">
            </div>
            <div>
                <label class="label" for="category">Category</label>
                <select id="category" name="category" class="select">
                    <option value="">All categories</option>
                    @foreach($categories as $item)
                        <option value="{{ $item->slug }}" @selected(request('category') === $item->slug)>{{ $item->name }} ({{ $item->products_count }})</option>
                    @endforeach
                </select>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div><label class="label" for="min_price">Min</label><input id="min_price" name="min_price" type="number" min="0" step="1" value="{{ request('min_price') }}" class="input"></div>
                <div><label class="label" for="max_price">Max</label><input id="max_price" name="max_price" type="number" min="0" step="1" value="{{ request('max_price') }}" class="input"></div>
            </div>
            <button class="btn-primary w-full">Apply filters</button>
            @if(request()->hasAny(['q','category','min_price','max_price','sort']))
                <a href="{{ route('storefront.shop') }}" class="block text-center text-xs font-semibold text-[var(--muted)] hover:text-[var(--link)]">Clear all</a>
            @endif
        </form>

        <div class="rounded-2xl border border-[var(--line)] bg-[var(--brand)] p-5 text-white">
            <div class="text-[10px] font-bold uppercase tracking-[.16em] text-white/50">Need a hand?</div>
            <p class="mt-2 text-sm leading-6 text-white/80">Not sure which piece fits? Our team answers every message within one business day.</p>
            <a href="mailto:{{ nexus_setting('store.email') }}" class="mt-4 inline-block text-xs font-bold text-white underline underline-offset-4">Talk to us</a>
        </div>
    </aside>

    <div>
        <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
            <div class="text-xs font-semibold text-[#7a8982]">{{ $products->total() }} {{ Str::plural('product', $products->total()) }}</div>
            <form action="{{ isset($category) ? route('storefront.category', $category->slug) : route('storefront.shop') }}" method="get" class="flex items-center gap-2">
                @foreach(request()->except('sort','page') as $key => $value)
                    <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                @endforeach
                <label for="sort" class="text-[11px] font-bold uppercase tracking-[.1em] text-[var(--muted)]">Sort</label>
                <select id="sort" name="sort" class="select !w-auto !py-2 text-xs" onchange="this.form.submit()">
                    <option value="newest" @selected(request('sort','newest') === 'newest')>Newest</option>
                    <option value="price_asc" @selected(request('sort') === 'price_asc')>Price: low to high</option>
                    <option value="price_desc" @selected(request('sort') === 'price_desc')>Price: high to low</option>
                    <option value="name" @selected(request('sort') === 'name')>Name</option>
                </select>
            </form>
        </div>

        @if($products->isEmpty())
            <div class="rounded-2xl border border-dashed border-[var(--line)] bg-white p-12 text-center">
                <div class="mx-auto grid h-12 w-12 place-items-center rounded-full bg-[var(--surface-2)] text-[var(--muted)]">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                </div>
                <h2 class="mt-4 font-display text-2xl">Nothing matched that</h2>
                <p class="mt-2 text-sm text-[var(--muted)]">Try a different search or browse the full collection.</p>
                <a href="{{ route('storefront.shop') }}" class="btn-primary mt-6">View all products</a>
            </div>
        @else
            <div class="grid grid-cols-2 gap-x-5 gap-y-9 lg:grid-cols-3">
                @foreach($products as $product)
                    @include(nexus_partial('partials.product-card'), ['product' => $product])
                @endforeach
            </div>
            <div class="mt-10">{{ $products->links() }}</div>
        @endif
    </div>
</div>
@endsection
