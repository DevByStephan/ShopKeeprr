@extends('theme.layouts.app')

@section('title', 'Trade program — '.nexus_setting('store.name'))

@section('content')
<div class="bg-[var(--ink-strong)] text-white">
    <div class="container-wide grid gap-10 py-14 sm:py-20 lg:grid-cols-[1.1fr_.9fr]">
        <div>
            <div class="text-[10px] font-bold uppercase tracking-[.2em] text-white/40">ShopKeeprr trade</div>
            <h1 class="mt-4 font-display text-4xl leading-[1.08] tracking-tight sm:text-6xl">Wholesale pricing, without the friction.</h1>
            <p class="mt-5 max-w-lg text-sm leading-7 text-white/60">Tier-based pricing, minimum order quantities and net terms for studios, shops and agencies. Apply once, then order at your price whenever you need.</p>
            <div class="mt-8 grid gap-4 sm:grid-cols-3">
                @foreach([['Tiered pricing', 'Automatic price breaks by volume.'], ['MOQ controls', 'Order in the quantities that work.'], ['Priority support', 'A named contact for your account.']] as [$title, $copy])
                    <div class="rounded-2xl border border-white/10 bg-white/[.04] p-4">
                        <div class="text-sm font-semibold">{{ $title }}</div>
                        <div class="mt-1 text-xs leading-5 text-white/45">{{ $copy }}</div>
                    </div>
                @endforeach
            </div>
        </div>
        <div class="rounded-3xl border border-white/10 bg-white/[.06] p-6 backdrop-blur">
            @if($user->hasWholesaleAccess())
                <div class="text-[10px] font-bold uppercase tracking-[.18em] text-[#7fe3cd]">Approved account</div>
                <h2 class="mt-2 font-display text-3xl">You are all set.</h2>
                <p class="mt-2 text-sm leading-6 text-white/60">Your trade pricing is applied automatically at checkout{{ $user->tier ? ' on the '.$user->tier->name.' tier' : '' }}.</p>
                <a href="{{ route('storefront.shop') }}" class="btn-primary mt-6 !bg-white !text-[var(--ink-strong)]">Shop at trade prices</a>
            @elseif($user->wholesale_status?->value === 'pending')
                <div class="text-[10px] font-bold uppercase tracking-[.18em] text-[#f5c877]">Application received</div>
                <h2 class="mt-2 font-display text-3xl">We are reviewing it.</h2>
                <p class="mt-2 text-sm leading-6 text-white/60">Applications are usually reviewed within one business day. We will email {{ $user->email }} when a decision is made.</p>
            @else
                @if($user->wholesale_status?->value === 'rejected')
                    <div class="mb-4 rounded-xl border border-rose-300/30 bg-rose-400/10 px-3 py-2 text-xs text-rose-100">Your previous application was not approved. You may apply again with updated details.</div>
                @endif
                <div class="text-[10px] font-bold uppercase tracking-[.18em] text-white/40">Apply for a trade account</div>
                <h2 class="mt-2 font-display text-3xl">Tell us about your business.</h2>
                <form method="post" action="{{ route('storefront.trade.apply') }}" class="mt-5 space-y-3">
                    @csrf
                    <div>
                        <label class="label !text-white/50" for="company_name">Company name</label>
                        <input id="company_name" name="company_name" value="{{ old('company_name', $user->company_name) }}" class="input !border-white/15 !bg-white/10 !text-white placeholder:!text-white/30" required>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label class="label !text-white/50" for="tax_id">Tax / VAT ID</label>
                            <input id="tax_id" name="tax_id" value="{{ old('tax_id', $user->tax_id) }}" class="input !border-white/15 !bg-white/10 !text-white">
                        </div>
                        <div>
                            <label class="label !text-white/50" for="phone">Phone</label>
                            <input id="phone" name="phone" value="{{ old('phone', $user->phone) }}" class="input !border-white/15 !bg-white/10 !text-white" required>
                        </div>
                    </div>
                    <div>
                        <label class="label !text-white/50" for="website">Website</label>
                        <input id="website" type="url" name="website" value="{{ old('website') }}" class="input !border-white/15 !bg-white/10 !text-white" placeholder="https://">
                    </div>
                    <div>
                        <label class="label !text-white/50" for="note">What do you sell?</label>
                        <textarea id="note" name="note" rows="3" class="textarea !border-white/15 !bg-white/10 !text-white">{{ old('note', $user->admin_notes) }}</textarea>
                    </div>
                    <button class="btn-primary w-full !bg-white !text-[var(--ink-strong)]">Submit application</button>
                </form>
            @endif
        </div>
    </div>
</div>

<div class="container-wide py-12">
    <div class="grid gap-8 lg:grid-cols-3">
        @foreach([
            ['01', 'Apply', 'Send us your business details. If you already have an account, the form is prefilled.'],
            ['02', 'Get approved', 'We verify your details and assign a pricing tier. Most applications are decided within a day.'],
            ['03', 'Order at your price', 'Trade prices, MOQs and tier breaks are applied automatically everywhere on the store.'],
        ] as [$step, $title, $copy])
            <div class="rounded-2xl border border-[var(--line)] bg-white p-6">
                <div class="font-display text-2xl text-[var(--link)]">{{ $step }}</div>
                <h3 class="mt-3 text-sm font-bold">{{ $title }}</h3>
                <p class="mt-2 text-sm leading-6 text-[var(--muted)]">{{ $copy }}</p>
            </div>
        @endforeach
    </div>
</div>
@endsection
