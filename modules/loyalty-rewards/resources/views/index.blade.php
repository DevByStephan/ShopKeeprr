@extends('theme.layouts.app')

@section('title', 'Rewards — '.nexus_setting('store.name'))

@section('content')
<div class="container-wide py-10 sm:py-14">
    @if(session('status'))
        <div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">{{ session('status') }}</div>
    @endif
    @if($errors->any())
        <div class="mb-5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900">{{ $errors->first() }}</div>
    @endif

    <div class="grid gap-8 lg:grid-cols-[1.4fr_.6fr]">
        <div>
            <div class="text-[10px] font-bold uppercase tracking-[.18em] text-[var(--link)]">My account</div>
            <h1 class="mt-2 font-display text-4xl tracking-tight sm:text-5xl">Rewards that add up.</h1>
            <p class="mt-2 max-w-xl text-sm leading-6 text-[var(--muted)]">Earn {{ rtrim(rtrim(number_format($earnRate, 2), '0'), '.') }} points for every {{ nexus_money(1) }} paid on an order. Points appear after payment is confirmed, never on an abandoned checkout.</p>

            <div class="mt-7 grid gap-4 sm:grid-cols-2">
                <div class="rounded-2xl bg-[var(--brand)] p-6 text-[var(--button-ink)]">
                    <div class="text-[10px] font-bold uppercase tracking-[.16em] opacity-60">Available points</div>
                    <div class="mt-2 font-display text-5xl">{{ number_format($account->points_balance) }}</div>
                    <div class="mt-1 text-xs opacity-65">{{ number_format($account->lifetime_points) }} earned all time</div>
                </div>
                <div class="rounded-2xl border border-[var(--line)] bg-white p-6">
                    <div class="text-[10px] font-bold uppercase tracking-[.16em] text-[var(--muted)]">Next reward</div>
                    <div class="mt-2 font-display text-3xl">{{ number_format($redemptionPoints) }} points</div>
                    <div class="mt-1 text-xs text-[var(--muted)]">Unlocks a {{ nexus_money($redemptionValue) }} coupon.</div>
                    <form method="post" action="{{ route('loyalty.redeem') }}" class="mt-4">
                        @csrf
                        <button class="btn-primary w-full text-xs" @disabled($account->points_balance < $redemptionPoints)>
                            {{ $account->points_balance >= $redemptionPoints ? 'Redeem '.nexus_money($redemptionValue).' reward' : 'Keep earning' }}
                        </button>
                    </form>
                </div>
            </div>

            <div class="mt-8">
                <h2 class="font-display text-2xl">Points ledger</h2>
                <div class="mt-3 overflow-hidden rounded-2xl border border-[var(--line)] bg-white">
                    <table class="w-full text-sm">
                        <thead class="bg-[var(--surface-2)] text-left text-[9px] font-bold uppercase tracking-[.14em] text-[var(--muted)]">
                            <tr><th class="px-4 py-3">Activity</th><th class="px-4 py-3">Date</th><th class="px-4 py-3 text-right">Points</th></tr>
                        </thead>
                        <tbody class="divide-y divide-[var(--line)]">
                            @forelse($transactions as $transaction)
                                <tr>
                                    <td class="px-4 py-3">
                                        <div class="font-semibold">{{ $transaction->description ?: ucfirst($transaction->type) }}</div>
                                        <div class="text-[10px] uppercase tracking-[.1em] text-[var(--muted)]">{{ $transaction->type }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-xs text-[var(--muted)]">{{ $transaction->created_at?->format('M j, Y') }}</td>
                                    <td class="px-4 py-3 text-right font-bold {{ $transaction->points >= 0 ? 'text-emerald-700' : 'text-rose-600' }}">{{ $transaction->points > 0 ? '+' : '' }}{{ number_format($transaction->points) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="px-4 py-10 text-center text-sm text-[var(--muted)]">Your first paid order will start the ledger.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-4">{{ $transactions->links() }}</div>
            </div>
        </div>

        <aside class="space-y-4">
            <div class="rounded-2xl border border-[var(--line)] bg-white p-5">
                <h2 class="text-sm font-bold">How it works</h2>
                <ol class="mt-3 space-y-3 text-xs leading-5 text-[var(--muted)]">
                    <li><span class="font-bold text-[var(--ink)]">1.</span> Place an order and pay as normal.</li>
                    <li><span class="font-bold text-[var(--ink)]">2.</span> Points land in your balance when payment clears.</li>
                    <li><span class="font-bold text-[var(--ink)]">3.</span> Redeem for a single-use coupon, then apply it in the cart.</li>
                </ol>
            </div>
            <div class="rounded-2xl border border-[var(--line)] bg-[var(--surface-2)] p-5 text-[11px] leading-5 text-[var(--muted)]">
                Coupons expire after 90 days and can be used once. Points have no cash value and may be adjusted by the store for returns or corrections.
            </div>
            <a href="{{ route('storefront.account.index') }}" class="btn-secondary w-full text-xs">Back to account</a>
        </aside>
    </div>
</div>
@endsection
