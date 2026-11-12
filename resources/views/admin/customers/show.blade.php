@extends('admin.layouts.app')

@section('title', $customer->name)
@section('eyebrow', 'Customers')
@section('heading', $customer->name)

@section('content')
<div class="mb-5 flex flex-wrap items-center justify-between gap-3">
    <div class="flex items-center gap-3">
        <span class="grid h-11 w-11 place-items-center rounded-full bg-[#102522] text-sm font-bold text-white">{{ $customer->initials() }}</span>
        <div>
            <div class="font-semibold">{{ $customer->name }}</div>
            <div class="text-xs text-[#8a9892]">{{ $customer->email }} · joined {{ $customer->created_at?->format('M j, Y') }}</div>
        </div>
    </div>
    <a href="{{ route('admin.customers.index') }}" class="text-xs font-bold uppercase tracking-[.1em] text-[#4d5d56] hover:text-[#0f766e]">← All customers</a>
</div>

<div class="grid gap-5 xl:grid-cols-[1.4fr_1fr]">
    <div class="space-y-5">
        <div class="table-wrap">
            <div class="border-b border-[#e3e9e5] px-5 py-4"><h2 class="font-display text-xl">Recent orders</h2></div>
            <table class="data-table">
                <thead><tr><th>Order</th><th>Date</th><th>Status</th><th class="text-right">Total</th></tr></thead>
                <tbody>
                    @forelse($customer->orders as $order)
                        <tr>
                            <td><a href="{{ route('admin.orders.show', $order) }}" class="font-semibold text-[#0f766e]">{{ $order->number }}</a></td>
                            <td class="text-xs">{{ $order->created_at?->format('M j, Y') }}</td>
                            <td><span class="badge badge-{{ nexus_status_badge($order->status->value) }}">{{ $order->status->label() }}</span></td>
                            <td class="text-right font-semibold">{{ nexus_money($order->grand_total, $order->currency) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="py-8 text-center text-sm text-[#8a9892]">No orders yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($customer->subscriptions->isNotEmpty())
            <div class="table-wrap">
                <div class="border-b border-[#e3e9e5] px-5 py-4"><h2 class="font-display text-xl">Subscriptions</h2></div>
                <table class="data-table">
                    <thead><tr><th>Product</th><th>Plan</th><th>Status</th><th>Renews</th></tr></thead>
                    <tbody>
                        @foreach($customer->subscriptions as $subscription)
                            <tr>
                                <td class="font-semibold">{{ $subscription->product?->name }}</td>
                                <td class="text-xs">{{ $subscription->plan?->name }}</td>
                                <td><span class="badge badge-{{ nexus_status_badge($subscription->status->value) }}">{{ $subscription->status->label() }}</span></td>
                                <td class="text-xs">{{ $subscription->current_period_end?->format('M j, Y') ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <div class="space-y-5">
        <form method="post" action="{{ route('admin.customers.update', $customer) }}" class="card card-pad">
            @csrf
            @method('PATCH')
            <h2 class="font-display text-xl">Account details</h2>
            <div class="mt-4 space-y-3">
                <div>
                    <label class="label" for="name">Name</label>
                    <input id="name" name="name" value="{{ old('name', $customer->name) }}" class="input" required>
                </div>
                <div>
                    <label class="label" for="email">Email</label>
                    <input id="email" type="email" name="email" value="{{ old('email', $customer->email) }}" class="input" required>
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="label" for="company_name">Company</label>
                        <input id="company_name" name="company_name" value="{{ old('company_name', $customer->company_name) }}" class="input">
                    </div>
                    <div>
                        <label class="label" for="phone">Phone</label>
                        <input id="phone" name="phone" value="{{ old('phone', $customer->phone) }}" class="input">
                    </div>
                </div>
                <div>
                    <label class="label" for="tax_id">Tax / VAT ID</label>
                    <input id="tax_id" name="tax_id" value="{{ old('tax_id', $customer->tax_id) }}" class="input">
                </div>
            </div>

            <h2 class="mt-6 font-display text-xl">Wholesale access</h2>
            <div class="mt-4 space-y-3">
                <div>
                    <label class="label" for="wholesale_status">Status</label>
                    <select id="wholesale_status" name="wholesale_status" class="select">
                        @foreach(['none' => 'Retail customer', 'pending' => 'Pending approval', 'approved' => 'Approved trade account', 'rejected' => 'Rejected'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('wholesale_status', $customer->wholesale_status?->value ?? 'none') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="label" for="tier_id">Pricing tier</label>
                    <select id="tier_id" name="tier_id" class="select">
                        <option value="">No tier (retail pricing)</option>
                        @foreach($tiers as $tier)
                            <option value="{{ $tier->id }}" @selected((int) old('tier_id', $customer->tier_id) === $tier->id)>{{ $tier->name }} @if($tier->discount_percent > 0)· {{ $tier->discount_percent }}% off @endif</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="label" for="admin_notes">Internal notes</label>
                    <textarea id="admin_notes" name="admin_notes" rows="3" class="textarea">{{ old('admin_notes', $customer->admin_notes) }}</textarea>
                </div>
                @if($customer->wholesale_applied_at)
                    <p class="text-[11px] text-[#8a9892]">Applied {{ $customer->wholesale_applied_at->format('M j, Y') }}@if($customer->wholesale_approved_at) · approved {{ $customer->wholesale_approved_at->format('M j, Y') }}@endif</p>
                @endif
            </div>
            <button class="btn-primary mt-5 w-full">Save customer</button>
        </form>

        <div class="card card-pad text-xs leading-5 text-[#687771]">
            <div class="text-[10px] font-bold uppercase tracking-[.14em] text-[#8a9892]">Activity</div>
            <div class="mt-2 flex justify-between"><span>Last sign in</span><span class="font-semibold">{{ $customer->last_login_at?->diffForHumans() ?? 'Never' }}</span></div>
            <div class="mt-1 flex justify-between"><span>Last IP</span><span class="font-semibold">{{ $customer->last_login_ip ?? '—' }}</span></div>
            <div class="mt-1 flex justify-between"><span>Lifetime orders</span><span class="font-semibold">{{ $customer->orders->count() }}</span></div>
        </div>
    </div>
</div>
@endsection
