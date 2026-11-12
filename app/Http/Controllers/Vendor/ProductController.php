<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        $vendor = $request->attributes->get('vendor');

        $products = $vendor->products()
            ->with(['images', 'category'])
            ->when($request->filled('q'), fn ($query) => $query->search($request->string('q')->toString()))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('vendor.products.index', compact('vendor', 'products'));
    }

    public function create(Request $request): View
    {
        return view('vendor.products.form', [
            'vendor' => $request->attributes->get('vendor'),
            'product' => new Product(['type' => 'simple', 'manage_stock' => true, 'is_active' => true, 'min_order_quantity' => 1]),
            'categories' => Category::query()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $vendor = $request->attributes->get('vendor');
        $data = $this->validated($request);

        $product = DB::transaction(function () use ($data, $request, $vendor): Product {
            $data['vendor_id'] = $vendor->id;
            $data['store_id'] = $vendor->store_id;
            $data['type'] = 'simple';
            $data['is_wholesale_only'] = false;
            $data['published_at'] = $data['is_active'] ? now() : null;

            $product = Product::query()->create($data);

            $this->saveImage($product, $request);

            return $product;
        });

        return redirect()->route('vendor.products.edit', $product)->with('status', 'Product created.');
    }

    public function edit(Request $request, Product $product): View
    {
        $vendor = $request->attributes->get('vendor');
        $this->authorizeProduct($vendor, $product);

        $product->load('images');

        return view('vendor.products.form', [
            'vendor' => $vendor,
            'product' => $product,
            'categories' => Category::query()->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $vendor = $request->attributes->get('vendor');
        $this->authorizeProduct($vendor, $product);

        $data = $this->validated($request, $product);
        $data['published_at'] = $data['is_active'] ? ($product->published_at ?? now()) : null;

        DB::transaction(function () use ($product, $data, $request): void {
            $product->update($data);
            $this->saveImage($product, $request);
        });

        return redirect()->route('vendor.products.edit', $product)->with('status', 'Product updated.');
    }

    public function destroy(Request $request, Product $product): RedirectResponse
    {
        $vendor = $request->attributes->get('vendor');
        $this->authorizeProduct($vendor, $product);

        foreach ($product->images as $image) {
            $this->deleteImage($image);
        }

        $product->delete();

        return redirect()->route('vendor.products.index')->with('status', 'Product deleted.');
    }

    private function authorizeProduct($vendor, Product $product): void
    {
        abort_unless((int) $product->vendor_id === (int) $vendor->id, 404);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Product $product = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'slug' => ['nullable', 'string', 'max:180', Rule::unique('products', 'slug')->ignore($product?->id)],
            'sku' => ['nullable', 'string', 'max:80', Rule::unique('products', 'sku')->ignore($product?->id)],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'short_description' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0', 'max:9999999'],
            'compare_at_price' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'stock_quantity' => ['nullable', 'integer', 'min:0'],
            'low_stock_threshold' => ['nullable', 'integer', 'min:0'],
            'weight' => ['nullable', 'numeric', 'min:0'],
            'image' => ['nullable', 'image', 'max:4096'],
        ]);

        $data['slug'] = $this->uniqueSlug($data['slug'] ?? $data['name'], $product?->id);
        $data['sku'] = $data['sku'] ?? null;
        $data['manage_stock'] = $request->boolean('manage_stock');
        $data['is_active'] = $request->boolean('is_active');
        $data['requires_shipping'] = $request->boolean('requires_shipping');
        $data['is_featured'] = false;
        $data['stock_quantity'] = (int) ($data['stock_quantity'] ?? 0);
        $data['low_stock_threshold'] = (int) ($data['low_stock_threshold'] ?? 5);
        $data['min_order_quantity'] = 1;
        $data['tax_class'] = 'standard';

        unset($data['image']);

        return $data;
    }

    private function uniqueSlug(string $value, ?int $ignoreId = null): string
    {
        $base = Str::slug($value) ?: 'product';
        $slug = $base;
        $i = 2;

        while (Product::query()->where('slug', $slug)->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    private function saveImage(Product $product, Request $request): void
    {
        $file = $request->file('image');

        if (! $file || ! $file->isValid()) {
            return;
        }

        $directory = public_path('uploads/products');

        if (! is_dir($directory)) {
            @mkdir($directory, 0775, true);
        }

        $filename = 'product-'.Str::lower(Str::random(12)).'.'.$file->extension();
        $file->move($directory, $filename);

        foreach ($product->images as $image) {
            $this->deleteImage($image);
        }

        ProductImage::query()->create([
            'product_id' => $product->id,
            'path' => 'uploads/products/'.$filename,
            'alt' => $product->name,
            'sort_order' => 0,
        ]);
    }

    private function deleteImage(ProductImage $image): void
    {
        $path = public_path($image->path);

        if (str_starts_with($image->path, 'uploads/') && is_file($path)) {
            @unlink($path);
        }

        $image->delete();
    }
}
