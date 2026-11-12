@extends('admin.layouts.app')

@section('title', 'Catalog integrity audit')
@section('eyebrow', 'System')
@section('heading', 'Catalog integrity audit')

@section('content')
<div class="mb-5 flex flex-wrap items-center justify-between gap-3">
    <p class="max-w-3xl text-sm leading-6 text-[#687771]">This audit is read-only. It finds products whose attributes contradict every business model and historical order/seller records that do not match their original model. Historical financial records are never rewritten by this screen.</p>
    <div class="flex gap-2">
        <a href="{{ route('admin.store-mode.history') }}" class="btn-secondary !px-4 !py-2 text-xs">Switch history</a>
        <a href="{{ route('admin.settings.index', ['section' => 'mode']) }}" class="btn-secondary !px-4 !py-2 text-xs">Store settings</a>
    </div>
</div>

<div class="grid gap-4 sm:grid-cols-3">
    <div class="card card-pad">
        <div class="text-[10px] font-bold uppercase tracking-[.14em] text-[#8a9892]">Product conflicts</div>
        <div class="mt-2 font-display text-3xl">{{ $integrity['conflicts'] }}</div>
        <p class="mt-1 text-xs text-[#687771]">Seller + wholesale + subscription combinations.</p>
    </div>
    <div class="card card-pad">
        <div class="text-[10px] font-bold uppercase tracking-[.14em] text-[#8a9892]">Cross-model order lines</div>
        <div class="mt-2 font-display text-3xl">{{ $integrity['cross_model_order_items'] }}</div>
        <p class="mt-1 text-xs text-[#687771]">Preserved for history; review reporting.</p>
    </div>
    <div class="card card-pad">
        <div class="text-[10px] font-bold uppercase tracking-[.14em] text-[#8a9892]">Cross-model seller splits</div>
        <div class="mt-2 font-display text-3xl">{{ $integrity['cross_model_vendor_splits'] }}</div>
        <p class="mt-1 text-xs text-[#687771]">Payout obligations remain unchanged.</p>
    </div>
</div>

<div class="mt-5 card card-pad">
    <h2 class="font-display text-xl">Products that cannot sell in any model</h2>
    <p class="mt-1 text-xs leading-5 text-[#8a9892]">Fix these in the product editor: a seller product cannot be wholesale-only or use subscription plans, and a wholesale-only product cannot use subscription plans.</p>

    @if($integrity['conflict_products'])
        <div class="mt-4 overflow-hidden rounded-xl border border-[#e3e9e5]">
            <table class="data-table">
                <thead><tr><th>Product</th><th>Conflicts</th><th></th></tr></thead>
                <tbody>
                    @foreach($integrity['conflict_products'] as $row)
                        <tr>
                            <td class="font-semibold">{{ $row['name'] }}</td>
                            <td class="text-xs">
                                <ul class="space-y-1">
                                    @foreach($row['reasons'] as $reason)
                                        <li>{{ $reason }}</li>
                                    @endforeach
                                </ul>
                            </td>
                            <td class="text-right"><a href="{{ route('admin.products.edit', $row['id']) }}" class="text-[11px] font-bold text-[#0f766e]">Fix</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <p class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900">No contradictory product attributes were found.</p>
    @endif
</div>

<div class="mt-5 card card-pad">
    <h2 class="font-display text-xl">Historical data notes</h2>
    <ul class="mt-3 space-y-2 text-sm leading-6 text-[#687771]">
        <li>Cross-model order lines are reported, not modified. An order keeps the model it was placed under, including its totals, tax, shipping and refunds.</li>
        <li>Seller splits and payouts are financial obligations. They stay attached to the original Multi-Vendor order even after the store changes model.</li>
        <li>If an anomaly is only a reporting mismatch, document it and leave the financial record intact.</li>
        <li>Run this audit after importing data, after restoring a backup, and before switching models.</li>
    </ul>
</div>
@endsection
