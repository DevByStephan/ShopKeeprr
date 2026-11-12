<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TaxRate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TaxController extends Controller
{
    public function index(): View
    {
        $rates = TaxRate::query()->orderByDesc('priority')->paginate(20);

        return view('admin.taxes.index', compact('rates'));
    }

    public function store(Request $request): RedirectResponse
    {
        TaxRate::query()->create($this->validated($request));

        return back()->with('status', 'Tax rate created.');
    }

    public function update(Request $request, TaxRate $rate): RedirectResponse
    {
        $rate->update($this->validated($request));

        return back()->with('status', 'Tax rate updated.');
    }

    public function destroy(TaxRate $rate): RedirectResponse
    {
        $rate->delete();

        return back()->with('status', 'Tax rate deleted.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'country' => ['required', 'string', 'size:2'],
            'state' => ['nullable', 'string', 'max:80'],
            'rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'priority' => ['nullable', 'integer', 'min:0'],
        ]);

        $data['country'] = strtoupper($data['country']);
        $data['state'] = $data['state'] ? strtoupper($data['state']) : null;
        $data['is_active'] = $request->boolean('is_active');
        $data['priority'] = (int) ($data['priority'] ?? 0);

        return $data;
    }
}
