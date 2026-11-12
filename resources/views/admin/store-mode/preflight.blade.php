@extends('admin.layouts.app')

@section('title', 'Switch store model')
@section('eyebrow', 'System')
@section('heading', 'Switch store model')

@section('content')
@php
    $catalog = $report['catalog'];
    $orders = $report['orders'];
    $subscriptions = $report['subscriptions'];
    $vendors = $report['vendors'];
    $carts = $report['carts'];
    $integrity = $report['integrity'];
@endphp

<div class="mb-5 flex flex-wrap items-center justify-between gap-3">
    <div>
        <div class="text-[11px] font-bold uppercase tracking-[.16em] text-[#8a9892]">Preflight</div>
        <h1 class="mt-1 font-display text-2xl">{{ $current->label() }} <span class="text-[#8a9892]">→</span> {{ $target->label() }}</h1>
        <p class="mt-1 max-w-3xl text-sm leading-6 text-[#687771]">{{ $target->description() }}</p>
    </div>
    <div class="flex flex-wrap gap-2">
        <a href="{{ route('admin.store-mode.history') }}" class="btn-secondary !px-4 !py-2 text-xs">Switch history</a>
        <a href="{{ route('admin.store-mode.integrity') }}" class="btn-secondary !px-4 !py-2 text-xs">Integrity audit</a>
        <a href="{{ route('admin.settings.index', ['section' => 'mode']) }}" class="btn-secondary !px-4 !py-2 text-xs">Cancel</a>
    </div>
</div>

@if($report['blockers'])
    <div class="mb-5 rounded-2xl border border-rose-200 bg-rose-50 p-5">
        <h2 class="font-display text-lg text-rose-900">Resolve these before switching</h2>
        <ul class="mt-3 space-y-2 text-sm leading-6 text-rose-900">
            @foreach($report['blockers'] as $blocker)
                <li><strong>{{ $blocker['code'] }}:</strong> {{ $blocker['message'] }}</li>
            @endforeach
        </ul>
        <p class="mt-3 text-xs leading-5 text-rose-800">Nothing has been changed. The switch is rejected atomically if a blocker is still present when it is submitted.</p>
    </div>
@else
    <div class="mb-5 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900">
        No blockers were found. Review the warnings below, then confirm the switch.
    </div>
@endif

