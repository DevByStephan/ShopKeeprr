@extends('admin.layouts.app')

@section('title', 'Subscriptions')
@section('eyebrow', 'Sell')
@section('heading', 'Subscriptions')

@section('content')
<form method="get" class="card mb-5 flex flex-wrap items-end gap-3 p-4">
    <div class="min-w-[200px] flex-1">
        <label class="label" for="q">Search</label>
        <input id="q" name="q" value="{{ request('q') }}" class="input" placeholder="Customer or product">
    </div>
    <div>
        <label class="label" for="status">Status</label>
        <select id="status" name="status" class="select !w-44">
            <option value="">All statuses</option>
            @foreach(\App\Enums\SubscriptionStatus::cases() as $status)
                <option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>
            @endforeach
        </select>
    </div>
    <button class="btn-secondary !px-5 !py-2.5 text-xs">Filter</button>
</form>

<div class="table-wrap">
    <table class="data-table">
        <thead><tr><th>Customer</th><th>Product</th><th>Plan</th><th>Status</th><th>Renews</th><th class="text-right">Value</th><th></th></tr></thead>
        <tbody>
            @forelse($subscriptions as $subscription)
                <tr>
                    <td>
                        <div class="text-sm font-semibold">{{ $subscription->user?->name ?? '—' }}</div>
                        <div class="text-[11px] text-[#8a9892]">{{ $subscription->user?->email }}</div>
                    </td>
                    <td class="text-sm">{{ $subscription->product?->name ?? '—' }}</td>
                    <td class="text-xs">{{ $subscription->plan?->name ?? '—' }} · every {{ $subscription->intervalLabel() }}</td>
                    <td><span class="badge badge-{{ nexus_status_badge($subscription->status->value) }}">{{ $subscription->status->label() }}</span></td>
                    <td class="text-xs">{{ $subscription->current_period_end?->format('M j, Y') ?? '—' }}</td>
                    <td class="text-right text-sm font-semibold">{{ nexus_money($subscription->price, $subscription->currency) }}</td>
                    <td class="text-right"><a href="{{ route('admin.subscriptions.show', $subscription) }}" class="text-xs font-bold text-[#0f766e]">View →</a></td>
                </tr>
            @empty
                <tr><td colspan="7" class="py-10 text-center text-sm text-[#8a9892]">No subscriptions yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="mt-5">{{ $subscriptions->links() }}</div>
@endsection
