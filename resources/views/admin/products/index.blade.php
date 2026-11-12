@extends('admin.layouts.app')

@section('title', 'Products')
@section('eyebrow', 'Sell')
@section('heading', 'Products')

@section('content')
<div class="mb-5 flex flex-wrap items-center justify-between gap-3">
    <form method="get" class="flex flex-wrap items-end gap-3">
        <div>
            <label class="label" for="q">Search</label>
            <input id="q" name="q" value="{{ request('q') }}" class="input !w-48" placeholder="Name or SKU">
        </div>
        <div>
            <label class="label" for="category">Category</label>
            <select id="category" name="category" class="select !w-44">
                <option value="">All categories</option>
                @foreach($categories as $category)
                    <option value="{{ $category->id }}" @selected((string) request('category') === (string) $category->id)>{{ $category->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="label" for="status">Status</label>
            <select id="status" name="status" class="select !w-36">
                <option value="">All</option>
                <option value="active" @selected(request('status') === 'active')>Active</option>
                <option value="draft" @selected(request('status') === 'draft')>Draft</option>
            </select>
        </div>
        <button class="btn-secondary !px-5 !py-2.5 text-xs">Filter</button>
    </form>
    <a href="{{ route('admin.products.create') }}" class="btn-primary !px-5 !py-2.5 text-xs">+ New product</a>
</div>

<div class="table-wrap">
    <table class="data-table">
        <thead><tr><th>Product</th><th>Category</th><th>Type</th><th>Stock</th><th>Price</th><th>Status</th><th></th></tr></thead>
        <tbody>
            @forelse($products as $product)
                <tr>
                    <td>
                        <div class="flex items-center gap-3">
                            <div class="h-11 w-11 shrink-0 overflow-hidden rounded-lg bg-[#eef2ef]">
                                @if($product->imageUrl())
                                    <img src="{{ $product->imageUrl() }}" alt="" class="h-full w-full object-cover">
                                @else
                                    <img src="{{ asset('images/placeholder.svg') }}" alt="" class="h-full w-full object-cover">
                                @endif
                            </div>
                            <div class="min-w-0">
                                <a href="{{ route('admin.products.edit', $product) }}" class="block truncate font-semibold hover:text-[#0f766e]">{{ $product->name }}</a>
                                <div class="text-[11px] text-[#8a9892]">{{ $product->sku ?: 'No SKU' }}{{ $product->is_featured ? ' · featured' : '' }}</div>
                            </div>
                        </div>
                    </td>
                    <td class="text-xs">{{ $product->category?->name ?? 'Uncategorised' }}</td>
                    <td class="text-xs capitalize">{{ $product->type }}</td>
                    <td>
                        @if(! $product->manage_stock)
                            <span class="badge badge-slate">Untracked</span>
                        @elseif($product->stock_quantity <= 0)
                            <span class="badge badge-rose">Out of stock</span>
                        @elseif($product->stock_quantity <= $product->low_stock_threshold)
                            <span class="badge badge-amber">{{ $product->stock_quantity }} left</span>
                        @else
                            <span class="text-xs">{{ $product->stock_quantity }}</span>
                        @endif
                    </td>
                    <td class="text-sm font-semibold">{{ nexus_money($product->price) }}</td>
                    <td>
                        <div class="flex flex-wrap gap-1.5">
                            <span class="badge {{ $product->is_active ? 'badge-emerald' : 'badge-slate' }}">{{ $product->is_active ? 'Active' : 'Draft' }}</span>
                            @php($modeState = $mode ? $product->modeStates->first() : null)
                            @if($modeState && ! $modeState->is_visible)
                                <span class="badge badge-amber">{{ $modeState->origin === 'manual' ? 'Hidden' : 'Archived' }} in {{ $mode->label() }}</span>
                            @endif
                        </div>
                    </td>
                    <td class="text-right">
                        <div class="flex items-center justify-end gap-3">
                            <a href="{{ route('storefront.product', $product->slug) }}" target="_blank" class="text-[11px] font-semibold text-[#687771] hover:text-[#0f766e]">View</a>
                            <a href="{{ route('admin.products.edit', $product) }}" class="text-[11px] font-bold text-[#0f766e]">Edit</a>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="py-10 text-center text-sm text-[#8a9892]">No products yet. <a href="{{ route('admin.products.create') }}" class="font-bold text-[#0f766e]">Add your first product</a>.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="mt-5">{{ $products->links() }}</div>
@endsection
