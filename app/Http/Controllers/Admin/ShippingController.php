<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ShippingMethod;
use App\Models\ShippingZone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ShippingController extends Controller
{
    public function index(): View
    {
        $zones = ShippingZone::query()->with(['methods'])->orderBy('sort_order')->get();

        return view('admin.shipping.index', compact('zones'));
    }

    public function storeZone(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'countries' => ['required', 'string', 'max:500'],
            'states' => ['nullable', 'string', 'max:500'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        ShippingZone::query()->create([
            'name' => $data['name'],
            'countries' => $this->parseList($data['countries']),
            'states' => $data['states'] ? $this->parseList($data['states']) : null,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'is_active' => $request->boolean('is_active'),
        ]);

        return back()->with('status', 'Shipping zone created.');
    }

    public function updateZone(Request $request, ShippingZone $zone): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'countries' => ['required', 'string', 'max:500'],
            'states' => ['nullable', 'string', 'max:500'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $zone->update([
            'name' => $data['name'],
            'countries' => $this->parseList($data['countries']),
            'states' => $data['states'] ? $this->parseList($data['states']) : null,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'is_active' => $request->boolean('is_active'),
        ]);

        return back()->with('status', 'Shipping zone updated.');
    }

    public function destroyZone(ShippingZone $zone): RedirectResponse
    {
        $zone->delete();

        return back()->with('status', 'Shipping zone deleted.');
    }

    public function storeMethod(Request $request): RedirectResponse
    {
        $data = $this->methodData($request);
        ShippingMethod::query()->create($data);

        return back()->with('status', 'Shipping method added.');
    }

    public function updateMethod(Request $request, ShippingMethod $method): RedirectResponse
    {
        $method->update($this->methodData($request));

        return back()->with('status', 'Shipping method updated.');
    }

    public function destroyMethod(ShippingMethod $method): RedirectResponse
    {
        $method->delete();

        return back()->with('status', 'Shipping method deleted.');
    }

    private function methodData(Request $request): array
    {
        $data = $request->validate([
            'zone_id' => ['required', 'integer', 'exists:shipping_zones,id'],
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', 'in:flat_rate,free,weight_based'],
            'cost' => ['nullable', 'numeric', 'min:0'],
            'per_kg' => ['nullable', 'numeric', 'min:0'],
            'free_threshold' => ['nullable', 'numeric', 'min:0'],
            'min_weight' => ['nullable', 'numeric', 'min:0'],
            'max_weight' => ['nullable', 'numeric', 'min:0'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $data['cost'] = (float) ($data['cost'] ?? 0);
        $data['is_active'] = $request->boolean('is_active');
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);

        return $data;
    }

    private function parseList(string $value): array
    {
        return collect(explode(',', $value))
            ->map(fn ($item) => strtoupper(trim($item)))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
