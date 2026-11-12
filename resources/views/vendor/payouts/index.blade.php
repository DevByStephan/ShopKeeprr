@extends('vendor.layouts.app')

@section('title', 'Payouts')
@section('eyebrow', 'Money')
@section('heading', 'Payouts')

@section('content')
@if(session('status'))
    <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">{{ session('status') }}</div>
@endif
@if($errors->any())
    <div class="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900">
        @foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach
    </div>
@endif

<div class="mb-5 grid gap-4 sm:grid-cols-3">
    <div class="card card-pad"><div class="text-[10px] font-bold uppercase tracking-[.14em] text-[#8a9892]">Available now</div><div class="mt-2 font-display text-3xl">{{ nexus_money($available) }}</div></div>
    <div class="card card-pad"><div class="text-[10px] font-bold uppercase tracking-[.14em] text-[#8a9892]">On hold</div><div class="mt-2 font-display text-3xl">{{ nexus_money(max(0, $balance - $available)) }}</div></div>
    <div class="card card-pad"><div class="text-[10px] font-bold uppercase tracking-[.14em] text-[#8a9892]">Minimum payout</div><div class="mt-2 font-display text-3xl">{{ nexus_money(nexus_setting('marketplace.minimum_payout', 25)) }}</div></div>
</div>

<div class="grid gap-5 xl:grid-cols-[1.3fr_.9fr]">
    <div class="card card-pad">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="font-display text-xl">Request a payout</h2>
                <p class="mt-1 text-xs leading-5 text-[#687771]">Paid through {{ strtolower($vendor->payoutMethodLabel()) }}. The store's hold period protects the customer's refund window.</p>
            </div>
            <form method="post" action="{{ route('vendor.payouts.request') }}">
                @csrf
                <button class="btn-primary !px-5 !py-2.5 text-xs">Request payout</button>
            </form>
        </div>
        @if($vendor->payout_method === 'stripe')
            <div class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-[#e3e9e5] bg-[#f9fbfa] p-3">
                <div class="text-xs leading-5 text-[#687771]">
                    <strong class="text-[#17231f]">Stripe Connect</strong><br>
                    @if(! $vendor->stripe_account_id)
                        Connect an account to receive automatic transfers.
                    @elseif($vendor->stripe_payouts_enabled)
                        Connected and ready. Transfers are sent automatically when an order is paid.
                    @else
                        Onboarding is incomplete{{ $vendor->stripe_details_submitted ? ' — Stripe is still reviewing the details' : '' }}.
                    @endif
                </div>
                <form method="post" action="{{ route('vendor.payouts.stripe') }}">
                    @csrf
                    <button class="btn-secondary !px-4 !py-2 text-xs">{{ $vendor->stripe_account_id ? 'Continue on Stripe' : 'Connect Stripe' }}</button>
                </form>
            </div>
        @endif
    </div>

    <div class="card card-pad !bg-[#f7f4ec]">
        <h2 class="font-display text-lg">Payout method</h2>
        <dl class="mt-3 space-y-3 text-sm">
            <div><dt class="text-[10px] font-bold uppercase tracking-[.12em] text-[#8a9892]">Selected</dt><dd>{{ $vendor->payoutMethodLabel() }}</dd></div>
            <div><dt class="text-[10px] font-bold uppercase tracking-[.12em] text-[#8a9892]">Commission</dt><dd class="text-xs">{{ ucfirst($vendor->commissionRule()['type']) }} · {{ rtrim(rtrim(number_format($vendor->commissionRule()['percent'], 2), '0'), '.') }}% · {{ nexus_money($vendor->commissionRule()['fixed']) }} fixed</dd></div>
            <div><dt class="text-[10px] font-bold uppercase tracking-[.12em] text-[#8a9892]">Hold period</dt><dd>{{ $vendor->payoutHoldDays() }} day(s)</dd></div>
            @if($vendor->paypal_email)<div><dt class="text-[10px] font-bold uppercase tracking-[.12em] text-[#8a9892]">PayPal</dt><dd>{{ $vendor->paypal_email }}</dd></div>@endif
        </dl>
        <p class="mt-3 text-[11px] leading-5 text-[#6a6047]">Need a different method? Contact the store owner. Changes are made on the seller record so bank details are never stored in ShopKeeprr.</p>
    </div>
</div>

<h2 class="mt-8 font-display text-xl">Payout history</h2>
<div class="table-wrap mt-3">
    <table class="data-table">
        <thead><tr><th>Order</th><th>Date</th><th>Amount</th><th>Status</th><th>Reference</th></tr></thead>
        <tbody>
            @forelse($splits as $split)
                <tr>
                    <td><a href="{{ route('vendor.orders.show', $split) }}" class="font-semibold hover:text-[#0f766e]">{{ $split->order?->number ?? '#'.$split->order_id }}</a></td>
                    <td class="text-xs">{{ $split->created_at?->format('M j, Y') }}</td>
                    <td class="text-sm font-semibold">{{ nexus_money($split->netPayout()) }}</td>
                    <td><span class="badge {{ $split->status === 'paid' ? 'badge-emerald' : ($split->status === 'reversed' ? 'badge-rose' : 'badge-amber') }}">{{ ucfirst($split->status) }}</span></td>
                    <td class="max-w-[220px] truncate text-[11px] text-[#687771]">{{ $split->stripe_transfer_id ?: ($split->paypal_batch_id ?: '—') }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="py-10 text-center text-sm text-[#8a9892]">No payouts yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="mt-5">{{ $splits->links() }}</div>
@endsection
