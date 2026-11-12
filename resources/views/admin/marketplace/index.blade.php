@extends('admin.layouts.app')

@section('title', 'Marketplace')
@section('eyebrow', 'Storefront')
@section('heading', 'Plugin marketplace')

@section('content')
<div class="space-y-5">
    @if($error)
        <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm leading-6 text-rose-900">
            <div class="font-bold">The marketplace is unavailable</div>
            <p class="mt-1">{{ $error }}</p>
            <div class="mt-3 flex flex-wrap gap-2">
                <form method="post" action="{{ route('admin.marketplace.refresh') }}">@csrf<button class="btn-secondary !px-4 !py-2 text-xs">Try again</button></form>
                <a href="{{ route('admin.marketplace.index') }}" class="btn-secondary !px-4 !py-2 text-xs">Reload</a>
            </div>
        </div>
    @endif

    <div class="grid gap-5 xl:grid-cols-[1.35fr_.65fr]">
        <section class="card card-pad">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <div class="text-[10px] font-bold uppercase tracking-[.14em] text-[#0f766e]">ShopKeeprr Store</div>
                    <h2 class="mt-1 font-display text-3xl">{{ $connection?->account_name ?: 'Connect your Store account' }}</h2>
                    <p class="mt-2 max-w-2xl text-xs leading-5 text-[#687771]">
                        @if($connection)
                            Linked as <strong>{{ $connection->account_email }}</strong>. This store can install plugins you own and free plugins from the marketplace.
                        @else
                            Link your ShopKeeprr Store account to browse paid plugins and install them without uploading ZIP files. The Store issues a scoped token for this site; your password never leaves the Store.
                        @endif
                    </p>
                </div>
                <span class="badge {{ $connection ? 'badge-emerald' : 'badge-slate' }}">{{ $connection ? 'Connected' : 'Not connected' }}</span>
            </div>

            <div class="mt-5 flex flex-wrap items-center gap-2">
                @if($connection)
                    <a href="{{ route('admin.marketplace.index') }}" class="btn-secondary !px-4 !py-2 text-xs">Refresh catalog</a>
                    <a href="{{ route('admin.settings.index', ['section' => 'store']) }}" class="btn-secondary !px-4 !py-2 text-xs">Store settings</a>
                    <form method="post" action="{{ route('admin.marketplace.disconnect') }}" onsubmit="return confirm('Disconnect this store from the ShopKeeprr Store? Installed plugins keep working.')">
                        @csrf
                        <button class="text-xs font-bold text-rose-600">Disconnect</button>
                    </form>
                @else
                    <form method="post" action="{{ route('admin.marketplace.connect') }}">
                        @csrf
                        <button class="btn-primary !px-5 !py-2.5 text-xs">Connect Store account</button>
                    </form>
                @endif
            </div>
        </section>

        <aside class="card card-pad !bg-[#f7f4ec] text-xs leading-5 text-[#6a6047]">
            <div class="text-[10px] font-bold uppercase tracking-[.14em] text-[#8a9892]">Before you install</div>
            <p class="mt-2">Plugins are executable code. A plugin can read and change store data, run migrations and add routes. The marketplace review reduces risk, but it cannot sandbox a plugin.</p>
            <p class="mt-2">Installed plugins stay disabled until you enable them from <a href="{{ route('admin.plugins.index') }}" class="font-bold underline">Plugins</a>.</p>
        </aside>
    </div>

    <section class="card overflow-hidden">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-[#e3e9e5] px-5 py-4">
            <div>
                <div class="text-[10px] font-bold uppercase tracking-[.14em] text-[#8a9892]">Catalog</div>
                <h2 class="mt-1 font-display text-2xl">Extensions for your store</h2>
            </div>
            <div class="flex items-center gap-2">
                <span class="text-[11px] text-[#8a9892]">{{ count($catalog) }} listed</span>
                <a href="{{ route('admin.marketplace.index') }}" class="text-[11px] font-bold text-[#0f766e]">Refresh</a>
            </div>
        </div>

        <div class="grid gap-4 p-5 md:grid-cols-2 xl:grid-cols-3">
            @forelse($catalog as $product)
                @php
                    $slug = (string) ($product['slug'] ?? '');
                    $price = (int) ($product['price_cents'] ?? 0);
                    $owned = in_array($slug, $library, true);
                    $installedVersion = $installed[$slug] ?? null;
                    $release = $product['release'] ?? null;
                    $approved = is_array($release) && in_array(($release['scan_status'] ?? ''), ['clean', 'warning'], true);
                    $canInstall = $connection && $slug !== '' && $approved && ($price === 0 || $owned);
                    $isBlocked = is_array($release) && ($release['scan_status'] ?? '') === 'blocked';
                @endphp
                <article class="flex min-h-[15rem] flex-col rounded-2xl border border-[#e3e9e5] bg-white p-4">
                    <div class="flex items-start justify-between gap-2">
                        <div class="grid h-10 w-10 place-items-center rounded-xl bg-[#e7f5f0] text-[#0f766e]">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path d="M9 4h6v3a2 2 0 1 0 4 0h1v6h-3a2 2 0 1 0 0 4v3H9v-3a2 2 0 1 0-4 0H4v-6h3a2 2 0 1 0 0-4H4V4h5Z"/></svg>
                        </div>
                        <div class="flex flex-wrap justify-end gap-1">
                            @if($installedVersion)
                                <span class="badge badge-emerald">Installed</span>
                            @elseif($owned)
                                <span class="badge badge-emerald">Purchased</span>
                            @elseif($price === 0)
                                <span class="badge badge-slate">Free</span>
                            @endif
                            @if($isBlocked)
                                <span class="badge badge-rose">Review blocked</span>
                            @endif
                        </div>
                    </div>

                    <h3 class="mt-3 font-display text-xl leading-tight">{{ $product['name'] ?? 'Unnamed extension' }}</h3>
                    <p class="mt-1 line-clamp-3 text-xs leading-5 text-[#687771]">{{ $product['tagline'] ?: 'No description provided.' }}</p>

                    <div class="mt-3 flex flex-wrap items-center gap-x-3 gap-y-1 text-[10px] text-[#8a9892]">
                        <span>{{ $product['developer'] ?: 'ShopKeeprr' }}</span>
                        <span>v{{ $product['version'] ?: '—' }}</span>
                        <span>{{ $price === 0 ? 'Free' : '$'.number_format($price / 100, 2) }}</span>
                    </div>

                    <div class="mt-auto pt-4">
                        @if($installedVersion)
                            <div class="rounded-lg bg-[#f5f8f6] px-3 py-2 text-[11px] font-semibold text-[#3f5a50]">Installed v{{ $installedVersion }} · enable it in Plugins</div>
                        @elseif(! $connection)
                            <form method="post" action="{{ route('admin.marketplace.connect') }}">
                                @csrf
                                <button class="btn-secondary w-full !py-2 text-xs">Connect Store to install</button>
                            </form>
                        @elseif($canInstall)
                            <form method="post" action="{{ route('admin.marketplace.install') }}">
                                @csrf
                                <input type="hidden" name="product" value="{{ $slug }}">
                                <input type="hidden" name="version" value="{{ $product['version'] ?? '' }}">
                                <button class="btn-primary w-full !py-2 text-xs">Install with one click</button>
                            </form>
                        @elseif($price > 0 && ! $owned)
                            <a href="{{ $product['purchase_url'] ?? config('marketplace.store_url') }}" target="_blank" rel="noopener" class="btn-primary flex w-full items-center justify-center !py-2 text-xs">Buy in Store</a>
                        @else
                            <div class="rounded-lg bg-[#fff8ec] px-3 py-2 text-[11px] font-semibold text-[#7a5a12]">This release is not available for one-click installation.</div>
                        @endif
                    </div>
                </article>
            @empty
                <div class="col-span-full rounded-2xl border border-dashed border-[#c8d8d1] p-8 text-center">
                    <h3 class="font-display text-2xl">No extensions in the catalog yet.</h3>
                    <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-[#687771]">The Store catalog is reachable, but it returned no published plugins. Check back after the next marketplace release.</p>
                </div>
            @endforelse
        </div>
    </section>
</div>
@endsection
