@extends('admin.layouts.app')

@section('title', $product->exists ? 'Edit '.$product->name : 'New product')
@section('eyebrow', 'Products')
@section('heading', $product->exists ? 'Edit product' : 'New product')

@section('content')
@php
    $isEdit = $product->exists;
    $optionsText = fn ($options) => collect($options ?: [])->map(fn ($value, $key) => $key.': '.$value)->implode("\n");
@endphp

<form method="post" action="{{ $isEdit ? route('admin.products.update', $product) : route('admin.products.store') }}" enctype="multipart/form-data" class="space-y-5">
    @csrf
    @if($isEdit) @method('PUT') @endif

    <div class="grid gap-5 xl:grid-cols-[1.5fr_1fr]">
        <div class="space-y-5">
            <div class="card card-pad">
                <h2 class="font-display text-xl">Product details</h2>
                <div class="mt-4 space-y-4">
                    <div>
                        <label class="label" for="name">Name</label>
                        <input id="name" name="name" value="{{ old('name', $product->name) }}" class="input" required>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="label" for="slug">URL slug</label>
                            <input id="slug" name="slug" value="{{ old('slug', $product->slug) }}" class="input" placeholder="auto-generated">
                        </div>
                        <div>
                            <label class="label" for="sku">SKU</label>
                            <input id="sku" name="sku" value="{{ old('sku', $product->sku) }}" class="input">
                        </div>
                    </div>
                    <div>
                        <label class="label" for="short_description">Short description</label>
                        <textarea id="short_description" name="short_description" rows="2" class="textarea">{{ old('short_description', $product->short_description) }}</textarea>
                    </div>
                    <div>
                        <label class="label" for="description">Full description</label>
                        <textarea id="description" name="description" rows="7" class="textarea">{{ old('description', $product->description) }}</textarea>
                    </div>
                </div>
            </div>

            <div class="card card-pad">
                <h2 class="font-display text-xl">Pricing</h2>
                <div class="mt-4 grid gap-4 sm:grid-cols-3">
                    <div>
                        <label class="label" for="price">Price</label>
                        <input id="price" type="number" step="0.01" min="0" name="price" value="{{ old('price', $product->price) }}" class="input" required>
                    </div>
                    <div>
                        <label class="label" for="compare_at_price">Compare at</label>
                        <input id="compare_at_price" type="number" step="0.01" min="0" name="compare_at_price" value="{{ old('compare_at_price', $product->compare_at_price) }}" class="input">
                    </div>
                    <div>
                        <label class="label" for="cost_price">Cost price</label>
                        <input id="cost_price" type="number" step="0.01" min="0" name="cost_price" value="{{ old('cost_price', $product->cost_price) }}" class="input">
                    </div>
                </div>
                <div class="mt-4 grid gap-4 sm:grid-cols-3">
                    <div>
                        <label class="label" for="tax_class">Tax class</label>
                        <select id="tax_class" name="tax_class" class="select">
                            @foreach(['standard' => 'Standard', 'reduced' => 'Reduced', 'zero' => 'Zero rated'] as $value => $label)
                                <option value="{{ $value }}" @selected(old('tax_class', $product->tax_class ?: 'standard') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="label" for="weight">Weight (kg)</label>
                        <input id="weight" type="number" step="0.001" min="0" name="weight" value="{{ old('weight', $product->weight) }}" class="input">
                    </div>
                    <div>
                        <label class="label" for="min_order_quantity">Min order qty</label>
                        <input id="min_order_quantity" type="number" min="1" name="min_order_quantity" value="{{ old('min_order_quantity', $product->min_order_quantity ?: 1) }}" class="input">
                    </div>
                </div>
            </div>

            <div class="card card-pad">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <h2 class="font-display text-xl">Variants</h2>
                        <p class="text-xs text-[#8a9892]">Add options like sizes or colours. Leave empty for a simple product.</p>
                    </div>
                    <button type="button" class="btn-secondary !px-4 !py-2 text-xs" onclick="addVariantRow()">+ Add variant</button>
                </div>
                <div id="variant-rows" class="mt-4 space-y-3">
                    @foreach($product->variants as $variant)
                        <div class="rounded-xl border border-[#e3e9e5] bg-[#fafcfb] p-4">
                            <input type="hidden" name="variants[{{ $variant->id }}][id]" value="{{ $variant->id }}">
                            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                                <div><label class="label">Name</label><input name="variants[{{ $variant->id }}][name]" value="{{ $variant->name }}" class="input" placeholder="Large / Sage"></div>
                                <div><label class="label">SKU</label><input name="variants[{{ $variant->id }}][sku]" value="{{ $variant->sku }}" class="input"></div>
                                <div><label class="label">Price</label><input type="number" step="0.01" min="0" name="variants[{{ $variant->id }}][price]" value="{{ $variant->price }}" class="input"></div>
                                <div><label class="label">Stock</label><input type="number" min="0" name="variants[{{ $variant->id }}][stock_quantity]" value="{{ $variant->stock_quantity }}" class="input"></div>
                                <div class="sm:col-span-2"><label class="label">Compare at</label><input type="number" step="0.01" min="0" name="variants[{{ $variant->id }}][compare_at_price]" value="{{ $variant->compare_at_price }}" class="input"></div>
                                <div class="sm:col-span-2"><label class="label">Options (one key: value per line)</label><textarea name="variants[{{ $variant->id }}][options_text]" rows="2" class="textarea !min-h-[64px]">{{ $optionsText($variant->options) }}</textarea></div>
                            </div>
                            <label class="mt-3 flex items-center gap-2 text-xs"><input type="checkbox" name="variants[{{ $variant->id }}][is_active]" value="1" class="accent-[#0f766e]" @checked($variant->is_active)> Active</label>
                        </div>
                    @endforeach
                </div>
                <template id="variant-template">
                    <div class="rounded-xl border border-[#e3e9e5] bg-[#fafcfb] p-4">
                        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                            <div><label class="label">Name</label><input name="variants[__INDEX__][name]" class="input" placeholder="Large / Sage"></div>
                            <div><label class="label">SKU</label><input name="variants[__INDEX__][sku]" class="input"></div>
                            <div><label class="label">Price</label><input type="number" step="0.01" min="0" name="variants[__INDEX__][price]" class="input"></div>
                            <div><label class="label">Stock</label><input type="number" min="0" name="variants[__INDEX__][stock_quantity]" class="input" value="0"></div>
                            <div class="sm:col-span-2"><label class="label">Compare at</label><input type="number" step="0.01" min="0" name="variants[__INDEX__][compare_at_price]" class="input"></div>
                            <div class="sm:col-span-2"><label class="label">Options (one key: value per line)</label><textarea name="variants[__INDEX__][options_text]" rows="2" class="textarea !min-h-[64px]"></textarea></div>
                        </div>
                        <label class="mt-3 flex items-center gap-2 text-xs"><input type="checkbox" name="variants[__INDEX__][is_active]" value="1" class="accent-[#0f766e]" checked> Active</label>
                    </div>
                </template>
            </div>

            <div class="card card-pad">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <h2 class="font-display text-xl">Subscription plans</h2>
                        <p class="text-xs text-[#8a9892]">Used when the store runs in subscription mode.</p>
                    </div>
                    <button type="button" class="btn-secondary !px-4 !py-2 text-xs" onclick="addPlanRow()">+ Add plan</button>
                </div>
                <div id="plan-rows" class="mt-4 space-y-3">
                    @foreach($product->subscriptionPlans as $plan)
                        <div class="rounded-xl border border-[#e3e9e5] bg-[#fafcfb] p-4">
                            <input type="hidden" name="plans[{{ $plan->id }}][id]" value="{{ $plan->id }}">
                            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                                <div><label class="label">Plan name</label><input name="plans[{{ $plan->id }}][name]" value="{{ $plan->name }}" class="input"></div>
                                <div><label class="label">Every</label><div class="flex gap-2"><input type="number" min="1" max="60" name="plans[{{ $plan->id }}][interval_count]" value="{{ $plan->interval_count }}" class="input"><select name="plans[{{ $plan->id }}][interval]" class="select">@foreach(['day' => 'Days', 'week' => 'Weeks', 'month' => 'Months', 'year' => 'Years'] as $value => $label)<option value="{{ $value }}" @selected($plan->interval === $value)>{{ $label }}</option>@endforeach</select></div></div>
                                <div><label class="label">Price</label><input type="number" step="0.01" min="0" name="plans[{{ $plan->id }}][price]" value="{{ $plan->price }}" class="input"></div>
                                <div><label class="label">Trial days</label><input type="number" min="0" max="365" name="plans[{{ $plan->id }}][trial_days]" value="{{ $plan->trial_days }}" class="input"></div>
                            </div>
                            <label class="mt-3 flex items-center gap-2 text-xs"><input type="checkbox" name="plans[{{ $plan->id }}][is_active]" value="1" class="accent-[#0f766e]" @checked($plan->is_active)> Active</label>
                        </div>
                    @endforeach
                </div>
                <template id="plan-template">
                    <div class="rounded-xl border border-[#e3e9e5] bg-[#fafcfb] p-4">
                        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                            <div><label class="label">Plan name</label><input name="plans[__INDEX__][name]" class="input" placeholder="Monthly box"></div>
                            <div><label class="label">Every</label><div class="flex gap-2"><input type="number" min="1" max="60" name="plans[__INDEX__][interval_count]" value="1" class="input"><select name="plans[__INDEX__][interval]" class="select"><option value="day">Days</option><option value="week">Weeks</option><option value="month" selected>Months</option><option value="year">Years</option></select></div></div>
                            <div><label class="label">Price</label><input type="number" step="0.01" min="0" name="plans[__INDEX__][price]" class="input"></div>
                            <div><label class="label">Trial days</label><input type="number" min="0" max="365" name="plans[__INDEX__][trial_days]" value="0" class="input"></div>
                        </div>
                        <label class="mt-3 flex items-center gap-2 text-xs"><input type="checkbox" name="plans[__INDEX__][is_active]" value="1" class="accent-[#0f766e]" checked> Active</label>
                    </div>
                </template>
            </div>

            @if($tiers->isNotEmpty())
                <div class="card card-pad">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <h2 class="font-display text-xl">Trade price breaks</h2>
                            <p class="text-xs text-[#8a9892]">Price for a tier at a minimum quantity. Overrides the tier's default discount.</p>
                        </div>
                        <button type="button" class="btn-secondary !px-4 !py-2 text-xs" onclick="addTierRow()">+ Add price</button>
                    </div>
                    <div id="tier-rows" class="mt-4 space-y-2">
                        @foreach($product->tierPrices as $tierPrice)
                            <div class="grid gap-2 rounded-xl border border-[#e3e9e5] bg-[#fafcfb] p-3 sm:grid-cols-3">
                                <select name="tier_prices[{{ $loop->index }}][tier_id]" class="select">
                                    <option value="">Choose tier</option>
                                    @foreach($tiers as $tier)<option value="{{ $tier->id }}" @selected($tierPrice->tier_id === $tier->id)>{{ $tier->name }}</option>@endforeach
                                </select>
                                <input type="number" min="1" name="tier_prices[{{ $loop->index }}][min_quantity]" value="{{ $tierPrice->min_quantity }}" class="input" placeholder="Min qty">
                                <input type="number" step="0.01" min="0" name="tier_prices[{{ $loop->index }}][price]" value="{{ $tierPrice->price }}" class="input" placeholder="Price">
                            </div>
                        @endforeach
                    </div>
                    <template id="tier-template">
                        <div class="grid gap-2 rounded-xl border border-[#e3e9e5] bg-[#fafcfb] p-3 sm:grid-cols-3">
                            <select name="tier_prices[__INDEX__][tier_id]" class="select"><option value="">Choose tier</option>@foreach($tiers as $tier)<option value="{{ $tier->id }}">{{ $tier->name }}</option>@endforeach</select>
                            <input type="number" min="1" name="tier_prices[__INDEX__][min_quantity]" value="1" class="input" placeholder="Min qty">
                            <input type="number" step="0.01" min="0" name="tier_prices[__INDEX__][price]" class="input" placeholder="Price">
                        </div>
                    </template>
                </div>
            @endif
        </div>

        <div class="space-y-5">
            <div class="card card-pad">
                <h2 class="font-display text-xl">Publish</h2>
                <div class="mt-4 space-y-3 text-sm">
                    <label class="flex items-center gap-2.5"><input type="checkbox" name="is_active" value="1" class="accent-[#0f766e]" @checked(old('is_active', $product->is_active ?? true))> Active and visible in the shop</label>
                    <label class="flex items-center gap-2.5"><input type="checkbox" name="is_featured" value="1" class="accent-[#0f766e]" @checked(old('is_featured', $product->is_featured))> Feature on the homepage</label>
                    <label class="flex items-center gap-2.5"><input type="checkbox" name="is_wholesale_only" value="1" class="accent-[#0f766e]" @checked(old('is_wholesale_only', $product->is_wholesale_only))> Hidden from retail customers</label>
                    <label class="flex items-center gap-2.5"><input type="checkbox" name="requires_shipping" value="1" class="accent-[#0f766e]" @checked(old('requires_shipping', $product->exists ? $product->requires_shipping : true))> Requires shipping</label>
                </div>
                <div class="mt-5 flex gap-2">
                    <button class="btn-primary flex-1">{{ $isEdit ? 'Save product' : 'Create product' }}</button>
                    <a href="{{ route('admin.products.index') }}" class="btn-secondary">Cancel</a>
                </div>
                @if($isEdit)
                    <div class="mt-4 border-t border-[#e3e9e5] pt-4">
                        <button type="submit" form="delete-product" class="text-xs font-bold text-rose-600" onclick="return confirm('Delete this product? This cannot be undone.')">Delete product</button>
                    </div>
                @endif
            </div>

            @if($mode)
                <div class="card card-pad">
                    <h2 class="font-display text-xl">Store model visibility</h2>
                    <p class="mt-1 text-xs leading-5 text-[#8a9892]">Current model: <strong>{{ $mode->label() }}</strong>. Availability is enforced at the catalog, cart, checkout and API layers &mdash; not only by hiding links.</p>

                    @if($modeCompatibility)
                        <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs leading-5 text-amber-900">
                            <strong>Not sellable in this model.</strong> {{ $modeCompatibility }}
                        </div>
                    @else
                        <input type="hidden" name="manual_visibility_present" value="1">
                        <label class="mt-4 flex items-start gap-2.5 text-sm">
                            <input type="checkbox" name="hide_in_current_mode" value="1" class="mt-0.5 accent-[#0f766e]" @checked($modeState && ! $modeState->is_visible && $modeState->origin === 'manual')>
                            <span>Hide from the storefront in {{ $mode->label() }} mode<br><span class="text-[11px] text-[#8a9892]">This merchant choice is preserved across model switches. Automatic archives are tracked separately.</span></span>
                        </label>
                    @endif

                    @if($modeState && ! $modeState->is_visible)
                        <p class="mt-3 text-[11px] leading-5 text-[#8a9892]">Currently hidden: {{ $modeState->origin === 'manual' ? 'merchant choice' : 'automatic model archive' }}. {{ $modeState->reason }}</p>
                    @endif
                </div>
            @endif

            <div class="card card-pad">
                <h2 class="font-display text-xl">Organisation</h2>
                <div class="mt-4">
                    <label class="label" for="category_id">Category</label>
                    <select id="category_id" name="category_id" class="select">
                        <option value="">Uncategorised</option>
                        @foreach($categories as $category)
                            <option value="{{ $category->id }}" @selected((int) old('category_id', $product->category_id) === $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="mt-4">
                    <label class="label" for="vendor_id">Seller</label>
                    @if($mode && $mode->allowsVendors())
                        <select id="vendor_id" name="vendor_id" class="select">
                            <option value="">Sold by the store</option>
                            @foreach($vendors as $seller)
                                <option value="{{ $seller->id }}" @selected((int) old('vendor_id', $product->vendor_id) === $seller->id)>{{ $seller->name }}</option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-[11px] leading-5 text-[#8a9892]">Assigning a seller makes this product part of their catalog. The seller earns the item subtotal after discount, minus commission.</p>
                        @if($vendors->isEmpty())
                            <a href="{{ route('admin.vendors.create') }}" class="mt-2 inline-block text-[11px] font-bold text-[#0f766e]">Add your first seller →</a>
                        @endif
                    @else
                        <input type="hidden" name="vendor_id" value="{{ $product->vendor_id }}">
                        <p class="rounded-lg border border-[#e3e9e5] bg-[#fafcfb] p-3 text-[11px] leading-5 text-[#687771]">Seller assignment is only editable in Multi-Vendor mode. Existing seller ownership is preserved.</p>
                    @endif
                </div>
            </div>

            <div class="card card-pad">
                <h2 class="font-display text-xl">Inventory</h2>
                <label class="mt-4 flex items-center gap-2.5 text-sm"><input type="checkbox" name="manage_stock" value="1" class="accent-[#0f766e]" @checked(old('manage_stock', $product->manage_stock ?? true))> Track stock for this product</label>
                <div class="mt-4 grid grid-cols-2 gap-3">
                    <div><label class="label" for="stock_quantity">In stock</label><input id="stock_quantity" type="number" min="0" name="stock_quantity" value="{{ old('stock_quantity', $product->stock_quantity) }}" class="input"></div>
                    <div><label class="label" for="low_stock_threshold">Low stock at</label><input id="low_stock_threshold" type="number" min="0" name="low_stock_threshold" value="{{ old('low_stock_threshold', $product->low_stock_threshold ?: 5) }}" class="input"></div>
                </div>
            </div>

            <div class="card card-pad">
                <h2 class="font-display text-xl">Images</h2>
                <input type="file" name="images[]" accept="image/*" multiple class="mt-3 block w-full text-xs file:mr-3 file:rounded-full file:border-0 file:bg-[#0f766e] file:px-4 file:py-2 file:text-xs file:font-bold file:text-white">
                @if($product->images->isNotEmpty())
                    <div class="mt-4 grid grid-cols-3 gap-2">
                        @foreach($product->images as $image)
                            <label class="group relative block overflow-hidden rounded-lg border border-[#e3e9e5]">
                                <img src="{{ $image->url() }}" alt="" class="aspect-square w-full object-cover">
                                <span class="absolute inset-x-0 bottom-0 flex items-center gap-1 bg-black/60 px-2 py-1 text-[9px] font-semibold text-white">
                                    <input type="checkbox" name="remove_images[]" value="{{ $image->id }}" class="accent-rose-500"> Remove
                                </span>
                            </label>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="card card-pad">
                <h2 class="font-display text-xl">SEO</h2>
                <div class="mt-4 space-y-3">
                    <div><label class="label" for="meta_title">Meta title</label><input id="meta_title" name="meta_title" value="{{ old('meta_title', $product->meta_title) }}" class="input"></div>
                    <div><label class="label" for="meta_description">Meta description</label><textarea id="meta_description" name="meta_description" rows="3" class="textarea">{{ old('meta_description', $product->meta_description) }}</textarea></div>
                </div>
            </div>
        </div>
    </div>
</form>

@if($isEdit)
    <form id="delete-product" method="post" action="{{ route('admin.products.destroy', $product) }}" class="hidden">
        @csrf
        @method('DELETE')
    </form>
@endif
@endsection

@push('scripts')
<script>
    let rowCounter = 0;
    function insertRow(templateId, containerId) {
        const template = document.getElementById(templateId);
        const container = document.getElementById(containerId);
        if (!template || !container) return;
        rowCounter += 1;
        container.insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__INDEX__', 'new' + rowCounter));
    }
    function addVariantRow() { insertRow('variant-template', 'variant-rows'); }
    function addPlanRow() { insertRow('plan-template', 'plan-rows'); }
    function addTierRow() { insertRow('tier-template', 'tier-rows'); }
</script>
@endpush
