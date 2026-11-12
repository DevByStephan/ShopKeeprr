@extends('vendor.layouts.app')

@section('title', $product->exists ? 'Edit '.$product->name : 'New product')
@section('eyebrow', 'Catalog')
@section('heading', $product->exists ? 'Edit product' : 'New product')

@section('content')
<form method="post" action="{{ $product->exists ? route('vendor.products.update', $product) : route('vendor.products.store') }}" enctype="multipart/form-data" class="grid gap-5 xl:grid-cols-[1.4fr_.8fr]">
    @csrf
    @if($product->exists) @method('PUT') @endif

    <div class="space-y-5">
        <div class="card card-pad">
            <h2 class="font-display text-xl">Product details</h2>
            <div class="mt-4 space-y-4">
                <div><label class="label" for="name">Name</label><input id="name" name="name" value="{{ old('name', $product->name) }}" class="input" required></div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div><label class="label" for="slug">URL slug</label><input id="slug" name="slug" value="{{ old('slug', $product->slug) }}" class="input" placeholder="auto-generated"></div>
                    <div><label class="label" for="sku">SKU</label><input id="sku" name="sku" value="{{ old('sku', $product->sku) }}" class="input"></div>
                </div>
                <div><label class="label" for="short_description">Short description</label><textarea id="short_description" name="short_description" rows="2" class="textarea">{{ old('short_description', $product->short_description) }}</textarea></div>
                <div><label class="label" for="description">Full description</label><textarea id="description" name="description" rows="7" class="textarea">{{ old('description', $product->description) }}</textarea></div>
            </div>
        </div>

        <div class="card card-pad">
            <h2 class="font-display text-xl">Pricing and stock</h2>
            <div class="mt-4 grid gap-4 sm:grid-cols-3">
                <div><label class="label" for="price">Price</label><input id="price" type="number" step="0.01" min="0" name="price" value="{{ old('price', $product->price) }}" class="input" required></div>
                <div><label class="label" for="compare_at_price">Compare at</label><input id="compare_at_price" type="number" step="0.01" min="0" name="compare_at_price" value="{{ old('compare_at_price', $product->compare_at_price) }}" class="input"></div>
                <div><label class="label" for="weight">Weight (kg)</label><input id="weight" type="number" step="0.001" min="0" name="weight" value="{{ old('weight', $product->weight) }}" class="input"></div>
            </div>
            <label class="mt-4 flex items-center gap-2.5 text-sm"><input type="checkbox" name="manage_stock" value="1" class="accent-[#0f766e]" @checked(old('manage_stock', $product->manage_stock ?? true))> Track stock for this product</label>
            <div class="mt-4 grid grid-cols-2 gap-3">
                <div><label class="label" for="stock_quantity">In stock</label><input id="stock_quantity" type="number" min="0" name="stock_quantity" value="{{ old('stock_quantity', $product->stock_quantity) }}" class="input"></div>
                <div><label class="label" for="low_stock_threshold">Low stock at</label><input id="low_stock_threshold" type="number" min="0" name="low_stock_threshold" value="{{ old('low_stock_threshold', $product->low_stock_threshold ?: 5) }}" class="input"></div>
            </div>
        </div>

        <div class="card card-pad">
            <h2 class="font-display text-xl">Image</h2>
            @if($product->imageUrl())
                <div class="mt-3 flex items-center gap-3">
                    <img src="{{ $product->imageUrl() }}" alt="" class="h-16 w-16 rounded-lg object-cover">
                    <span class="text-xs text-[#8a9892]">Uploading a new image replaces this one.</span>
                </div>
            @endif
            <input type="file" name="image" accept="image/*" class="mt-3 block w-full text-xs file:mr-3 file:rounded-full file:border-0 file:bg-[#0f766e] file:px-4 file:py-2 file:text-xs file:font-bold file:text-white">
        </div>
    </div>

    <div class="space-y-5">
        <div class="card card-pad">
            <h2 class="font-display text-lg">Visibility</h2>
            <div class="mt-3 space-y-3">
                <label class="flex items-center gap-2.5 text-sm"><input type="checkbox" name="is_active" value="1" class="accent-[#0f766e]" @checked(old('is_active', $product->is_active ?? true))> Visible in the marketplace</label>
                <label class="flex items-center gap-2.5 text-sm"><input type="checkbox" name="requires_shipping" value="1" class="accent-[#0f766e]" @checked(old('requires_shipping', $product->requires_shipping ?? true))> Requires shipping</label>
            </div>
            <div class="mt-4">
                <label class="label" for="category_id">Category</label>
                <select id="category_id" name="category_id" class="select">
                    <option value="">Uncategorised</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}" @selected((int) old('category_id', $product->category_id) === $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="card card-pad !bg-[#f7f4ec]">
            <h2 class="font-display text-lg">Commission</h2>
            <p class="mt-2 text-xs leading-5 text-[#6a6047]">Your payout is the discounted item subtotal minus commission. The store sets the commission rule and shows it on your payout page.</p>
        </div>

        <div class="flex flex-wrap gap-3">
            <button class="btn-primary !px-6 !py-3 text-xs">{{ $product->exists ? 'Save product' : 'Create product' }}</button>
            <a href="{{ route('vendor.products.index') }}" class="btn-secondary !px-6 !py-3 text-xs">Cancel</a>
        </div>

        @if($product->exists)
            <form method="post" action="{{ route('vendor.products.destroy', $product) }}" onsubmit="return confirm('Delete this product? This cannot be undone.')">
                @csrf
                @method('DELETE')
                <button class="text-xs font-bold text-rose-600">Delete product</button>
            </form>
        @endif
    </div>
</form>
@endsection
