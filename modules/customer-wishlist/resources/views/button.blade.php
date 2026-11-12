@auth
    @php
        $wishlisted = \Modules\Wishlist\Models\WishlistItem::query()
            ->where('user_id', auth()->id())
            ->where('product_id', $product->id)
            ->exists();
    @endphp
    <form method="post" action="{{ route('wishlist.toggle', $product->slug) }}" class="{{ $class ?? 'mt-3' }}">
        @csrf
        <button class="inline-flex items-center gap-2 rounded-full border border-[var(--line)] bg-white px-4 py-2 text-[11px] font-bold text-[var(--ink)] transition hover:border-[var(--brand)] hover:text-[var(--link)]">
            <svg class="h-4 w-4" fill="{{ $wishlisted ? 'currentColor' : 'none' }}" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path d="M12 20.5S3.5 15 3.5 9.2A4.7 4.7 0 0 1 12 6a4.7 4.7 0 0 1 8.5 3.2C20.5 15 12 20.5 12 20.5Z"/></svg>
            {{ $wishlisted ? 'Saved to wishlist' : 'Save to wishlist' }}
        </button>
    </form>
@endauth
