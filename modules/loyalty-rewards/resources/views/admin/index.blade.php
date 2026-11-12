@extends('admin.layouts.app')

@section('title', 'Loyalty & rewards')
@section('eyebrow', 'Plugins')
@section('heading', 'Loyalty & rewards')

@section('content')
<div class="grid gap-5 xl:grid-cols-[1fr_1.4fr]">
    <div class="space-y-5">
        <div class="card card-pad">
            <h2 class="font-display text-xl">Earning & redemption</h2>
            <form method="post" action="{{ route('admin.loyalty.settings') }}" class="mt-4 space-y-3">
                @csrf
                @method('PATCH')
                <div>
                    <label class="label" for="earn_points_per_dollar">Points per {{ nexus_money(1) }}</label>
                    <input id="earn_points_per_dollar" type="number" step="0.01" min="0" name="earn_points_per_dollar" value="{{ $earnRate }}" class="input" required>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="label" for="redeem_points">Points to redeem</label>
                        <input id="redeem_points" type="number" min="1" name="redeem_points" value="{{ $redemptionPoints }}" class="input" required>
                    </div>
                    <div>
                        <label class="label" for="redeem_value">Coupon value</label>
                        <input id="redeem_value" type="number" step="0.01" min="0" name="redeem_value" value="{{ $redemptionValue }}" class="input" required>
                    </div>
                </div>
                <button class="btn-primary w-full text-xs">Save settings</button>
            </form>
        </div>

        <div class="card card-pad">
            <h2 class="font-display text-xl">Manual adjustment</h2>
            <p class="mt-1 text-xs text-[#8a9892]">Use a negative number to remove points after a return or correction.</p>
            <form method="post" action="{{ route('admin.loyalty.adjust') }}" class="mt-4 space-y-3">
                @csrf
                <div>
                    <label class="label" for="email">Customer email</label>
                    <input id="email" type="email" name="email" class="input" required>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="label" for="points">Points</label>
                        <input id="points" type="number" name="points" class="input" required placeholder="+100">
                    </div>
                    <div>
                        <label class="label" for="description">Reason</label>
                        <input id="description" name="description" class="input" required placeholder="Goodwill">
                    </div>
                </div>
                <button class="btn-secondary w-full text-xs">Apply adjustment</button>
            </form>
        </div>
    </div>

    <div class="space-y-5">
        <div class="card card-pad">
            <div class="flex items-center justify-between">
                <h2 class="font-display text-xl">Top balances</h2>
                <span class="text-[11px] text-[#8a9892]">Points are awarded after payment</span>
            </div>
            <div class="mt-3 overflow-x-auto">
                <table class="data-table">
                    <thead><tr><th>Customer</th><th>Balance</th><th>Lifetime</th></tr></thead>
                    <tbody>
                        @forelse($accounts as $account)
                            <tr>
                                <td><div class="text-sm font-semibold">{{ $account->user?->name ?? 'Deleted account' }}</div><div class="text-[11px] text-[#8a9892]">{{ $account->user?->email }}</div></td>
                                <td class="text-sm font-bold">{{ number_format($account->points_balance) }}</td>
                                <td class="text-xs">{{ number_format($account->lifetime_points) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="py-8 text-center text-sm text-[#8a9892]">No loyalty accounts yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $accounts->links() }}</div>
        </div>

        <div class="card card-pad">
            <h2 class="font-display text-xl">Recent activity</h2>
            <div class="mt-3 space-y-2">
                @forelse($transactions as $transaction)
                    <div class="flex items-center justify-between gap-3 rounded-lg border border-[#e3e9e5] px-3 py-2">
                        <div class="min-w-0">
                            <div class="truncate text-xs font-semibold">{{ $transaction->user?->email ?? 'Deleted account' }}</div>
                            <div class="truncate text-[10px] text-[#8a9892]">{{ $transaction->description }} · {{ $transaction->created_at?->format('M j, g:ia') }}</div>
                        </div>
                        <span class="shrink-0 text-xs font-bold {{ $transaction->points >= 0 ? 'text-emerald-700' : 'text-rose-600' }}">{{ $transaction->points > 0 ? '+' : '' }}{{ number_format($transaction->points) }}</span>
                    </div>
                @empty
                    <p class="py-6 text-center text-sm text-[#8a9892]">No points activity yet.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
