<?php

namespace Modules\Bundles\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Bundles\Models\Bundle;

class BundleController extends Controller
{
    public function index(): View
    {
        return view('bundles::admin.index', [
            'bundles' => Bundle::query()->withCount('products')->latest()->paginate(20),
        ]);
    }

    public function create(): View
    {
        return view('bundles::admin.form', [
            'bundle' => new Bundle(['discount_type' => 'percentage', 'discount_value' => 10, 'is_active' => true]),
            'products' => Product::query()->orderBy('name')->get(),
            'selected' => [],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $bundle = DB::transaction(function () use ($request, $data) {
            $bundle = Bundle::query()->create($this->payload($request, $data));
            $this->syncProducts($bundle, $request);

            return $bundle;
        });

        return redirect()->route('admin.bundles.edit', $bundle)->with('status', 'Bundle created.');
    }

    public function edit(Bundle $bundle): View
    {
        $bundle->load('products');

        return view('bundles::admin.form', [
            'bundle' => $bundle,
            'products' => Product::query()->orderBy('name')->get(),
            'selected' => $bundle->products->mapWithKeys(fn (Product $product) => [$product->id => (int) $product->pivot->quantity])->all(),
        ]);
    }

    public function update(Request $request, Bundle $bundle): RedirectResponse
    {
        $data = $this->validated($request, $bundle);

        DB::transaction(function () use ($request, $bundle, $data) {
            $bundle->update($this->payload($request, $data));
            $this->syncProducts($bundle, $request);
        });

        return redirect()->route('admin.bundles.edit', $bundle)->with('status', 'Bundle updated.');
    }

    public function destroy(Bundle $bundle): RedirectResponse
    {
        $bundle->delete();

        return redirect()->route('admin.bundles.index')->with('status', 'Bundle deleted.');
    }

    private function validated(Request $request, ?Bundle $bundle = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:140'],
            'slug' => ['nullable', 'string', 'max:160', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('product_bundles', 'slug')->ignore($bundle?->id)],
            'description' => ['nullable', 'string', 'max:2000'],
            'discount_type' => ['required', Rule::in(['percentage', 'fixed'])],
            'discount_value' => ['required', 'numeric', 'min:0', 'max:100000'],
            'is_active' => ['nullable', 'boolean'],
            'items' => ['required', 'array', 'min:2'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:50'],
        ]);
    }

    private function payload(Request $request, array $data): array
    {
        $slug = $data['slug'] ?? Str::slug($data['name']);

        return [
            'name' => $data['name'],
            'slug' => $slug,
            'description' => $data['description'] ?? null,
            'discount_type' => $data['discount_type'],
            'discount_value' => $data['discount_value'],
            'is_active' => $request->boolean('is_active'),
        ];
    }

    private function syncProducts(Bundle $bundle, Request $request): void
    {
        $sync = [];

        foreach ($request->input('items', []) as $item) {
            if (empty($item['product_id'])) {
                continue;
            }

            $sync[(int) $item['product_id']] = ['quantity' => max(1, (int) ($item['quantity'] ?? 1))];
        }

        $bundle->products()->sync($sync);
    }
}
