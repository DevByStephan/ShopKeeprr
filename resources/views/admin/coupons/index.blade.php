@extends('admin.layouts.app')

@section('title', 'Coupons')
@section('eyebrow', 'Sell')
@section('heading', 'Coupons')

@section('content')
<div class="mb-5 flex items-center justify-between gap-3">
    <p class="text-sm text-[#687771]">Percentage, fixed-amount and free-shipping codes with limits and expiry dates.</p>
    <a href="{{ route('admin.coupons.create') }}" class="btn-primary !px-5 !py-2.5 text-xs">+ New coupon</a>
</div>

<div class="table-wrap">
    <table class="data-table">
        <thead><tr><th>Code</th><th>Type</th><th>Value</th><th>Used</th><th>Window</th><th>Status</th><th></th></tr></thead>
        <tbody>
            @forelse($coupons as $coupon)
                <tr>
                    <td class="font-mono text-xs font-bold">{{ $coupon->code }}</td>
                    <td class="text-xs capitalize">{{ str_replace('_', ' ', $coupon->type->value) }}</td>
                    <td class="text-sm font-semibold">
                        @if($coupon->type->value === 'percentage') {{ rtrim(rtrim(number_format((float) $coupon->value, 2), '0'), '.') }}%
                        @elseif($coupon->type->value === 'fixed') {{ nexus_money($coupon->value) }}
                        @else Free shipping @endif
                    </td>
                    <td class="text-xs">{{ $coupon->used_count }}{{ $coupon->usage_limit ? ' / '.$coupon->usage_limit : '' }}</td>
                    <td class="text-xs text-[#687771]">
                        {{ $coupon->starts_at?->format('M j, Y') ?? 'Anytime' }} – {{ $coupon->expires_at?->format('M j, Y') ?? 'No expiry' }}
                    </td>
                    <td><span class="badge {{ $coupon->is_active ? 'badge-emerald' : 'badge-slate' }}">{{ $coupon->is_active ? 'Active' : 'Inactive' }}</span></td>
                    <td class="text-right"><a href="{{ route('admin.coupons.edit', $coupon) }}" class="text-xs font-bold text-[#0f766e]">Edit →</a></td>
                </tr>
            @empty
                <tr><td colspan="7" class="py-10 text-center text-sm text-[#8a9892]">No coupons yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="mt-5">{{ $coupons->links() }}</div>
@endsection