@if($report['warnings'])
    <div class="mb-5 rounded-2xl border border-amber-200 bg-amber-50 p-5">
        <h2 class="font-display text-lg text-amber-900">What will change</h2>
        <ul class="mt-3 space-y-2 text-sm leading-6 text-amber-900">
            @foreach($report['warnings'] as $warning)
                <li>{{ $warning['message'] }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
    <div class="card card-pad">
        <div class="text-[10px] font-bold uppercase tracking-[.14em] text-[#8a9892]">Catalog</div>
        <div class="mt-2 font-display text-3xl">{{ $catalog['total'] }}</div>
        <p class="mt-1 text-xs leading-5 text-[#687771]">{{ $catalog['compatible'] }} sellable · {{ $catalog['incompatible'] }} incompatible</p>
        <p class="mt-2 text-[11px] text-[#8a9892]">{{ $catalog['will_archive'] }} to archive · {{ $catalog['already_archived'] }} already hidden · {{ $catalog['will_restore'] }} to restore</p>
    </div>
    <div class="card card-pad">
        <div class="text-[10px] font-bold uppercase tracking-[.14em] text-[#8a9892]">Orders</div>
        <div class="mt-2 font-display text-3xl">{{ $orders['total'] }}</div>
        <p class="mt-1 text-xs leading-5 text-[#687771]">{{ $orders['pending'] }} open · {{ $orders['unpaid_pending'] }} unpaid</p>
        <p class="mt-2 text-[11px] text-[#8a9892]">Historical rows are never rewritten.</p>
    </div>
    <div class="card card-pad">
        <div class="text-[10px] font-bold uppercase tracking-[.14em] text-[#8a9892]">Subscriptions</div>
        <div class="mt-2 font-display text-3xl">{{ $subscriptions['active'] }}</div>
        <p class="mt-1 text-xs leading-5 text-[#687771]">{{ $subscriptions['auto_renew'] ? 'Auto-renew on' : 'Auto-renew off' }} · {{ $subscriptions['gateway_ready'] ? 'gateway ready' : 'no gateway' }}</p>
        <p class="mt-2 text-[11px] text-[#8a9892]">Next renewal: {{ $subscriptions['next_billing_at'] ? \Illuminate\Support\Carbon::parse($subscriptions['next_billing_at'])->toFormattedDateString() : 'none' }}</p>
    </div>
    <div class="card card-pad">
        <div class="text-[10px] font-bold uppercase tracking-[.14em] text-[#8a9892]">Seller balances</div>
        <div class="mt-2 font-display text-3xl">{{ nexus_money($vendors['outstanding']) }}</div>
        <p class="mt-1 text-xs leading-5 text-[#687771]">{{ $vendors['payable_count'] }} seller(s) · {{ $vendors['unpayoutable'] }} payout issue(s)</p>
        <p class="mt-2 text-[11px] text-[#8a9892]">{{ $vendors['approved_vendors'] }} approved seller account(s)</p>
    </div>
</div>

<div class="mt-5 grid gap-5 xl:grid-cols-[1.35fr_1fr]">
    <div class="space-y-5">
        <div class="card card-pad">
            <h2 class="font-display text-xl">Catalog impact</h2>
            <p class="mt-1 text-xs leading-5 text-[#8a9892]">Incompatible products are archived for the target model only. A merchant-hidden product stays hidden; an automatic archive can be restored if the product becomes compatible later.</p>

            @if($catalog['sample'])
                <div class="mt-4 overflow-hidden rounded-xl border border-[#e3e9e5]">
                    <table class="data-table">
                        <thead><tr><th>Product</th><th>Why it cannot sell</th><th>State</th></tr></thead>
                        <tbody>
                            @foreach($catalog['sample'] as $row)
                                <tr>
                                    <td class="font-semibold">{{ $row['name'] }}</td>
                                    <td class="text-xs">{{ $row['reason'] }}</td>
                                    <td><span class="badge {{ $row['already_archived'] ? 'badge-slate' : 'badge-amber' }}">{{ $row['already_archived'] ? 'Already hidden' : 'Will archive' }}</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if($catalog['incompatible'] > count($catalog['sample']))
                    <p class="mt-2 text-[11px] text-[#8a9892]">Showing the first {{ count($catalog['sample']) }} of {{ $catalog['incompatible'] }} incompatible products.</p>
                @endif
            @else
                <p class="mt-4 rounded-xl border border-[#e3e9e5] bg-[#fafcfb] p-4 text-sm text-[#687771]">Every product in the catalog is compatible with {{ $target->label() }}.</p>
            @endif
        </div>

        <div class="card card-pad">
            <h2 class="font-display text-xl">Orders by original model</h2>
            <div class="mt-4 grid gap-2 sm:grid-cols-2">
                @forelse($orders['by_mode'] as $mode => $count)
                    <div class="flex items-center justify-between rounded-lg border border-[#e3e9e5] px-3 py-2 text-sm">
                        <span class="font-semibold">{{ ucfirst($mode) }}</span>
                        <span class="text-[#687771]">{{ $count }}</span>
                    </div>
                @empty
                    <p class="text-sm text-[#687771]">No orders yet.</p>
                @endforelse
            </div>
            <p class="mt-3 text-[11px] leading-5 text-[#8a9892]">Pending orders keep their original model, totals, payments and fulfillment. Refunds and seller payouts continue through the original transaction context.</p>
        </div>

        <div class="card card-pad">
            <h2 class="font-display text-xl">Carts and integrity</h2>
            <div class="mt-4 grid gap-3 sm:grid-cols-3">
                <div class="rounded-lg border border-[#e3e9e5] p-3">
                    <div class="text-[10px] font-bold uppercase tracking-[.12em] text-[#8a9892]">Active carts</div>
                    <div class="mt-1 font-display text-2xl">{{ $carts['with_items'] }}</div>
                    <div class="text-[11px] text-[#8a9892]">{{ $carts['items'] }} units</div>
                </div>
                <div class="rounded-lg border border-[#e3e9e5] p-3">
                    <div class="text-[10px] font-bold uppercase tracking-[.12em] text-[#8a9892]">Stale carts</div>
                    <div class="mt-1 font-display text-2xl">{{ $carts['stale'] }}</div>
                    <div class="text-[11px] text-[#8a9892]">Repriced after switch</div>
                </div>
                <div class="rounded-lg border border-[#e3e9e5] p-3">
                    <div class="text-[10px] font-bold uppercase tracking-[.12em] text-[#8a9892]">Conflicts</div>
                    <div class="mt-1 font-display text-2xl">{{ $integrity['conflicts'] }}</div>
                    <div class="text-[11px] text-[#8a9892]">Products to review</div>
                </div>
            </div>

            @if($integrity['cross_model_order_items'] || $integrity['cross_model_vendor_splits'])
                <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs leading-5 text-amber-900">
                    {{ $integrity['cross_model_order_items'] }} historical order line(s) and {{ $integrity['cross_model_vendor_splits'] }} seller split(s) do not match their order model. They are preserved for financial history; review the integrity audit if reporting looks wrong.
                </div>
            @endif

            <a href="{{ route('admin.store-mode.integrity') }}" class="mt-4 inline-block text-xs font-bold text-[#0f766e]">Open the full integrity audit →</a>
        </div>
    </div>

    <div class="space-y-5">
        <div class="card card-pad">
            <h2 class="font-display text-xl">Confirm switch</h2>
            <p class="mt-1 text-xs leading-5 text-[#8a9892]">The model and catalog state change in one database transaction. External payment operations never run inside it. Carts are revalidated immediately afterwards.</p>

            <form method="post" action="{{ route('admin.store-mode.switch') }}" class="mt-4 space-y-4">
                @csrf
                <input type="hidden" name="mode" value="{{ $target->value }}">
                <input type="hidden" name="live" value="{{ $live ? 1 : 0 }}">

                <div>
                    <label class="label" for="note">Reason or note (optional)</label>
                    <textarea id="note" name="note" rows="3" maxlength="1000" class="textarea" placeholder="e.g. Moving from retail to a seller marketplace.">{{ old('note') }}</textarea>
                </div>

                <label class="flex items-start gap-2.5 rounded-xl border border-[#e3e9e5] bg-[#fafcfb] p-3 text-sm">
                    <input type="checkbox" name="confirm" value="1" class="mt-0.5 accent-[#0f766e]" @checked(old('confirm')) required>
                    <span>I have reviewed the blockers and warnings and want to switch to <strong>{{ $target->label() }}</strong>.</span>
                </label>

                <div class="rounded-xl border border-[#e3e9e5] p-3 text-xs leading-5 text-[#687771]">
                    Storefront after switch: <strong>{{ $live ? 'Live' : 'Offline (holding page)' }}</strong>. You can change availability from Store settings without switching models again.
                </div>

                <button class="btn-primary w-full" @disabled($report['blockers'])>
                    {{ $report['blockers'] ? 'Resolve blockers first' : 'Switch to '.$target->label() }}
                </button>
            </form>
        </div>

        <div class="card card-pad">
            <h2 class="font-display text-xl">What is preserved</h2>
            <ul class="mt-3 space-y-2 text-xs leading-5 text-[#687771]">
                <li>Every historical order keeps its original business model, totals and timestamps.</li>
                <li>Active subscriptions keep renewing through their original gateway.</li>
                <li>Refunds and seller payouts continue against the original order context.</li>
                <li>Merchant-hidden products stay hidden independently of automatic archives.</li>
                <li>Customer accounts, wholesale tiers, settings, themes and uploaded files stay untouched.</li>
                <li>No v1.1 marketplace integration is introduced.</li>
            </ul>
        </div>
    </div>
</div>
@endsection
