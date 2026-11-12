@extends('theme.layouts.app')

@section('title', 'My wishlist — '.nexus_setting('store.name'))

@section('content')
<div class="container-wide py-10 sm:py-14">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <div class="text-[10px] font-bold uppercase tracking-[.18em] text-[var(--link)]">My account</div>
            <h1 class="mt-2 font-display text-4xl tracking-tight">Saved for later.</h1>
            <p class="mt-1 max-w-xl text-sm text-[var(--muted)]">Everything you have saved, kept private to your account. Prices and availability update automatically.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('storefront.account.orders') }}" class="btn-secondary !px-4 !py-2 text-xs">Orders</a>
            <a href="{{ route('storefront.shop') }}" class="btn-secondary !px-4 !py-2 text-xs">Keep shopping</a>
        </div>
    </div>

    @if(session('status'))
        <div class="mt-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">{{ session('status') }}</div>
    @endif

    @if($items->isEmpty())
        <div class="mt-8 rounded-2xl border border-dashed border-[var(--line)] bg-white p-12 text-center">
            <div class="mx-auto grid h-12 w-12 place-items-center rounded-full bg-[var(--surface-2)] text-[var(--muted)]">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path d="M12 20.5S3.5 15 3.5 9.2A4.7 4.7 0 0 1 12 6a4.7 4.7 0 0 1 8.5 3.2C20.5 15 12 20.5 12 20.5Z"/></svg>
            </div>
            <h2 class="mt-4 font-display text-2xl">Nothing saved yet</h2>
            <p class="mt-1 text-sm text-[var(--muted)]">Use the save button on any product you are considering.</p>
            <a href="{{ route('storefront.shop') }}" class="btn-primary mt-5">Browse the shop</a>
        </div>
    @else
        <div class="mt-8 grid grid-cols-2 gap-x-5 gap-y-9 sm:grid-cols-3 lg:grid-cols-4">
            @foreach($items as $item)
                @if($item->product)
                    <div>
                        @include(nexus_partial('partials.product-card'), ['product' => $item->product])
                        <form method="post" action="{{ route('wishlist.destroy', $item->product->slug) }}" class="mt-2">
                            @csrf
                            @method('DELETE')
                            <button class="text-[10px] font-semibold text-[var(--muted)] hover:text-rose-600">Remove</button>
                        </form>
                    </div>
                @endif
            @endforeach
        </div>
        <div class="mt-10">{{ $items->links() }}</div>
    @endif
</div>
@endsection
