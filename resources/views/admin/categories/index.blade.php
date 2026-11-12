@extends('admin.layouts.app')

@section('title', 'Categories')
@section('eyebrow', 'Sell')
@section('heading', 'Categories')

@section('content')
<div class="mb-5 flex items-center justify-between gap-3">
    <p class="text-sm text-[#687771]">Organise the catalogue so shoppers can find things quickly.</p>
    <a href="{{ route('admin.categories.create') }}" class="btn-primary !px-5 !py-2.5 text-xs">+ New category</a>
</div>

<div class="table-wrap">
    <table class="data-table">
        <thead><tr><th>Category</th><th>Parent</th><th>Products</th><th>Order</th><th>Status</th><th></th></tr></thead>
        <tbody>
            @forelse($categories as $category)
                <tr>
                    <td>
                        <div class="flex items-center gap-3">
                            <div class="h-10 w-10 shrink-0 overflow-hidden rounded-lg bg-[#eef2ef]">
                                @if($category->imageUrl())
                                    <img src="{{ $category->imageUrl() }}" alt="" class="h-full w-full object-cover">
                                @else
                                    <div class="grid h-full w-full place-items-center text-[10px] font-bold text-[#8a9892]">{{ strtoupper(substr($category->name, 0, 1)) }}</div>
                                @endif
                            </div>
                            <div>
                                <a href="{{ route('admin.categories.edit', $category) }}" class="font-semibold hover:text-[#0f766e]">{{ $category->name }}</a>
                                <div class="text-[11px] text-[#8a9892]">/{{ $category->slug }}</div>
                            </div>
                        </div>
                    </td>
                    <td class="text-xs">{{ $category->parent?->name ?? '—' }}</td>
                    <td class="text-xs">{{ $category->products_count }}</td>
                    <td class="text-xs">{{ $category->sort_order }}</td>
                    <td><span class="badge {{ $category->is_active ? 'badge-emerald' : 'badge-slate' }}">{{ $category->is_active ? 'Active' : 'Hidden' }}</span></td>
                    <td class="text-right"><a href="{{ route('admin.categories.edit', $category) }}" class="text-xs font-bold text-[#0f766e]">Edit →</a></td>
                </tr>
            @empty
                <tr><td colspan="6" class="py-10 text-center text-sm text-[#8a9892]">No categories yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="mt-5">{{ $categories->links() }}</div>
@endsection
