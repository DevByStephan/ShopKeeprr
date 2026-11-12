@extends('admin.layouts.app')

@section('title', $vendor->name)
@section('eyebrow', 'Marketplace')
@section('heading', $vendor->name)

@section('content')
<div class="mb-5 flex flex-wrap items-center justify-between gap-3">
    <div class="flex flex-wrap items-center gap-2">
        <span class="badge {{ $vendor->status === 'approved' ? 'badge-emerald' : ($vendor->status === 'suspended' ? 'badge-rose' : 'badge-amber') }}">{{ ucfirst($vendor->status) }}</span>
        <span class="badge badge-slate">{{ $vendor->payoutMethodLabel() }}</span>
        @if($vendor->user)
            <span class="text-xs text-[#687771]">Portal: {{ $vendor->user->email }}</span>
        @endif
    </div>
    <div class="flex flex-wrap items-center gap-2">
        @if($vendor->status !== 'approved')
            <form method="post" action="{{ route('admin.vendors.status', $vendor) }}">
                @csrf
                <input type="hidden" name="status" value="approved">
                <button class="btn-primary !px-4 !py-2 text-xs">Approve seller</button>
            </form>
        @else
            <form method="post" action="{{ route('admin.vendors.status', $vendor) }}">
                @csrf
                <input type="hidden" name="status" value="suspended">
                <button class="btn-secondary !px-4 !py-2 text-xs" onclick="return confirm('Suspend this seller? Their products will stop appearing in the marketplace.')">Suspend</button>
            </form>
        @endif
        <a href="{{ route('admin.vendors.edit', $vendor) }}" class="btn-secondary !px-4 !py-2 text-xs">Edit settings</a>
    </div>
</div>

@if(session('status'))
    <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">{{ session('status') }}</div>
@endif
@if($errors->any())
    <div class="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900">
        @foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach
    </div>
@endif

<div class="mb-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
    <div class="card card-pad"><div class="text-[10px] font-bold uppercase tracking-[.14em] text-[#8a9892]">Lifetime payouts</div><div class="mt-2 font-display text-3xl">{{ nexus_money($totals['lifetime']) }}</div></div>
    <div class="card card-pad"><div class="text-[10px] font-bold uppercase tracking-[.14em] text-[#8a9892]">Current balance</div><div class="mt-2 font-display text-3xl">{{ nexus_money($totals['balance']) }}</div></div>
    <div class="card card-pad"><div class="text-[10px] font-bold uppercase tracking-[.14em] text-[#8a9892]">Refunded to seller</div><div class="mt-2 font-display text-3xl">{{ nexus_money($totals['refunded']) }}</div></div>
    <div class="card card-pad"><div class="text-[10px] font-bold uppercase tracking-[.14em] text-[#8a9892]">Orders</div><div class="mt-2 font-display text-3xl">{{ $totals['orders'] }}</div></div>
</div>

