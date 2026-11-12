@extends('admin.layouts.app')

@section('title', $vendor->exists ? 'Edit seller' : 'New seller')
@section('eyebrow', 'Marketplace')
@section('heading', $vendor->exists ? $vendor->name : 'New seller')

@section('content')
<form method="post" action="{{ $vendor->exists ? route('admin.vendors.update', $vendor) : route('admin.vendors.store') }}" class="grid gap-5 xl:grid-cols-[1.4fr_.8fr]">
    @csrf
    @if($vendor->exists)
        @method('PUT')
    @endif

    <div class="space-y-5">
        <div class="card card-pad">
            <h2 class="font-display text-xl">Seller details</h2>
            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="label" for="name">Store name</label>
                    <input id="name" name="name" class="input" value="{{ old('name', $vendor->name) }}" required>
                </div>
                <div>
                    <label class="label" for="email">Contact email</label>
                    <input id="email" type="email" name="email" class="input" value="{{ old('email', $vendor->email) }}" required>
                </div>
                <div>
                    <label class="label" for="phone">Phone</label>
                    <input id="phone" name="phone" class="input" value="{{ old('phone', $vendor->phone) }}">
                </div>
                <div>
                    <label class="label" for="country">Seller country</label>
                    <select id="country" name="country" class="select">
                        @foreach(['US' => 'United States', 'CA' => 'Canada', 'GB' => 'United Kingdom', 'AU' => 'Australia', 'NZ' => 'New Zealand', 'IE' => 'Ireland', 'DE' => 'Germany', 'FR' => 'France', 'ES' => 'Spain', 'IT' => 'Italy', 'NL' => 'Netherlands', 'BE' => 'Belgium', 'AT' => 'Austria', 'CH' => 'Switzerland', 'SE' => 'Sweden', 'NO' => 'Norway', 'DK' => 'Denmark', 'FI' => 'Finland', 'PT' => 'Portugal', 'PL' => 'Poland', 'CZ' => 'Czechia', 'JP' => 'Japan', 'SG' => 'Singapore', 'HK' => 'Hong Kong', 'AE' => 'United Arab Emirates'] as $code => $label)
                            <option value="{{ $code }}" @selected(old('country', $vendor->country) === $code)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-[11px] leading-5 text-[#8a9892]">Stripe uses this for the seller's connected account. It must match their legal country.</p>
                </div>
                <div>
                    <label class="label" for="user_id">Portal account</label>
                    <select id="user_id" name="user_id" class="select">
                        <option value="">No login yet</option>
                        @foreach($users as $user)
                            <option value="{{ $user->id }}" @selected((string) old('user_id', $vendor->user_id) === (string) $user->id)>{{ $user->name }} — {{ $user->email }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-[11px] leading-5 text-[#8a9892]">The seller signs in with this customer account to reach their vendor portal.</p>
                </div>
                <div class="sm:col-span-2">
                    <label class="label" for="description">Public description</label>
                    <textarea id="description" name="description" rows="4" class="input">{{ old('description', $vendor->description) }}</textarea>
                </div>
            </div>
        </div>

        <div class="card card-pad">
            <h2 class="font-display text-xl">Commission</h2>
            <p class="mt-1 text-xs leading-5 text-[#687771]">Leave a field empty to inherit the platform rule from Settings → Marketplace.</p>
            <div class="mt-4 grid gap-4 sm:grid-cols-3">
                <div>
                    <label class="label" for="commission_type">Rule</label>
                    <select id="commission_type" name="commission_type" class="select">
                        <option value="">Inherit platform rule</option>
                        <option value="percentage" @selected(old('commission_type', $vendor->commission_type) === 'percentage')>Percentage</option>
                        <option value="fixed" @selected(old('commission_type', $vendor->commission_type) === 'fixed')>Fixed per order</option>
                        <option value="combined" @selected(old('commission_type', $vendor->commission_type) === 'combined')>Percentage + fixed</option>
                    </select>
                </div>
                <div>
                    <label class="label" for="commission_percent">Percent (%)</label>
                    <input id="commission_percent" name="commission_percent" type="number" step="0.01" min="0" max="100" class="input" value="{{ old('commission_percent', $vendor->commission_percent) }}" placeholder="Inherit">
                </div>
                <div>
                    <label class="label" for="commission_fixed">Fixed amount</label>
                    <input id="commission_fixed" name="commission_fixed" type="number" step="0.01" min="0" class="input" value="{{ old('commission_fixed', $vendor->commission_fixed) }}" placeholder="Inherit">
                </div>
            </div>
        </div>

        <div class="card card-pad">
            <h2 class="font-display text-xl">Payouts</h2>
            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="label" for="payout_method">Method</label>
                    <select id="payout_method" name="payout_method" class="select">
                        <option value="manual" @selected(old('payout_method', $vendor->payout_method) === 'manual')>Manual bank transfer</option>
                        <option value="stripe" @selected(old('payout_method', $vendor->payout_method) === 'stripe')>Stripe Connect</option>
                        <option value="paypal" @selected(old('payout_method', $vendor->payout_method) === 'paypal')>PayPal Payouts</option>
                    </select>
                </div>
                <div>
                    <label class="label" for="paypal_email">PayPal email</label>
                    <input id="paypal_email" type="email" name="paypal_email" class="input" value="{{ old('paypal_email', $vendor->paypal_email) }}">
                </div>
                <div>
                    <label class="label" for="payout_hold_days">Hold period (days)</label>
                    <input id="payout_hold_days" type="number" min="0" max="365" name="payout_hold_days" class="input" value="{{ old('payout_hold_days', $vendor->payout_hold_days) }}" placeholder="Inherit">
                </div>
                <div>
                    <label class="label" for="status">Status</label>
                    <select id="status" name="status" class="select">
                        @foreach(['pending', 'approved', 'suspended'] as $option)
                            <option value="{{ $option }}" @selected(old('status', $vendor->status) === $option)>{{ ucfirst($option) }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        <div class="card card-pad">
            <label class="label" for="admin_notes">Private notes</label>
            <textarea id="admin_notes" name="admin_notes" rows="3" class="input">{{ old('admin_notes', $vendor->admin_notes) }}</textarea>
        </div>
    </div>

    <div class="space-y-5">
        <div class="card card-pad">
            <h2 class="font-display text-lg">Before you save</h2>
            <ul class="mt-3 space-y-2 text-xs leading-5 text-[#687771]">
                <li>• Approving a seller makes their active products visible in the marketplace.</li>
                <li>• Stripe Connect onboarding is started from the seller page after saving.</li>
                <li>• Commission is calculated on the item subtotal after discount. Shipping and tax stay with the platform.</li>
            </ul>
        </div>
        <div class="flex flex-wrap gap-3">
            <button class="btn-primary !px-6 !py-3 text-xs">{{ $vendor->exists ? 'Save seller' : 'Create seller' }}</button>
            <a href="{{ $vendor->exists ? route('admin.vendors.show', $vendor) : route('admin.vendors.index') }}" class="btn-secondary !px-6 !py-3 text-xs">Cancel</a>
        </div>
    </div>
</form>
@endsection
