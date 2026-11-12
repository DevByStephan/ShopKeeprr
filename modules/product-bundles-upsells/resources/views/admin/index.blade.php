@extends('admin.layouts.app')

@section('title', 'Bundles & upsells')
@section('eyebrow', 'Plugins')
@section('heading', 'Bundles & upsells')

@section('content')
<div class="mb-5 flex flex-wrap items-center justify-between gap-3">
    <p class="max-w-2xl text-sm leading-6 text-[#687771]">Group products into a set and offer a percentage or fixed discount. Bundles appear on every component product page and add all items to the cart in one click.</p>
    <a href="{{ route('admin.bundles.create') }}" class="btn-primary !px-5 !py-2.5 text-xs">+ New bundle</a>
</div>

<div class="table-wrap">
    <table class="data-table">
        <thead><tr><th>Bundle</th><th>Products</th><th>Discount</th><th>Status</th><th></th></tr></thead>
        <tbody>
            @forelse($bundles as $bundle)
                <tr>
                    <td>
                        <a href="{{ route('admin.bundles.edit', $bundle) }}" class="font-semibold hover:text-[#0f766e]">{{ $bundle->name }}</a>
                        <div class="text-[11px] text-[#8a9892]">/{{ $bundle->slug }}</div>
                    </td>
                    <td class="text-xs">{{ $bundle->products_count }}</td>
                    <td class="text-xs">{{ $bundle->discountLabel() }}</td>
                    <td><span class="badge {{ $bundle->is_active ? 'badge-emerald' : 'badge-slate' }}">{{ $bundle->is_active ? 'Active' : 'Draft' }}</span></td>
                    <td class="text-right"><a href="{{ route('admin.bundles.edit', $bundle) }}" class="text-[11px] font-bold text-[#0f766e]">Edit</a></td>
                </tr>
            @empty
                <tr><td colspan="5" class="py-10 text-center text-sm text-[#8a9892]">No bundles yet. <a href="{{ route('admin.bundles.create') }}" class="font-bold text-[#0f766e]">Create the first set</a>.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="mt-5">{{ $bundles->links() }}</div>
@endsection
