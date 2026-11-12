<?php

namespace App\Http\Controllers\Admin;

use App\Enums\DiscountType;
use App\Http\Controllers\Controller;
use App\Models\Coupon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CouponController extends Controller
{
    public function index(): View
    {
        $coupons = Coupon::query()->latest()->paginate(20);

        return view('admin.coupons.index', compact('coupons'));
    }

    public function create(): View
    {
        return view('admin.coupons.form', ['coupon' => new Coupon(['type' => 'percentage', 'is_active' => true])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['code'] = strtoupper($data['code']);
        Coupon::query()->create($data);

        return redirect()->route('admin.coupons.index')->with('status', 'Coupon created.');
    }

    public function edit(Coupon $coupon): View
    {
        return view('admin.coupons.form', compact('coupon'));
    }

    public function update(Request $request, Coupon $coupon): RedirectResponse
    {
        $data = $this->validated($request, $coupon);
        $data['code'] = strtoupper($data['code']);
        $coupon->update($data);

        return redirect()->route('admin.coupons.index')->with('status', 'Coupon updated.');
    }

    public function destroy(Coupon $coupon): RedirectResponse
    {
        $coupon->delete();

        return redirect()->route('admin.coupons.index')->with('status', 'Coupon deleted.');
    }

    private function validated(Request $request, ?Coupon $coupon = null): array
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:60', Rule::unique('coupons', 'code')->ignore($coupon?->id)],
            'type' => ['required', Rule::enum(DiscountType::class)],
            'value' => ['required', 'numeric', 'min:0', 'max:999999'],
            'minimum_subtotal' => ['nullable', 'numeric', 'min:0'],
            'maximum_discount' => ['nullable', 'numeric', 'min:0'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'usage_limit_per_customer' => ['nullable', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ]);

        $data['code'] = strtoupper(trim($data['code']));
        if (Coupon::query()->where('code', $data['code'])->when($coupon, fn ($q) => $q->where('id', '!=', $coupon->id))->exists()) {
            throw \Illuminate\Validation\ValidationException::withMessages(['code' => 'That coupon code is already in use.']);
        }

        $data['is_active'] = $request->boolean('is_active');
        $data['value'] = (float) $data['value'];
        $data['used_count'] = $coupon?->used_count ?? 0;

        return $data;
    }
}
