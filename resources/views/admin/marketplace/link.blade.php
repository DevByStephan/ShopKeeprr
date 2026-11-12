@extends('admin.layouts.app')

@section('title', 'Connect ShopKeeprr Store')
@section('eyebrow', 'Storefront')
@section('heading', 'Connect your Store account')

@section('content')
<div class="mx-auto max-w-2xl space-y-5">
    <section class="card card-pad text-center">
        <div class="mx-auto grid h-12 w-12 place-items-center rounded-2xl bg-[#e7f5f0] text-[#0f766e]">
            <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M8 11a4 4 0 1 1 8 0c0 2-1 3-2 4s-1 2-1 3M12 21h.01"/><path d="M4 7V5h5M20 7V5h-5M4 17v2h5M20 17v2h-5"/></svg>
        </div>
        <h2 class="mt-4 font-display text-3xl">Approve this store</h2>
        <p class="mx-auto mt-2 max-w-lg text-sm leading-6 text-[#687771]">Open the ShopKeeprr Store, sign in, and approve the connection. Then return here; this page will finish automatically.</p>

        <div class="mt-6 rounded-2xl border border-[#dce5e0] bg-[#fbfdfc] p-5">
            <div class="text-[10px] font-bold uppercase tracking-[.14em] text-[#8a9892]">Connection code</div>
            <div class="mt-2 font-mono text-4xl font-black tracking-[.25em] text-[#102522]">{{ $pending['user_code'] ?? '--------' }}</div>
            <div class="mt-2 text-[11px] text-[#8a9892]">This code expires {{ isset($pending['expires_at']) ? \Illuminate\Support\Carbon::parse($pending['expires_at'])->diffForHumans() : 'soon' }}.</div>
        </div>

        <div class="mt-5 flex flex-col justify-center gap-2 sm:flex-row">
            @if(! empty($pending['verification_url']))
                <a href="{{ $pending['verification_url'] }}" target="_blank" rel="noopener" class="btn-primary">Open ShopKeeprr Store</a>
            @endif
            <a href="{{ route('admin.marketplace.index') }}" class="btn-secondary">Cancel</a>
        </div>

        <div id="marketplace-link-status" class="mt-5 text-xs text-[#687771]" aria-live="polite">Waiting for approval…</div>
    </section>

    <div class="card card-pad text-xs leading-5 text-[#687771]">
        <strong class="text-[#17231f]">What access is granted?</strong>
        The Store gives this installation a scoped site token. It can read the catalog, check which products this account owns, and download the releases you are entitled to. It cannot change your Store password, payment methods or other account settings.
    </div>
</div>
@endsection

@push('scripts')
<script>
(() => {
    const status = document.querySelector('#marketplace-link-status');
    if (!status) return;

    let stopped = false;
    let timer = null;

    const poll = async () => {
        if (stopped) return;

        try {
            const response = await fetch(@json(route('admin.marketplace.status')), {
                headers: { 'Accept': 'application/json' },
                credentials: 'same-origin',
            });
            const data = await response.json();

            if (data.status === 'connected') {
                stopped = true;
                status.textContent = 'Store connected. Loading the marketplace…';
                window.location.href = data.redirect || @json(route('admin.marketplace.index'));
                return;
            }

            if (data.status === 'error') {
                stopped = true;
                status.textContent = data.message || 'The connection could not be completed. Start again.';
                status.className = 'mt-5 text-xs font-semibold text-rose-600';
                return;
            }

            status.textContent = 'Waiting for approval…';
        } catch (error) {
            status.textContent = 'Waiting for the Store…';
        }

        if (!stopped) timer = setTimeout(poll, 3000);
    };

    timer = setTimeout(poll, 2000);

    window.addEventListener('beforeunload', () => {
        stopped = true;
        if (timer) clearTimeout(timer);
    });
})();
</script>
@endpush
