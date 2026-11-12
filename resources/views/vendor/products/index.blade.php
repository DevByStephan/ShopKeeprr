@extends('vendor.layouts.app')

@section('title', 'Products')
@section('eyebrow', 'Catalog')
@section('heading', 'Products')

@section('content')
<div class="mb-5 flex flex-wrap items-end justify-between gap-3">
    <form method="get" class="flex items-end gap-3">
        <div><label class="label" for="q">Search</label><input id="q" name="q" value="{{ request('q') }}" class="input !w-52" placeholder="Name or SKU"></div>
        <button class="btn-secondary !px-5 !py-2.5 text-xs">Search</button>
    </form>
    <a href="{{ route('vendor.products.create') }}" class="btn-primary !px-5 !py-2.5 text-xs">+ New product</a>
</div>

<div class="table-wrap">
    <table class="data-table">
        <thead><tr><th>Product</th><th>Category</th><th>Stock</th><th>Price</th><th>Status</th><th></th></tr></thead>
        <tbody>
            @forelse($products as $product)
                <tr>
                    <td>
                        <div class="flex items-center gap-3">
                            <div class="h-10 w-10 overflow-hidden rounded-lg bg-[#eef2ef]">
                                @if($product->imageUrl())<img src="{{ $product->imageUrl() }}" alt="" class="h-full w-full object-cover">@endif
                            </div>
                            <div class="min-w-0">
                                <a href="{{ route('vendor.products.edit', $product) }}" class="block truncate font-semibold hover:text-[#0f766e]">{{ $product->name }}</a>
                                <div class="text-[11px] text-[#8a9892]">{{ $product->sku ?: 'No SKU' }}</div>
                            </div>
                        </div>
                    </td>
                    <td class="text-xs">{{ $product->category?->name ?? '—' }}</td>
                    <td class="text-xs">{{ $product->manage_stock ? $product->stock_quantity : 'Untracked' }}</td>
                    <td class="text-sm font-semibold">{{ nexus_money($product->price) }}</td>
                    <td><span class="badge {{ $product->is_active ? 'badge-emerald' : 'badge-slate' }}">{{ $product->is_active ? 'Active' : 'Draft' }}</span></td>
                    <td class="text-right"><a href="{{ route('vendor.products.edit', $product) }}" class="text-[11px] font-bold text-[#0f766e]">Edit</a></td>
                </tr>
            @empty
                <tr><td colspan="6" class="py-10 text-center text-sm text-[#8a9892]">No products yet. <a href="{{ route('vendor.products.create') }}" class="font-bold text-[#0f766e]">Add your first product</a>.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="mt-5">{{ $products->links() }}</div>
@endsection