<div class="grid gap-5 xl:grid-cols-[1.4fr_.8fr]">
    <div class="space-y-5">
        <div class="card card-pad">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="font-display text-xl">Payout account</h2>
                    <p class="mt-1 text-xs text-[#687771]">Stripe handles identity checks and bank details. ShopKeeprr only stores the connected account reference.</p>
                </div>
                @if($vendor->stripe_account_id)
                    <span class="badge {{ $vendor->stripe_payouts_enabled ? 'badge-emerald' : 'badge-amber' }}">{{ $vendor->stripe_payouts_enabled ? 'Stripe ready' : 'Onboarding incomplete' }}</span>
                @else
                    <span class="badge badge-slate">Not connected</span>
                @endif
            </div>

            @if($vendor->stripe_account_id)
                <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                    <div><dt class="text-[10px] font-bold uppercase tracking-[.12em] text-[#8a9892]">Account</dt><dd class="mt-1 font-mono text-xs">{{ $vendor->stripe_account_id }}</dd></div>
                    <div><dt class="text-[10px] font-bold uppercase tracking-[.12em] text-[#8a9892]">Charges enabled</dt><dd class="mt-1 text-xs">{{ $vendor->stripe_charges_enabled ? 'Yes' : 'No' }}</dd></div>
                    <div><dt class="text-[10px] font-bold uppercase tracking-[.12em] text-[#8a9892]">Payouts enabled</dt><dd class="mt-1 text-xs">{{ $vendor->stripe_payouts_enabled ? 'Yes' : 'No' }}</dd></div>
                    <div><dt class="text-[10px] font-bold uppercase tracking-[.12em] text-[#8a9892]">Details submitted</dt><dd class="mt-1 text-xs">{{ $vendor->stripe_details_submitted ? 'Yes' : 'No' }}</dd></div>
                </dl>
            @endif

            <div class="mt-4 flex flex-wrap gap-2">
                <form method="post" action="{{ route('admin.vendors.stripe', $vendor) }}">
                    @csrf
                    <button class="btn-primary !px-4 !py-2 text-xs">{{ $vendor->stripe_account_id ? 'Continue Stripe onboarding' : 'Connect Stripe account' }}</button>
                </form>
                @if($vendor->stripe_account_id)
                    <form method="post" action="{{ route('admin.vendors.stripe.refresh', $vendor) }}">
                        @csrf
                        <button class="btn-secondary !px-4 !py-2 text-xs">Refresh status</button>
                    </form>
                @endif
            </div>
        </div>

        <div class="card card-pad">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="font-display text-xl">Payout queue</h2>
                <form method="post" action="{{ route('admin.vendors.payout', $vendor) }}">
                    @csrf
                    @if($vendor->status === 'suspended')
                        <input type="hidden" name="force" value="1">
                    @endif
                    <button class="btn-primary !px-4 !py-2 text-xs">Pay released balance</button>
                </form>
            </div>
            <p class="mt-1 text-xs leading-5 text-[#687771]">Only splits whose hold period has ended are paid. Stripe transfers are attempted automatically when a payment lands, if auto-payout is enabled.</p>
        </div>

        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Order</th><th>Status</th><th>Seller earned</th><th>Refunded</th><th>Release</th><th>Reference</th></tr></thead>
                <tbody>
                    @forelse($splits as $split)
                        <tr>
                            <td>
                                <a href="{{ route('admin.orders.show', $split->order_id) }}" class="font-semibold hover:text-[#0f766e]">{{ $split->order?->number ?? '#'.$split->order_id }}</a>
                                <div class="text-[11px] text-[#8a9892]">{{ $split->created_at?->format('M j, Y') }}</div>
                            </td>
                            <td><span class="badge {{ $split->status === 'paid' ? 'badge-emerald' : ($split->status === 'reversed' ? 'badge-rose' : 'badge-amber') }}">{{ ucfirst($split->status) }}</span></td>
                            <td class="text-sm font-semibold">{{ nexus_money($split->payout_total) }}</td>
                            <td class="text-xs">{{ $split->refunded_total > 0 ? nexus_money($split->refunded_total) : '—' }}</td>
                            <td class="text-xs">{{ $split->available_at?->format('M j, Y') ?? 'On payment' }}</td>
                            <td class="max-w-[180px] truncate text-[11px] text-[#687771]">{{ $split->stripe_transfer_id ?: ($split->paypal_batch_id ?: ($split->failure_reason ?: '—')) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-10 text-center text-sm text-[#8a9892]">No seller orders yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div>{{ $splits->links() }}</div>
    </div>

    <div class="space-y-5">
        <div class="card card-pad">
            <h2 class="font-display text-lg">Seller profile</h2>
            <dl class="mt-3 space-y-3 text-sm">
                <div><dt class="text-[10px] font-bold uppercase tracking-[.12em] text-[#8a9892]">Email</dt><dd>{{ $vendor->email }}</dd></div>
                @if($vendor->phone)<div><dt class="text-[10px] font-bold uppercase tracking-[.12em] text-[#8a9892]">Phone</dt><dd>{{ $vendor->phone }}</dd></div>@endif
                @if($vendor->description)<div><dt class="text-[10px] font-bold uppercase tracking-[.12em] text-[#8a9892]">Description</dt><dd class="text-xs leading-5 text-[#687771]">{{ $vendor->description }}</dd></div>@endif
                <div><dt class="text-[10px] font-bold uppercase tracking-[.12em] text-[#8a9892]">Commission</dt><dd class="text-xs">{{ ucfirst($vendor->commissionRule()['type']) }} · {{ rtrim(rtrim(number_format($vendor->commissionRule()['percent'], 2), '0'), '.') }}% · {{ nexus_money($vendor->commissionRule()['fixed']) }} fixed</dd></div>
                <div><dt class="text-[10px] font-bold uppercase tracking-[.12em] text-[#8a9892]">Hold period</dt><dd class="text-xs">{{ $vendor->payoutHoldDays() }} day(s)</dd></div>
            </dl>
            @if($vendor->admin_notes)
                <div class="mt-4 rounded-lg bg-[#f5f8f6] p-3 text-xs leading-5 text-[#687771]">{{ $vendor->admin_notes }}</div>
            @endif
        </div>

        <div class="card card-pad">
            <div class="flex items-center justify-between">
                <h2 class="font-display text-lg">Products</h2>
                <a href="{{ route('admin.products.index') }}" class="text-[11px] font-bold text-[#0f766e]">All products</a>
            </div>
            <div class="mt-3 space-y-2">
                @forelse($vendor->products as $product)
                    <a href="{{ route('admin.products.edit', $product) }}" class="flex items-center gap-3 rounded-lg border border-[#eef2ef] p-2 hover:border-[#cfe0d8]">
                        <div class="h-9 w-9 overflow-hidden rounded-lg bg-[#eef2ef]">
                            @if($product->imageUrl())<img src="{{ $product->imageUrl() }}" alt="" class="h-full w-full object-cover">@endif
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="truncate text-xs font-semibold">{{ $product->name }}</div>
                            <div class="text-[10px] text-[#8a9892]">{{ nexus_money($product->price) }} · {{ $product->is_active ? 'Active' : 'Draft' }}</div>
                        </div>
                    </a>
                @empty
                    <p class="text-xs text-[#8a9892]">No products assigned yet. Assign a vendor while creating or editing a product.</p>
                @endforelse
            </div>
        </div>

        <div class="card card-pad !bg-[#f7f4ec]">
            <h2 class="font-display text-lg">Compliance note</h2>
            <p class="mt-2 text-xs leading-5 text-[#6a6047]">Each seller is responsible for their own tax registration, product compliance and customer service. Stripe Connect accounts are created through Stripe's hosted onboarding so the platform never stores identity documents or bank numbers.</p>
        </div>
    </div>
</div>
@endsection
