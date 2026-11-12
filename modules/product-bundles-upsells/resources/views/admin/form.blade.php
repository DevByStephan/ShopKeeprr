@extends('admin.layouts.app')

@section('title', $bundle->exists ? 'Edit '.$bundle->name : 'New bundle')
@section('eyebrow', 'Bundles')
@section('heading', $bundle->exists ? 'Edit bundle' : 'New bundle')

@section('content')
@php
    $isEdit = $bundle->exists;
    $rows = old('items', collect($selected)->map(fn ($quantity, $id) => ['product_id' => (string) $id, 'quantity' => $quantity])->values()->all());
    if (! $rows) {
        $rows = [['product_id' => '', 'quantity' => 1], ['product_id' => '', 'quantity' => 1]];
    }
@endphp

<form method="post" action="{{ $isEdit ? route('admin.bundles.update', $bundle) : route('admin.bundles.store') }}" class="space-y-5">
    @csrf
    @if($isEdit) @method('PUT') @endif

    <div class="grid gap-5 xl:grid-cols-[1.5fr_1fr]">
        <div class="card card-pad">
            <h2 class="font-display text-xl">Bundle details</h2>
            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="label" for="name">Name</label>
                    <input id="name" name="name" value="{{ old('name', $bundle->name) }}" class="input" required placeholder="The complete starter set">
                </div>
                <div class="sm:col-span-2">
                    <label class="label" for="slug">URL slug</label>
                    <input id="slug" name="slug" value="{{ old('slug', $bundle->slug) }}" class="input" placeholder="generated-from-name">
                </div>
                <div class="sm:col-span-2">
                    <label class="label" for="description">Description</label>
                    <textarea id="description" name="description" rows="2" class="textarea">{{ old('description', $bundle->description) }}</textarea>
                </div>
                <div>
                    <label class="label" for="discount_type">Discount type</label>
                    <select id="discount_type" name="discount_type" class="select">
                        <option value="percentage" @selected(old('discount_type', $bundle->discount_type) === 'percentage')>Percentage off</option>
                        <option value="fixed" @selected(old('discount_type', $bundle->discount_type) === 'fixed')>Fixed amount off</option>
                    </select>
                </div>
                <div>
                    <label class="label" for="discount_value">Discount value</label>
                    <input id="discount_value" type="number" min="0" step="0.01" name="discount_value" value="{{ old('discount_value', $bundle->discount_value) }}" class="input" required>
                </div>
            </div>
        </div>

        <div class="card card-pad">
            <h2 class="font-display text-xl">Publish</h2>
            <label class="mt-4 flex items-center gap-2.5 text-sm"><input type="checkbox" name="is_active" value="1" class="accent-[#0f766e]" @checked(old('is_active', $bundle->is_active))> Active on product pages</label>
            <div class="mt-5 flex gap-2">
                <button class="btn-primary flex-1">{{ $isEdit ? 'Save bundle' : 'Create bundle' }}</button>
                <a href="{{ route('admin.bundles.index') }}" class="btn-secondary">Cancel</a>
            </div>
            @if($isEdit)
                <div class="mt-4 border-t border-[#e3e9e5] pt-4">
                    <button type="submit" form="delete-bundle" class="text-xs font-bold text-rose-600" onclick="return confirm('Delete this bundle? Products are not affected.')">Delete bundle</button>
                </div>
            @endif
        </div>
    </div>

    <div class="card card-pad">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div>
                <h2 class="font-display text-xl">Products in this bundle</h2>
                <p class="text-xs text-[#8a9892]">At least two products. Stock is checked against every component before the bundle can be added to a cart.</p>
            </div>
            <button type="button" class="btn-secondary !px-4 !py-2 text-xs" onclick="addBundleRow()">+ Add product</button>
        </div>
        <div id="bundle-rows" class="mt-4 space-y-2">
            @foreach($rows as $index => $row)
                <div class="grid gap-2 rounded-xl border border-[#e3e9e5] bg-[#fafcfb] p-3 sm:grid-cols-[1fr_120px_40px]">
                    <select name="items[{{ $index }}][product_id]" class="select">
                        <option value="">Choose product</option>
                        @foreach($products as $product)
                            <option value="{{ $product->id }}" @selected((string) ($row['product_id'] ?? '') === (string) $product->id)>{{ $product->name }} — {{ nexus_money($product->price) }}</option>
                        @endforeach
                    </select>
                    <input type="number" min="1" max="50" name="items[{{ $index }}][quantity]" value="{{ $row['quantity'] ?? 1 }}" class="input" placeholder="Qty">
                    <button type="button" class="rounded-lg text-[#a4b0a9] hover:bg-rose-50 hover:text-rose-600" onclick="this.parentElement.remove()" aria-label="Remove">×</button>
                </div>
            @endforeach
        </div>
        <template id="bundle-row-template">
            <div class="grid gap-2 rounded-xl border border-[#e3e9e5] bg-[#fafcfb] p-3 sm:grid-cols-[1fr_120px_40px]">
                <select name="items[__INDEX__][product_id]" class="select">
                    <option value="">Choose product</option>
                    @foreach($products as $product)
                        <option value="{{ $product->id }}">{{ $product->name }} — {{ nexus_money($product->price) }}</option>
                    @endforeach
                </select>
                <input type="number" min="1" max="50" name="items[__INDEX__][quantity]" value="1" class="input" placeholder="Qty">
                <button type="button" class="rounded-lg text-[#a4b0a9] hover:bg-rose-50 hover:text-rose-600" onclick="this.parentElement.remove()" aria-label="Remove">×</button>
            </div>
        </template>
    </div>
</form>

@if($isEdit)
    <form id="delete-bundle" method="post" action="{{ route('admin.bundles.destroy', $bundle) }}" class="hidden">
        @csrf
        @method('DELETE')
    </form>
@endif
@endsection

@push('scripts')
<script>
    let bundleRow = {{ count($rows) }};
    function addBundleRow() {
        const template = document.getElementById('bundle-row-template');
        document.getElementById('bundle-rows').insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__INDEX__', bundleRow++));
    }
</script>
@endpush
