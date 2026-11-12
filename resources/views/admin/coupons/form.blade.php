@extends('admin.layouts.app')

@section('title', $coupon->exists ? 'Edit '.$coupon->code : 'New coupon')
@section('eyebrow', 'Coupons')
@section('heading', $coupon->exists ? 'Edit coupon' : 'New coupon')

@section('content')
@php($isEdit = $coupon->exists)
<form method="post" action="{{ $isEdit ? route('admin.coupons.update', $coupon) : route('admin.coupons.store') }}" class="grid gap-5 xl:grid-cols-[1.3fr_1fr]">
    @csrf
    @if($isEdit) @method('PUT') @endif

    <div class="card card-pad">
        <h2 class="font-display text-xl">Discount</h2>
        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <label class="label" for="code">Coupon code</label>
                <input id="code" name="code" value="{{ old('code', $coupon->code) }}" class="input font-mono uppercase" required placeholder="WELCOME10">
            </div>
            <div>
                <label class="label" for="type">Type</label>
                <select id="type" name="type" class="select">
                    @foreach(['percentage' => 'Percentage off', 'fixed' => 'Fixed amount off', 'free_shipping' => 'Free shipping'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('type', $coupon->type?->value ?? 'percentage') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="label" for="value">Value</label>
                <input id="value" type="number" step="0.01" min="0" name="value" value="{{ old('value', $coupon->value) }}" class="input">
                <p class="mt-1 text-[11px] text-[#8a9892]">Percentage (10 = 10%) or amount in store currency.</p>
            </div>
            <div>
                <label class="label" for="minimum_subtotal">Minimum subtotal</label>
                <input id="minimum_subtotal" type="number" step="0.01" min="0" name="minimum_subtotal" value="{{ old('minimum_subtotal', $coupon->minimum_subtotal) }}" class="input">
            </div>
            <div>
                <label class="label" for="maximum_discount">Maximum discount</label>
                <input id="maximum_discount" type="number" step="0.01" min="0" name="maximum_discount" value="{{ old('maximum_discount', $coupon->maximum_discount) }}" class="input">
            </div>
            <div>
                <label class="label" for="usage_limit">Total usage limit</label>
                <input id="usage_limit" type="number" min="1" name="usage_limit" value="{{ old('usage_limit', $coupon->usage_limit) }}" class="input">
            </div>
            <div>
                <label class="label" for="usage_limit_per_customer">Limit per customer</label>
                <input id="usage_limit_per_customer" type="number" min="1" name="usage_limit_per_customer" value="{{ old('usage_limit_per_customer', $coupon->usage_limit_per_customer) }}" class="input">
            </div>
        </div>
        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <div>
                <label class="label" for="starts_at">Starts</label>
                <input id="starts_at" type="date" name="starts_at" value="{{ old('starts_at', $coupon->starts_at?->format('Y-m-d')) }}" class="input">
            </div>
            <div>
                <label class="label" for="expires_at">Expires</label>
                <input id="expires_at" type="date" name="expires_at" value="{{ old('expires_at', $coupon->expires_at?->format('Y-m-d')) }}" class="input">
            </div>
        </div>
        <div class="mt-5 flex gap-2">
            <button class="btn-primary">{{ $isEdit ? 'Save coupon' : 'Create coupon' }}</button>
            <a href="{{ route('admin.coupons.index') }}" class="btn-secondary">Cancel</a>
        </div>
    </div>

    <div class="space-y-5">
        <div class="card card-pad">
            <h2 class="font-display text-xl">Status</h2>
            <label class="mt-4 flex items-center gap-2.5 text-sm"><input type="checkbox" name="is_active" value="1" class="accent-[#0f766e]" @checked(old('is_active', $coupon->is_active ?? true))> Coupon is active</label>
            @if($isEdit)
                <p class="mt-3 text-xs text-[#687771]">Redeemed {{ $coupon->used_count }} {{ Str::plural('time', $coupon->used_count) }}.</p>
            @endif
        </div>
        @if($isEdit)
            <div class="card card-pad">
                <button type="submit" form="delete-coupon" class="text-xs font-bold text-rose-600" onclick="return confirm('Delete this coupon?')">Delete coupon</button>
            </div>
        @endif
    </div>
</form>
@if($isEdit)
    <form id="delete-coupon" method="post" action="{{ route('admin.coupons.destroy', $coupon) }}" class="hidden">
        @csrf
        @method('DELETE')
    </form>
@endif
@endsection
