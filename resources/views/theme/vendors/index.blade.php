@extends('theme.layouts.app')

@section('title', 'Vendors — '.nexus_setting('store.name'))
@section('description', 'Browse every independent seller in the marketplace.')

@section('content')
<div class="border-b border-[var(--line)] bg-[var(--surface-2)]">
    <div class="container-wide py-10 sm:py-14">
        <nav class="text-[11px] font-semibold uppercase tracking-[.12em] text-[var(--muted)]">
            <a href="{{ route('storefront.home') }}" class="hover:text-[var(--link)]">Home</a>
            <span class="px-2">/</span>
            <span>Vendors</span>
        </nav>
        <h1 class="mt-4 font-display text-4xl tracking-tight sm:text-5xl">Meet the makers</h1>
        <p class="mt-3 max-w-xl text-sm leading-6 text-[var(--muted)]">Every seller runs their own catalog, fulfilment and story. One checkout, one customer account, one place to find something good.</p>
    </div>
</div>

<div class="container-wide py-10">
    @if($vendors->isEmpty())
        <div class="mx-auto max-w-lg rounded-2xl border border-dashed border-[var(--line)] bg-white/60 p-10 text-center">
            <div class="font-display text-2xl">The first sellers are getting ready.</div>
            <p class="mt-2 text-sm leading-6 text-[var(--muted)]">Check back soon, or browse the full shop in the meantime.</p>
            <a href="{{ route('storefront.shop') }}" class="btn-primary mt-5">Shop all products</a>
        </div>
    @else
        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($vendors as $vendor)
                <a href="{{ route('storefront.vendors.show', $vendor->slug) }}" class="group flex flex-col rounded-2xl border border-[var(--line)] bg-white p-6 transition hover:-translate-y-0.5 hover:shadow-[0_20px_45px_-30px_rgba(16,37,34,.5)]">
                    <span class="grid h-12 w-12 place-items-center rounded-2xl bg-[var(--brand)] text-sm font-bold text-white">{{ $vendor->initials() }}</span>
                    <h2 class="mt-4 font-display text-2xl leading-tight">{{ $vendor->name }}</h2>
                    <p class="mt-2 line-clamp-3 text-sm leading-6 text-[var(--muted)]">{{ $vendor->description ?: 'An independent seller in our marketplace.' }}</p>
                    <div class="mt-auto flex items-center justify-between pt-6 text-[10px] font-bold uppercase tracking-[.12em] text-[var(--muted)]">
                        <span>{{ $vendor->products_count }} {{ Str::plural('product', $vendor->products_count) }}</span>
                        <span class="text-[var(--link)]">Visit store →</span>
                    </div>
                </a>
            @endforeach
        </div>
        <div class="mt-10">{{ $vendors->links() }}</div>
    @endif
</div>
@endsection
