@extends('admin.layouts.app')

@section('title', 'Vendors')
@section('eyebrow', 'Marketplace')
@section('heading', 'Vendors')

@section('content')
@if(! nexus_mode()?->allowsVendors())
    <div class="card card-pad mb-5 !border-amber-300 !bg-[#fff8ec]">
        <h2 class="font-display text-lg text-[#7a4c07]">Multi-vendor mode is not active</h2>
        <p class="mt-1 text-sm leading-6 text-[#7a5a1e]">Switch the store model to Multi-Vendor Marketplace in <a href="{{ route('admin.settings.index', ['section' => 'mode']) }}" class="font-bold underline">Settings → Business model</a> before opening the marketplace. You can still prepare seller records now.</p>
    </div>
@endif

<div class="mb-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
    @foreach([
        ['label' => 'All sellers', 'value' => $counts['all'], 'filter' => ''],
        ['label' => 'Awaiting approval', 'value' => $counts['pending'], 'filter' => 'pending'],
        ['label' => 'Approved', 'value' => $counts['approved'], 'filter' => 'approved'],
        ['label' => 'Suspended', 'value' => $counts['suspended'], 'filter' => 'suspended'],
    ] as $card)
        <a href="{{ route('admin.vendors.index', array_filter(['status' => $card['filter']])) }}" class="card card-pad transition hover:-translate-y-0.5">
            <div class="text-[10px] font-bold uppercase tracking-[.14em] text-[#8a9892]">{{ $card['label'] }}</div>
            <div class="mt-2 font-display text-3xl">{{ $card['value'] }}</div>
        </a>
    @endforeach
</div>

<div class="mb-5 flex flex-wrap items-center justify-between gap-3">
    <form method="get" class="flex flex-wrap items-end gap-3">
        <div>
            <label class="label" for="q">Search</label>
            <input id="q" name="q" value="{{ $search }}" class="input !w-56" placeholder="Name or email">
        </div>
        <div>
            <label class="label" for="status">Status</label>
            <select id="status" name="status" class="select !w-40">
                <option value="">All</option>
                @foreach(['pending', 'approved', 'suspended'] as $option)
                    <option value="{{ $option }}" @selected($status === $option)>{{ ucfirst($option) }}</option>
                @endforeach
            </select>
        </div>
        <button class="btn-secondary !px-5 !py-2.5 text-xs">Filter</button>
    </form>
    <a href="{{ route('admin.vendors.create') }}" class="btn-primary !px-5 !py-2.5 text-xs">+ New seller</a>
</div>

<div class="table-wrap">
    <table class="data-table">
        <thead><tr><th>Seller</th><th>Status</th><th>Products</th><th>Commission</th><th>Payouts</th><th>Balance</th><th></th></tr></thead>
        <tbody>
            @forelse($vendors as $vendor)
                @php($rule = $vendor->commissionRule())
                <tr>
                    <td>
                        <div class="flex items-center gap-3">
                            <span class="grid h-10 w-10 place-items-center rounded-xl bg-[#e7f5f0] text-xs font-bold text-[#0f766e]">{{ $vendor->initials() }}</span>
                            <div class="min-w-0">
                                <a href="{{ route('admin.vendors.show', $vendor) }}" class="block truncate font-semibold hover:text-[#0f766e]">{{ $vendor->name }}</a>
                                <div class="truncate text-[11px] text-[#8a9892]">{{ $vendor->email }}</div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <span class="badge {{ $vendor->status === 'approved' ? 'badge-emerald' : ($vendor->status === 'suspended' ? 'badge-rose' : 'badge-amber') }}">{{ ucfirst($vendor->status) }}</span>
                    </td>
                    <td class="text-xs">{{ $vendor->products_count }}</td>
                    <td class="text-xs">
                        @if($rule['type'] === 'fixed')
                            {{ nexus_money($rule['fixed']) }} fixed
                        @elseif($rule['type'] === 'combined')
                            {{ rtrim(rtrim(number_format($rule['percent'], 2), '0'), '.') }}% + {{ nexus_money($rule['fixed']) }}
                        @else
                            {{ rtrim(rtrim(number_format($rule['percent'], 2), '0'), '.') }}%
                        @endif
                    </td>
                    <td class="text-xs">{{ $vendor->payoutMethodLabel() }}</td>
                    <td class="text-sm font-semibold">{{ nexus_money($vendor->balance) }}</td>
                    <td class="text-right"><a href="{{ route('admin.vendors.show', $vendor) }}" class="text-[11px] font-bold text-[#0f766e]">Manage</a></td>
                </tr>
            @empty
                <tr><td colspan="7" class="py-10 text-center text-sm text-[#8a9892]">No sellers yet. <a href="{{ route('admin.vendors.create') }}" class="font-bold text-[#0f766e]">Add your first seller</a>.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-5">{{ $vendors->links() }}</div>
@endsection
