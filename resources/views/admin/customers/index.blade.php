@extends('admin.layouts.app')

@section('title', 'Customers')
@section('eyebrow', 'Sell')
@section('heading', 'Customers')

@section('content')
<form method="get" class="card mb-5 grid gap-3 p-4 sm:grid-cols-[1fr_220px_auto]">
    <div>
        <label class="label" for="q">Search</label>
        <input id="q" name="q" value="{{ request('q') }}" class="input" placeholder="Name, email or company">
    </div>
    <div>
        <label class="label" for="wholesale_status">Wholesale</label>
        <select id="wholesale_status" name="wholesale_status" class="select">
            <option value="">All customers</option>
            @foreach(['none' => 'Retail', 'pending' => 'Pending approval', 'approved' => 'Approved trade', 'rejected' => 'Rejected'] as $value => $label)
                <option value="{{ $value }}" @selected(request('wholesale_status') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="flex items-end"><button class="btn-primary !px-6">Filter</button></div>
</form>

<div class="table-wrap">
    <table class="data-table">
        <thead><tr><th>Customer</th><th>Company</th><th>Tier</th><th>Wholesale</th><th>Joined</th><th></th></tr></thead>
        <tbody>
            @forelse($customers as $customer)
                <tr>
                    <td>
                        <div class="flex items-center gap-3">
                            <span class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-[#e7f5f0] text-[10px] font-bold text-[#0f766e]">{{ $customer->initials() }}</span>
                            <div class="min-w-0">
                                <a href="{{ route('admin.customers.show', $customer) }}" class="block truncate font-semibold hover:text-[#0f766e]">{{ $customer->name }}</a>
                                <div class="truncate text-[11px] text-[#8a9892]">{{ $customer->email }}</div>
                            </div>
                        </div>
                    </td>
                    <td class="text-sm">{{ $customer->company_name ?: '—' }}</td>
                    <td class="text-xs">{{ $customer->tier?->name ?? 'Retail' }}</td>
                    <td><span class="badge badge-{{ nexus_status_badge($customer->wholesale_status?->value ?? 'none') }}">{{ $customer->wholesale_status?->label() ?? 'Retail' }}</span></td>
                    <td class="text-xs">{{ $customer->created_at?->format('M j, Y') }}</td>
                    <td class="text-right"><a href="{{ route('admin.customers.show', $customer) }}" class="text-xs font-bold text-[#0f766e]">View →</a></td>
                </tr>
            @empty
                <tr><td colspan="6" class="py-10 text-center text-sm text-[#8a9892]">No customers match these filters.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="mt-5">{{ $customers->links() }}</div>
@endsection
