@extends('admin.layouts.app')

@section('title', 'Taxes')
@section('eyebrow', 'Operations')
@section('heading', 'Tax rates')

@section('content')
<div class="grid gap-5 xl:grid-cols-[1.5fr_1fr]">
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th>Name</th><th>Country</th><th>State</th><th>Rate</th><th>Priority</th><th>Status</th><th></th></tr></thead>
            <tbody>
                @forelse($rates as $rate)
                    <tr>
                        <td class="font-semibold">{{ $rate->name }}</td>
                        <td class="text-xs">{{ $rate->country }}</td>
                        <td class="text-xs">{{ $rate->state ?: 'All' }}</td>
                        <td class="text-sm font-semibold">{{ rtrim(rtrim(number_format($rate->rate, 3), '0'), '.') }}%</td>
                        <td class="text-xs">{{ $rate->priority }}</td>
                        <td><span class="badge {{ $rate->is_active ? 'badge-emerald' : 'badge-slate' }}">{{ $rate->is_active ? 'Active' : 'Inactive' }}</span></td>
                        <td class="text-right">
                            <details class="relative inline-block text-left">
                                <summary class="cursor-pointer list-none text-xs font-bold text-[#0f766e]">Edit</summary>
                                <form method="post" action="{{ route('admin.taxes.update', $rate) }}" class="absolute right-0 z-20 mt-2 w-64 space-y-2 rounded-xl border border-[#e3e9e5] bg-white p-4 text-left shadow-xl">
                                    @csrf
                                    @method('PATCH')
                                    <input name="name" value="{{ $rate->name }}" class="input !py-2 text-xs" required>
                                    <div class="grid grid-cols-2 gap-2">
                                        <input name="country" value="{{ $rate->country }}" class="input !py-2 text-xs" maxlength="2" required>
                                        <input name="state" value="{{ $rate->state }}" class="input !py-2 text-xs" placeholder="State">
                                    </div>
                                    <input name="rate" type="number" step="0.001" min="0" max="100" value="{{ $rate->rate }}" class="input !py-2 text-xs" required>
                                    <input name="priority" type="number" min="0" value="{{ $rate->priority }}" class="input !py-2 text-xs">
                                    <label class="flex items-center gap-2 text-xs"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" class="accent-[#0f766e]" @checked($rate->is_active)> Active</label>
                                    <button class="btn-primary w-full !py-2 text-xs">Save rate</button>
                                </form>
                            </details>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="py-10 text-center text-sm text-[#8a9892]">No tax rates configured.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="space-y-5">
        <form method="post" action="{{ route('admin.taxes.store') }}" class="card card-pad">
            @csrf
            <h2 class="font-display text-xl">Add tax rate</h2>
            <div class="mt-4 space-y-3">
                <div><label class="label" for="name">Name</label><input id="name" name="name" class="input" placeholder="US sales tax" required></div>
                <div class="grid grid-cols-2 gap-3">
                    <div><label class="label" for="country">Country</label><input id="country" name="country" class="input" placeholder="US" maxlength="2" required></div>
                    <div><label class="label" for="state">State</label><input id="state" name="state" class="input" placeholder="Optional"></div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div><label class="label" for="rate">Rate %</label><input id="rate" type="number" step="0.001" min="0" max="100" name="rate" class="input" required></div>
                    <div><label class="label" for="priority">Priority</label><input id="priority" type="number" min="0" name="priority" value="0" class="input"></div>
                </div>
                <label class="flex items-center gap-2 text-xs"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" class="accent-[#0f766e]" checked> Active</label>
            </div>
            <button class="btn-primary mt-4 w-full">Create rate</button>
        </form>
        <div class="card card-pad text-xs leading-5 text-[#687771]">
            <div class="text-[10px] font-bold uppercase tracking-[.14em] text-[#8a9892]">Tax settings</div>
            <p class="mt-2">Whether prices include tax, and whether shipping is taxable, are configured under <a href="{{ route('admin.settings.index', ['section' => 'checkout']) }}" class="font-bold text-[#0f766e]">Settings → Checkout</a>.</p>
        </div>
    </div>
</div>
<div class="mt-5">{{ $rates->links() }}</div>
@endsection
