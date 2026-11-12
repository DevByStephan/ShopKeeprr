<?php

namespace App\Http\Controllers\Admin;

use App\Enums\StoreMode;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\CustomerTier;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\SubscriptionPlan;
use App\Models\TierPrice;
use App\Models\Vendor;
use App\Services\StoreModeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        $query = Product::query()->with(['category', 'images', 'variants'])->latest();

        if ($request->filled('q')) {
            $query->search($request->string('q')->toString());
        }

        if ($request->filled('category')) {
            $query->where('category_id', $request->integer('category'));
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->string('status')->toString() === 'active');
        }

        $mode = nexus_mode();

        if ($mode) {
            $query->with(['modeStates' => fn ($states) => $states->where('mode', $mode->value)]);
        }

        $products = $query->paginate(15)->withQueryString();
        $categories = Category::query()->orderBy('name')->get();

        return view('admin.products.index', compact('products', 'categories', 'mode'));
    }

    public function create(): View
    {
        $mode = nexus_mode();

        return view('admin.products.form', [
            'product' => new Product(['type' => 'simple', 'manage_stock' => true, 'is_active' => true, 'min_order_quantity' => 1]),
            'categories' => Category::query()->orderBy('name')->get(),
            'tiers' => CustomerTier::query()->orderBy('priority')->get(),
            'vendors' => Vendor::query()->approved()->orderBy('name')->get(),
            'mode' => $mode,
            'modeState' => null,
            'modeCompatibility' => null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->validateProduct($request);

        $product = DB::transaction(function () use ($request) {
            $data = $this->productData($request);
            $data['type'] = ! empty($request->input('variants', [])) ? 'variable' : 'simple';

            if (nexus_mode() !== StoreMode::MultiVendor) {
                $data['vendor_id'] = null;
            }

            $product = Product::query()->create($data);

            $this->syncImages($product, $request);
            $this->syncVariants($product, $request);
            $this->syncPlans($product, $request);
            $this->syncTierPrices($product, $request);

            return $product;
        });

        $this->syncModeState($product, $request);

        return redirect()->route('admin.products.edit', $product)->with('status', 'Product created.');
    }

    public function edit(Product $product): View
    {
        $product->load(['images', 'variants', 'subscriptionPlans', 'tierPrices']);
        $mode = nexus_mode();
        $modeState = $mode
            ? $product->modeStates()->where('mode', $mode->value)->first()
            : null;

        return view('admin.products.form', [
            'product' => $product,
            'categories' => Category::query()->orderBy('name')->get(),
            'tiers' => CustomerTier::query()->orderBy('priority')->get(),
            'vendors' => Vendor::query()->approved()->orderBy('name')->get(),
            'mode' => $mode,
            'modeState' => $modeState,
            'modeCompatibility' => $mode ? $product->modeUnavailableReason($mode) : null,
        ]);
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $this->validateProduct($request, $product);

        DB::transaction(function () use ($request, $product) {
            $data = $this->productData($request);
            $data['type'] = ! empty($request->input('variants', [])) ? 'variable' : 'simple';

            if (nexus_mode() !== StoreMode::MultiVendor) {
                $data['vendor_id'] = $product->vendor_id;
            }

            $product->update($data);

            $this->syncImages($product, $request);
            $this->syncVariants($product, $request);
            $this->syncPlans($product, $request);
            $this->syncTierPrices($product, $request);
        });

        $this->syncModeState($product->refresh(), $request);

        return redirect()->route('admin.products.edit', $product)->with('status', 'Product updated.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        foreach ($product->images as $image) {
            $this->deleteImageFile($image->path);
        }

        $product->delete();

        return redirect()->route('admin.products.index')->with('status', 'Product deleted.');
    }

    private function validateProduct(Request $request, ?Product $product = null): void
    {
        $rules = [
            'name' => ['required', 'string', 'max:160'],
            'slug' => ['nullable', 'string', 'max:180', Rule::unique('products', 'slug')->ignore($product?->id)],
            'sku' => ['nullable', 'string', 'max:80', Rule::unique('products', 'sku')->ignore($product?->id)],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'vendor_id' => ['nullable', 'integer', 'exists:vendors,id'],
            'short_description' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0', 'max:9999999'],
            'compare_at_price' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'cost_price' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'stock_quantity' => ['nullable', 'integer', 'min:0'],
            'low_stock_threshold' => ['nullable', 'integer', 'min:0'],
            'min_order_quantity' => ['nullable', 'integer', 'min:1'],
            'weight' => ['nullable', 'numeric', 'min:0'],
            'images.*' => ['nullable', 'image', 'max:4096'],
            'variants' => ['nullable', 'array'],
            'variants.*.name' => ['nullable', 'string', 'max:120'],
            'variants.*.sku' => ['nullable', 'string', 'max:80'],
            'variants.*.price' => ['nullable', 'numeric', 'min:0'],
            'variants.*.stock_quantity' => ['nullable', 'integer', 'min:0'],
            'plans' => ['nullable', 'array'],
            'plans.*.name' => ['nullable', 'string', 'max:120'],
            'plans.*.interval' => ['nullable', Rule::in(['day', 'week', 'month', 'year'])],
            'plans.*.interval_count' => ['nullable', 'integer', 'min:1', 'max:60'],
            'plans.*.price' => ['nullable', 'numeric', 'min:0'],
            'plans.*.trial_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'tier_prices' => ['nullable', 'array'],
            'tier_prices.*.tier_id' => ['nullable', 'integer', 'exists:customer_tiers,id'],
            'tier_prices.*.min_quantity' => ['nullable', 'integer', 'min:1'],
            'tier_prices.*.price' => ['nullable', 'numeric', 'min:0'],
        ];

        foreach ($request->input('variants', []) as $index => $variant) {
            if (! empty($variant['sku'])) {
                $rules["variants.{$index}.sku"] = [
                    Rule::unique('product_variants', 'sku')->ignore($variant['id'] ?? null),
                ];
            }
        }

        $request->validate($rules);

        $hasActivePlan = collect($request->input('plans', []))->contains(
            fn ($plan) => ! empty($plan['name']) && ! empty($plan['is_active'])
        );
        $hasVendor = filled($request->input('vendor_id'));
        $wholesaleOnly = $request->boolean('is_wholesale_only');

        if ($hasVendor && $hasActivePlan) {
            throw ValidationException::withMessages([
                'vendor_id' => 'A seller product cannot use subscription plans. Move it to Subscription mode first.',
            ]);
        }

        if ($hasVendor && $wholesaleOnly) {
            throw ValidationException::withMessages([
                'vendor_id' => 'A seller product cannot be wholesale-only. Multi-Vendor and B2B are separate models.',
            ]);
        }

        if ($wholesaleOnly && $hasActivePlan) {
            throw ValidationException::withMessages([
                'is_wholesale_only' => 'A wholesale-only product cannot use subscription plans.',
            ]);
        }
    }

    private function syncModeState(Product $product, Request $request): void
    {
        $mode = nexus_mode();

        if (! $mode) {
            return;
        }

        $service = app(StoreModeService::class);
        $service->syncProductState($product, $mode);

        if ($request->boolean('manual_visibility_present')) {
            $service->setManualVisibility($product, $mode, $request->boolean('hide_in_current_mode'));
        }
    }

    private function productData(Request $request): array
    {
        $data = $request->only([
            'name', 'category_id', 'vendor_id', 'short_description', 'description', 'price', 'compare_at_price',
            'cost_price', 'stock_quantity', 'low_stock_threshold', 'min_order_quantity', 'weight',
            'tax_class', 'meta_title', 'meta_description',
        ]);

        $data['slug'] = $this->uniqueSlug($request->input('slug') ?: $request->input('name'), $request->route('product')?->id);
        $data['sku'] = $request->input('sku') ?: null;
        $data['manage_stock'] = $request->boolean('manage_stock');
        $data['is_active'] = $request->boolean('is_active');
        $data['is_featured'] = $request->boolean('is_featured');
        $data['is_wholesale_only'] = $request->boolean('is_wholesale_only');
        $data['requires_shipping'] = $request->boolean('requires_shipping');
        $data['stock_quantity'] = (int) ($data['stock_quantity'] ?? 0);
        $data['low_stock_threshold'] = (int) ($data['low_stock_threshold'] ?? 5);
        $data['min_order_quantity'] = max(1, (int) ($data['min_order_quantity'] ?? 1));
        $data['published_at'] = $request->boolean('is_active') ? (Product::query()->whereKey($request->route('product')?->id)->value('published_at') ?? now()) : null;

        return $data;
    }

    private function uniqueSlug(string $value, ?int $ignoreId = null): string
    {
        $base = Str::slug($value) ?: 'product';
        $slug = $base;
        $i = 2;

        while (Product::query()->where('slug', $slug)->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    private function syncImages(Product $product, Request $request): void
    {
        $remove = $request->input('remove_images', []);
        if ($remove) {
            foreach (ProductImage::query()->where('product_id', $product->id)->whereIn('id', $remove)->get() as $image) {
                $this->deleteImageFile($image->path);
                $image->delete();
            }
        }

        $sort = (int) ProductImage::query()->where('product_id', $product->id)->max('sort_order');
        $files = $request->file('images', []);
        $directory = public_path('uploads/products');

        if (! is_dir($directory)) {
            @mkdir($directory, 0775, true);
        }

        foreach ($files as $file) {
            if (! $file || ! $file->isValid()) {
                continue;
            }

            $name = Str::slug($product->name).'-'.Str::lower(Str::random(8)).'.'.$file->extension();
            $file->move($directory, $name);

            ProductImage::query()->create([
                'product_id' => $product->id,
                'path' => 'uploads/products/'.$name,
                'alt' => $product->name,
                'sort_order' => ++$sort,
            ]);
        }
    }

    private function syncVariants(Product $product, Request $request): void
    {
        $keep = [];

        foreach ($request->input('variants', []) as $index => $row) {
            if (empty($row['name'])) {
                continue;
            }

            $variant = ! empty($row['id'])
                ? $product->variants()->whereKey($row['id'])->first()
                : new ProductVariant(['product_id' => $product->id]);

            if (! $variant) {
                continue;
            }

            $variant->fill([
                'name' => $row['name'],
                'sku' => $row['sku'] ?: null,
                'price' => $row['price'] !== '' ? (float) $row['price'] : null,
                'compare_at_price' => ! empty($row['compare_at_price']) ? (float) $row['compare_at_price'] : null,
                'stock_quantity' => (int) ($row['stock_quantity'] ?? 0),
                'options' => $this->parseOptions($row['options_text'] ?? '', $row['name']),
                'is_active' => ! empty($row['is_active']),
                'sort_order' => $index,
            ]);
            $variant->product_id = $product->id;
            $variant->save();
            $keep[] = $variant->id;
        }

        $product->variants()->whereNotIn('id', $keep)->delete();
    }

    private function syncPlans(Product $product, Request $request): void
    {
        $keep = [];

        foreach ($request->input('plans', []) as $index => $row) {
            if (empty($row['name'])) {
                continue;
            }

            $plan = ! empty($row['id'])
                ? $product->subscriptionPlans()->whereKey($row['id'])->first()
                : new SubscriptionPlan(['product_id' => $product->id]);

            if (! $plan) {
                continue;
            }

            $plan->fill([
                'product_id' => $product->id,
                'name' => $row['name'],
                'interval' => $row['interval'] ?? 'month',
                'interval_count' => max(1, (int) ($row['interval_count'] ?? 1)),
                'price' => (float) ($row['price'] ?? 0),
                'trial_days' => (int) ($row['trial_days'] ?? 0),
                'is_active' => ! empty($row['is_active']),
                'sort_order' => $index,
            ])->save();
            $keep[] = $plan->id;
        }

        $product->subscriptionPlans()->whereNotIn('id', $keep)->delete();
    }

    private function syncTierPrices(Product $product, Request $request): void
    {
        $product->tierPrices()->delete();

        foreach ($request->input('tier_prices', []) as $row) {
            if (empty($row['tier_id']) || ! isset($row['price']) || $row['price'] === '') {
                continue;
            }

            try {
                TierPrice::query()->create([
                    'product_id' => $product->id,
                    'variant_id' => ! empty($row['variant_id']) ? (int) $row['variant_id'] : null,
                    'tier_id' => (int) $row['tier_id'],
                    'min_quantity' => max(1, (int) ($row['min_quantity'] ?? 1)),
                    'price' => (float) $row['price'],
                ]);
            } catch (Throwable) {
                // Ignore duplicate or malformed price rows rather than failing the whole product save.
            }
        }
    }

    private function parseOptions(string $text, string $fallbackName): array
    {
        $options = [];
        foreach (preg_split('/\r\n|\r|\n/', $text) as $line) {
            if (str_contains($line, ':')) {
                [$key, $value] = array_map('trim', explode(':', $line, 2));
                if ($key !== '' && $value !== '') {
                    $options[$key] = $value;
                }
            }
        }

        return $options ?: ['Option' => $fallbackName];
    }

    private function deleteImageFile(string $path): void
    {
        if (str_starts_with($path, 'uploads/')) {
            $full = public_path($path);
            if (is_file($full)) {
                @unlink($full);
            }
        }
    }
}
