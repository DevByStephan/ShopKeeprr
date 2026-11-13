@extends('admin.layouts.app')

@section('title', 'Marketplace')
@section('eyebrow', 'Extensions')
@section('heading', 'Add plugins')

@section('content')
@php
    $allCount = count($catalog) + count($ideas);
    $pluginCount = collect($catalog)->filter(fn ($item) => in_array(($item['type'] ?? 'plugin'), ['plugin', 'business_model'], true))->count();
    $themeCount = collect($catalog)->where('type', 'theme')->count();
    $freeCount = collect($catalog)->filter(fn ($item) => (int) ($item['price_cents'] ?? 0) === 0)->count();
    $installedCount = collect($catalog)->filter(fn ($item) => isset($installed[(string) ($item['slug'] ?? '')]))->count();
    $ideaCount = count($ideas);
    $iconPalettes = [
        ['#0f766e', '#082b28'],
        ['#b45309', '#7c2d12'],
        ['#4338ca', '#1e1b4b'],
        ['#a21caf', '#4a044e'],
        ['#0369a1', '#082f49'],
        ['#4d7c0f', '#1a2e05'],
    ];
@endphp

<style>
    .marketplace-head { display: flex; flex-wrap: wrap; align-items: flex-end; justify-content: space-between; gap: 24px; padding: 24px; border: 1px solid #dfe8e2; border-radius: 18px; background: linear-gradient(135deg, #fffdf8 0%, #f2f8f4 100%); }
    .marketplace-kicker { color: #0f766e; font-size: 10px; font-weight: 800; letter-spacing: .14em; text-transform: uppercase; }
    .marketplace-title { margin-top: 5px; color: #17231f; font-family: "Fraunces", Georgia, serif; font-size: 34px; font-weight: 500; letter-spacing: -.04em; line-height: 1; }
    .marketplace-lede { max-width: 660px; margin-top: 10px; color: #687771; font-size: 12px; line-height: 1.7; }
    .marketplace-account { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; margin-top: 14px; color: #687771; font-size: 11px; }
    .marketplace-account-dot { width: 7px; height: 7px; border-radius: 50%; background: #a8b8b0; }
    .marketplace-account-dot.is-connected { background: #0f766e; box-shadow: 0 0 0 3px #dff2e8; }
    .marketplace-account strong { color: #17231f; }
    .marketplace-head-actions { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }
    .marketplace-note { display: flex; flex-wrap: wrap; align-items: flex-start; gap: 9px; padding: 12px 15px; border: 1px solid #e8d5ae; border-radius: 12px; background: #fdf6e8; color: #6d5329; font-size: 11px; line-height: 1.6; }
    .marketplace-note svg { flex: 0 0 auto; margin-top: 1px; }
    .marketplace-panel { overflow: hidden; border: 1px solid #dfe8e2; border-radius: 18px; background: #fff; }
    .marketplace-toolbar { padding: 16px; border-bottom: 1px solid #e7eee9; }
    .marketplace-tools { display: flex; flex-direction: column; gap: 12px; }
    .marketplace-search-wrap { position: relative; width: 100%; max-width: 480px; }
    .marketplace-search-wrap svg { position: absolute; top: 50%; left: 13px; width: 16px; height: 16px; color: #8a9892; transform: translateY(-50%); pointer-events: none; }
    .marketplace-search { width: 100%; height: 40px; padding: 0 38px 0 38px; border: 1px solid #cfddd5; border-radius: 10px; outline: 0; background: #fbfdfc; color: #17231f; font: inherit; font-size: 12px; transition: border-color .15s ease, box-shadow .15s ease; }
    .marketplace-search:focus { border-color: #0f766e; box-shadow: 0 0 0 3px #e7f5f0; }
    .marketplace-search::placeholder { color: #97a69f; }
    .marketplace-clear { position: absolute; top: 50%; right: 8px; display: grid; width: 24px; height: 24px; place-items: center; border: 0; border-radius: 50%; background: #edf3ef; color: #5b6b64; font-size: 16px; line-height: 1; cursor: pointer; transform: translateY(-50%); }
    .marketplace-clear:hover { background: #dfece6; color: #17231f; }
    .marketplace-clear[hidden] { display: none; }
    .marketplace-filters { display: flex; flex-wrap: wrap; align-items: center; gap: 4px; }
    .marketplace-filter { display: inline-flex; align-items: center; gap: 5px; min-height: 30px; padding: 5px 9px; border: 1px solid transparent; border-radius: 7px; background: transparent; color: #687771; font: inherit; font-size: 11px; font-weight: 600; cursor: pointer; }
    .marketplace-filter span { color: #9aa8a1; font-size: 10px; font-weight: 500; }
    .marketplace-filter:hover { background: #f3f7f4; color: #17231f; }
    .marketplace-filter.is-active { border-color: #b9d8cb; background: #eaf6f0; color: #0f604f; }
    .marketplace-filter.is-active span { color: #0f766e; }
    .marketplace-results { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 10px; padding: 13px 18px; border-bottom: 1px solid #eef3f0; color: #8a9892; font-size: 11px; }
    .marketplace-results strong { color: #17231f; }
    .marketplace-results a { color: #0f766e; font-weight: 700; text-decoration: none; }
    .marketplace-results a:hover { text-decoration: underline; }
    .marketplace-grid { display: grid; gap: 14px; padding: 18px; }
    .marketplace-card { display: flex; min-width: 0; flex-direction: column; padding: 17px; border: 1px solid #dfe8e2; border-radius: 14px; background: #fff; box-shadow: 0 1px 2px rgba(23, 35, 31, .03); transition: border-color .15s ease, box-shadow .15s ease, transform .15s ease; }
    .marketplace-card:hover { border-color: #b9d8cb; box-shadow: 0 9px 24px rgba(23, 35, 31, .07); transform: translateY(-1px); }
    .marketplace-card-head { display: flex; align-items: flex-start; gap: 12px; }
    .marketplace-icon { display: grid; overflow: hidden; width: 54px; height: 54px; flex: 0 0 54px; place-items: center; border-radius: 15px; color: #fff; font-size: 16px; font-weight: 800; letter-spacing: -.04em; box-shadow: inset 0 1px 0 rgba(255,255,255,.18); }
    .marketplace-icon img { width: 100%; height: 100%; object-fit: cover; object-position: center; }
    .marketplace-card-title { min-width: 0; flex: 1 1 auto; }
    .marketplace-card-meta { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; margin-bottom: 4px; }
    .marketplace-type { color: #0f766e; font-size: 9px; font-weight: 800; letter-spacing: .09em; text-transform: uppercase; }
    .marketplace-version { color: #9aa8a1; font-size: 9px; }
    .marketplace-card-name { margin: 0; color: #17231f; font-family: "Fraunces", Georgia, serif; font-size: 21px; font-weight: 500; letter-spacing: -.025em; line-height: 1.1; }
    .marketplace-card-author { margin-top: 4px; color: #8a9892; font-size: 10px; line-height: 1.4; }
    .marketplace-card-author strong { color: #5d6e66; font-weight: 600; }
    .marketplace-price { flex: 0 0 auto; padding: 4px 7px; border-radius: 6px; background: #f3f7f4; color: #4f6259; font-size: 10px; font-weight: 800; white-space: nowrap; }
    .marketplace-price.is-free { background: #e8f5ed; color: #0f766e; }
    .marketplace-card-copy { display: -webkit-box; overflow: hidden; margin: 13px 0 0; color: #687771; font-size: 11px; line-height: 1.65; -webkit-box-orient: vertical; -webkit-line-clamp: 3; }
    .marketplace-card-footer { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 9px; margin-top: auto; padding-top: 15px; }
    .marketplace-badges, .marketplace-actions { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; }
    .marketplace-pill { display: inline-flex; align-items: center; min-height: 22px; padding: 3px 7px; border-radius: 999px; font-size: 9px; font-weight: 800; letter-spacing: .04em; text-transform: uppercase; }
    .marketplace-pill.is-installed { background: #e8f5ed; color: #0f604f; }
    .marketplace-pill.is-owned { background: #eef3ff; color: #3f51a5; }
    .marketplace-pill.is-free { background: #eef3ff; color: #3f51a5; }
    .marketplace-pill.is-blocked { background: #fef0f0; color: #a13a3a; }
    .marketplace-pill.is-concept { background: #f4edff; color: #6b3fa0; }
    .marketplace-button { display: inline-flex; min-height: 30px; align-items: center; justify-content: center; padding: 6px 10px; border: 1px solid #cfddd5; border-radius: 7px; background: #fff; color: #4d6258; font: inherit; font-size: 10px; font-weight: 700; line-height: 1.15; text-decoration: none; cursor: pointer; transition: background .15s ease, border-color .15s ease, color .15s ease; }
    .marketplace-button:hover { border-color: #0f766e; background: #f0faf5; color: #0f604f; }
    .marketplace-button--primary { border-color: #0f766e; background: #0f766e; color: #fff; }
    .marketplace-button--primary:hover { border-color: #0b5d56; background: #0b5d56; color: #fff; }
    .marketplace-button--secondary { border-color: #b9d8cb; background: #f4fbf7; color: #0f604f; }
    .marketplace-button--quiet { border-color: transparent; background: transparent; color: #0f766e; }
    .marketplace-more { padding: 0; border: 0; background: transparent; color: #7a8b82; font-family: inherit; font-size: 10px; font-weight: 600; text-decoration: none; cursor: pointer; }
    .marketplace-more:hover { color: #0f766e; text-decoration: underline; }
    .marketplace-managed { color: #5d7167; font-size: 10px; line-height: 1.4; }
    .marketplace-section-head { display: flex; flex-wrap: wrap; align-items: flex-end; justify-content: space-between; gap: 14px; padding: 21px 20px 15px; border-bottom: 1px solid #eef3f0; }
    .marketplace-section-head h2 { margin: 4px 0 0; color: #17231f; font-family: "Fraunces", Georgia, serif; font-size: 27px; font-weight: 500; letter-spacing: -.035em; line-height: 1.05; }
    .marketplace-section-head p:not(.marketplace-kicker) { max-width: 620px; margin-top: 7px; color: #687771; font-size: 11px; line-height: 1.6; }
    .marketplace-concept-count { color: #8a9892; font-size: 10px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; }
    .marketplace-card--idea { border-style: dashed; background: #fdfcf8; }
    .marketplace-card--idea:hover { border-style: solid; }
    .marketplace-card--idea .marketplace-card-copy { -webkit-line-clamp: 3; }
    .marketplace-idea-purpose { margin-top: 7px; color: #7b8b83; font-size: 10px; line-height: 1.55; }
    .marketplace-empty { padding: 34px 20px; text-align: center; }
    .marketplace-empty h3 { color: #17231f; font-family: "Fraunces", Georgia, serif; font-size: 24px; font-weight: 500; }
    .marketplace-empty p { max-width: 470px; margin: 8px auto 0; color: #687771; font-size: 12px; line-height: 1.65; }
    .marketplace-hidden { display: none !important; }
    .marketplace-dialog { width: min(760px, calc(100vw - 28px)); max-height: min(88vh, 800px); margin: auto; padding: 0; border: 0; border-radius: 22px; background: #fff; color: #17231f; box-shadow: 0 30px 90px rgba(8, 28, 23, .28); }
    .marketplace-dialog:not([open]) { display: none; }
    .marketplace-dialog::backdrop { background: rgba(12, 28, 23, .58); backdrop-filter: blur(3px); }
    .marketplace-dialog-card { display: flex; max-height: min(88vh, 800px); flex-direction: column; overflow: hidden; }
    .marketplace-dialog-head { display: flex; flex: 0 0 auto; align-items: flex-start; gap: 15px; padding: 22px 24px 18px; border-bottom: 1px solid #e7eee9; background: #fff; }
    .marketplace-detail-icon { position: relative; display: grid; overflow: hidden; width: 74px; height: 74px; flex: 0 0 74px; place-items: center; border-radius: 18px; color: #fff; font-size: 22px; font-weight: 800; letter-spacing: -.05em; box-shadow: inset 0 1px 0 rgba(255,255,255,.18); }
    .marketplace-detail-icon img { width: 100%; height: 100%; object-fit: cover; object-position: center; }
    .marketplace-detail-identity { min-width: 0; flex: 1 1 auto; }
    .marketplace-detail-title { margin: 6px 0 0; color: #17231f; font-family: "Fraunces", Georgia, serif; font-size: 29px; font-weight: 500; letter-spacing: -.035em; line-height: 1.05; }
    .marketplace-detail-author { margin-top: 5px; color: #7b8b83; font-size: 11px; line-height: 1.5; }
    .marketplace-detail-pills { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; margin-top: 10px; }
    .marketplace-detail-close { display: grid; width: 32px; height: 32px; flex: 0 0 32px; place-items: center; border: 1px solid #dfe8e2; border-radius: 9px; background: #fff; color: #687771; cursor: pointer; }
    .marketplace-detail-close:hover { border-color: #0f766e; background: #f3faf6; color: #0f604f; }
    .marketplace-detail-body { display: grid; flex: 1 1 auto; gap: 18px; min-height: 0; overflow-y: auto; padding: 20px 24px 22px; }
    .marketplace-detail-description { margin: 0; color: #53645c; font-size: 12px; line-height: 1.8; white-space: pre-line; }
    .marketplace-detail-heading { margin: 0 0 8px; color: #17231f; font-size: 10px; font-weight: 800; letter-spacing: .12em; text-transform: uppercase; }
    .marketplace-detail-features { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 7px 16px; margin: 0; padding: 0; list-style: none; }
    .marketplace-detail-features li { position: relative; padding-left: 21px; color: #53645c; font-size: 11px; line-height: 1.6; }
    .marketplace-detail-features li::before { position: absolute; top: 3px; left: 0; display: grid; width: 14px; height: 14px; place-items: center; border-radius: 50%; background: #e8f5ed; color: #0f604f; content: '✓'; font-size: 9px; font-weight: 800; }
    .marketplace-detail-meta { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 8px; }
    .marketplace-detail-meta div { min-width: 0; padding: 10px 11px; border: 1px solid #e7eee9; border-radius: 9px; background: #fbfdfc; }
    .marketplace-detail-meta dt { color: #8a9892; font-size: 9px; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; }
    .marketplace-detail-meta dd { overflow: hidden; margin-top: 3px; color: #43564c; font-size: 11px; font-weight: 700; text-overflow: ellipsis; white-space: nowrap; }
    .marketplace-detail-status { border: 1px solid #d8e8df; border-radius: 9px; background: #f6fbf8; padding: 10px 12px; color: #50665a; font-size: 11px; line-height: 1.55; }
    .marketplace-detail-status.is-error { border-color: #f0cccc; background: #fff7f7; color: #9b3f3f; }
    .marketplace-detail-waiting { display: inline-flex; align-items: center; gap: 6px; color: #6d7f76; font-size: 10px; line-height: 1.4; }
    .marketplace-detail-waiting::before { content: ""; width: 6px; height: 6px; flex: 0 0 6px; border-radius: 999px; background: #0f766e; animation: marketplace-waiting-pulse 1.4s ease-in-out infinite; }
    @keyframes marketplace-waiting-pulse { 0%, 100% { opacity: .35; } 50% { opacity: 1; } }
    .marketplace-detail-footer { display: flex; flex: 0 0 auto; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px; padding: 15px 24px 20px; border-top: 1px solid #e7eee9; background: #fbfdfc; }
    .marketplace-detail-price { color: #17231f; }
    .marketplace-detail-price span { display: block; color: #8a9892; font-size: 9px; font-weight: 800; letter-spacing: .09em; text-transform: uppercase; }
    .marketplace-detail-price strong { display: block; margin-top: 2px; font-family: "Fraunces", Georgia, serif; font-size: 25px; font-weight: 500; line-height: 1; }
    .marketplace-detail-actions { display: flex; flex-wrap: wrap; align-items: center; justify-content: flex-end; gap: 7px; }
    .marketplace-detail-actions form { margin: 0; }
    .marketplace-detail-actions .marketplace-button { min-height: 36px; padding: 8px 13px; font-size: 11px; }
    .marketplace-detail-note { flex-basis: 100%; margin: 0; color: #8a9892; font-size: 10px; line-height: 1.55; text-align: right; }
    body.marketplace-dialog-open { overflow: hidden; }
    @media (max-width: 620px) {
        .marketplace-dialog-head { padding: 18px; }
        .marketplace-detail-icon { width: 58px; height: 58px; flex-basis: 58px; border-radius: 14px; font-size: 18px; }
        .marketplace-detail-title { font-size: 24px; }
        .marketplace-detail-body { padding: 17px 18px 18px; }
        .marketplace-detail-features { grid-template-columns: 1fr; }
        .marketplace-detail-meta { grid-template-columns: 1fr 1fr; }
        .marketplace-detail-footer { align-items: stretch; padding: 14px 18px 18px; }
        .marketplace-detail-actions { justify-content: stretch; }
        .marketplace-detail-actions .marketplace-button { flex: 1 1 auto; }
        .marketplace-detail-waiting { flex-basis: 100%; }
        .marketplace-detail-note { text-align: left; }
    }
    @media (min-width: 760px) {
        .marketplace-tools { flex-direction: row; align-items: center; justify-content: space-between; }
        .marketplace-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }
    @media (min-width: 1180px) {
        .marketplace-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
    }
</style>

<div id="marketplace-app" class="space-y-5">
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

    <section class="marketplace-head">
        <div>
            <div class="marketplace-kicker">ShopKeeprr Store</div>
            <h2 class="marketplace-title">Add plugins</h2>
            <p class="marketplace-lede">Browse plugins and themes for your store, compare what they add, and install the ones you need. Every extension is reviewed by the Store, and free plugins are clearly marked.</p>
            <div class="marketplace-account">
                <span class="marketplace-account-dot {{ $connection ? 'is-connected' : '' }}" aria-hidden="true"></span>
                @if($connection)
                    <span>Connected as <strong>{{ $connection->account_email }}</strong> · {{ $connection->account_name ?: 'ShopKeeprr Store' }}</span>
                @else
                    <span>No Store account connected. Connect to install free or purchased extensions.</span>
                @endif
            </div>
        </div>
        <div class="marketplace-head-actions">
            @if($connection)
                <form method="post" action="{{ route('admin.marketplace.refresh') }}">
                    @csrf
                    <button class="marketplace-button">Refresh catalog</button>
                </form>
                <a href="{{ route('admin.settings.index', ['section' => 'store']) }}" class="marketplace-button">Store settings</a>
                <form method="post" action="{{ route('admin.marketplace.disconnect') }}" onsubmit="return confirm('Disconnect this store from the ShopKeeprr Store? Installed plugins keep working.')">
                    @csrf
                    <button class="marketplace-button marketplace-button--quiet">Disconnect</button>
                </form>
            @else
                <form method="post" action="{{ route('admin.marketplace.connect') }}">
                    @csrf
                    <button class="marketplace-button marketplace-button--primary">Connect Store account</button>
                </form>
            @endif
            <a href="{{ route('admin.plugins.index') }}" class="marketplace-button">Upload plugin</a>
        </div>
    </section>

    <div class="marketplace-note">
        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 9v4M12 17h.01"/><circle cx="12" cy="12" r="9"/></svg>
        <span><strong>Before you install:</strong> plugins are executable code. A plugin can read and change store data, run migrations and add routes. The marketplace review reduces risk, but it cannot sandbox a plugin. Installed plugins stay disabled until you enable them in <a href="{{ route('admin.plugins.index') }}" class="font-bold underline">Plugins</a>.</span>
    </div>

    <section class="marketplace-panel">
        <div class="marketplace-toolbar">
            <div class="marketplace-tools">
                <div class="marketplace-search-wrap">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                    <input id="marketplace-search" class="marketplace-search" type="search" placeholder="Search plugins, themes and authors" autocomplete="off" aria-label="Search the marketplace">
                    <button id="marketplace-search-clear" class="marketplace-clear" type="button" aria-label="Clear search" hidden>×</button>
                </div>
                <div class="marketplace-filters" role="group" aria-label="Filter extensions">
                    <button type="button" class="marketplace-filter is-active" data-marketplace-filter="all">All <span>{{ $allCount }}</span></button>
                    <button type="button" class="marketplace-filter" data-marketplace-filter="plugins">Plugins <span>{{ $pluginCount }}</span></button>
                    <button type="button" class="marketplace-filter" data-marketplace-filter="themes">Themes <span>{{ $themeCount }}</span></button>
                    <button type="button" class="marketplace-filter" data-marketplace-filter="free">Free <span>{{ $freeCount }}</span></button>
                    <button type="button" class="marketplace-filter" data-marketplace-filter="installed">Installed <span>{{ $installedCount }}</span></button>
                </div>
            </div>
        </div>

        <div id="marketplace-catalog-panel">
            <div class="marketplace-results">
                <span>Showing <strong id="marketplace-count">{{ $allCount }}</strong> extensions</span>
                <a href="{{ route('admin.plugins.index') }}">Manage uploaded plugins</a>
            </div>

            <div id="marketplace-grid" class="marketplace-grid">
                @forelse($catalog as $product)
                    @php
                        $name = trim((string) ($product['name'] ?? 'Unnamed extension'));
                        $slug = (string) ($product['slug'] ?? '');
                        $type = (string) ($product['type'] ?? 'plugin');
                        $isTheme = $type === 'theme';
                        $isBusinessModel = $type === 'business_model';
                        $kind = $isTheme ? 'theme' : 'plugin';
                        $typeLabel = $isTheme ? 'Theme' : ($isBusinessModel ? 'Store add-on' : 'Plugin');
                        $tagline = trim((string) ($product['tagline'] ?? '')) ?: 'No description provided.';
                        $developer = trim((string) ($product['developer'] ?? '')) ?: 'ShopKeeprr';
                        $version = trim((string) ($product['version'] ?? '')) ?: '—';
                        $price = (int) ($product['price_cents'] ?? 0);
                        $owned = in_array($slug, $library, true);
                        $installedVersion = $installed[$slug] ?? null;
                        $release = $product['release'] ?? null;
                        $approved = is_array($release) && in_array(($release['scan_status'] ?? ''), ['clean', 'warning'], true);
                        $canInstall = $connection && $slug !== '' && $approved && ($price === 0 || $owned);
                        $isBlocked = is_array($release) && ($release['scan_status'] ?? '') === 'blocked';
                        $words = preg_split('/\s+/', $name) ?: [];
                        $initials = strtoupper(implode('', array_map(fn ($word) => mb_substr($word, 0, 1), array_slice($words, 0, 2))));
                        if (mb_strlen($initials) < 2) {
                            $initials = strtoupper(mb_substr($name, 0, 2));
                        }
                        $palette = $iconPalettes[abs(crc32($slug ?: $name)) % count($iconPalettes)];
                        $cover = trim((string) ($product['cover_url'] ?? ''));
                        $description = trim((string) ($product['description'] ?? '')) ?: $tagline;
                        $description = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags(preg_replace('/<\/(?:p|div|h[1-6]|li|br|tr)\s*>/i', ' ', $description)), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?: $description);
                        $features = array_values(array_filter((array) ($product['features'] ?? []), static fn (mixed $feature): bool => is_scalar($feature) && trim((string) $feature) !== ''));
                        $featuresJson = json_encode($features, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?: '[]';
                        $purchaseUrl = trim((string) ($product['purchase_url'] ?? ''));
                        $priceLabel = $price === 0 ? 'Free' : '$'.number_format($price / 100, 2);
                        $releaseVersion = is_array($release) ? trim((string) ($release['version'] ?? '')) : '';
                        $releaseStatus = is_array($release) ? trim((string) ($release['scan_status'] ?? '')) : '';
                        $releaseSize = is_array($release) ? (int) ($release['file_size'] ?? 0) : 0;
                        $releasePublished = is_array($release) ? trim((string) ($release['published_at'] ?? '')) : '';
                    @endphp
                    <article class="marketplace-card"
                        data-marketplace-card
                        data-kind="{{ $kind }}"
                        data-free="{{ $price === 0 ? '1' : '0' }}"
                        data-installed="{{ $installedVersion ? '1' : '0' }}"
                        data-name="{{ $name }}"
                        data-slug="{{ $slug }}"
                        data-type-label="{{ $typeLabel }}"
                        data-version="{{ $version }}"
                        data-developer="{{ $developer }}"
                        data-price-label="{{ $priceLabel }}"
                        data-price-cents="{{ $price }}"
                        data-description="{{ $description }}"
                        data-features="{{ $featuresJson }}"
                        data-owned="{{ $owned ? '1' : '0' }}"
                        data-approved="{{ $approved ? '1' : '0' }}"
                        data-blocked="{{ $isBlocked ? '1' : '0' }}"
                        data-connection="{{ $connection ? '1' : '0' }}"
                        data-purchase-url="{{ $purchaseUrl }}"
                        data-cover="{{ $cover }}"
                        data-initials="{{ $initials }}"
                        data-color-start="{{ $palette[0] }}"
                        data-color-end="{{ $palette[1] }}"
                        data-release-version="{{ $releaseVersion }}"
                        data-release-status="{{ $releaseStatus }}"
                        data-release-size="{{ $releaseSize }}"
                        data-release-published="{{ $releasePublished }}"
                        data-search="{{ strtolower($name.' '.$developer.' '.$tagline.' '.$typeLabel.' '.(string) ($product['category'] ?? '')) }}">
                        <div class="marketplace-card-head">
                            <div class="marketplace-icon" @if($cover === '') style="background: linear-gradient(135deg, {{ $palette[0] }}, {{ $palette[1] }});" @endif aria-hidden="true">
                                @if($cover !== '')
                                    <img src="{{ $cover }}" alt="" loading="lazy" referrerpolicy="no-referrer">
                                @else
                                    {{ $initials }}
                                @endif
                            </div>
                            <div class="marketplace-card-title">
                                <div class="marketplace-card-meta">
                                    <span class="marketplace-type">{{ $typeLabel }}</span>
                                    <span class="marketplace-version">v{{ $version }}</span>
                                </div>
                                <h3 class="marketplace-card-name">{{ $name }}</h3>
                                <p class="marketplace-card-author">by <strong>{{ $developer }}</strong>@if($isBusinessModel) <span>·</span> business model @endif</p>
                            </div>
                            <span class="marketplace-price {{ $price === 0 ? 'is-free' : '' }}">{{ $priceLabel }}</span>
                        </div>

                        <p class="marketplace-card-copy">{{ $tagline }}</p>

                        <div class="marketplace-card-footer">
                            <div class="marketplace-badges">
                                @if($installedVersion)
                                    <span class="marketplace-pill is-installed">Installed</span>
                                @elseif($owned)
                                    <span class="marketplace-pill is-owned">Owned</span>
                                @elseif($price === 0)
                                    <span class="marketplace-pill is-free">Free</span>
                                @endif
                                @if($isBlocked)
                                    <span class="marketplace-pill is-blocked">Review blocked</span>
                                @endif
                            </div>
                            <div class="marketplace-actions">
                                @if($installedVersion)
                                    <a href="{{ route('admin.plugins.index') }}" class="marketplace-button marketplace-button--quiet">Manage</a>
                                @elseif(! $connection)
                                    <form method="post" action="{{ route('admin.marketplace.connect') }}">
                                        @csrf
                                        <button class="marketplace-button marketplace-button--secondary">Connect to install</button>
                                    </form>
                                @elseif($canInstall)
                                    <form method="post" action="{{ route('admin.marketplace.install') }}">
                                        @csrf
                                        <input type="hidden" name="product" value="{{ $slug }}">
                                        <input type="hidden" name="version" value="{{ $product['version'] ?? '' }}">
                                        <button class="marketplace-button marketplace-button--primary">Install now</button>
                                    </form>
                                @elseif($price > 0 && ! $owned)
                                    <button type="button" class="marketplace-button marketplace-button--primary" data-marketplace-details>Details &amp; buy</button>
                                @else
                                    <span class="marketplace-managed">Not installable yet</span>
                                @endif

                                @if($installedVersion || ! ($price > 0 && ! $owned))
                                    <button type="button" class="marketplace-more" data-marketplace-details>Details</button>
                                @endif
                            </div>
                        </div>
                    </article>
                @empty
                    <div class="marketplace-empty" style="grid-column: 1 / -1;">
                        <h3>No extensions in the catalog yet.</h3>
                        <p>The Store catalog is reachable, but it returned no published plugins. Check back after the next marketplace release.</p>
                    </div>
                @endforelse
            </div>

            <div id="marketplace-empty" class="marketplace-empty marketplace-hidden">
                <h3>Nothing matches that search.</h3>
                <p>Try a different name or developer, or clear the filters to see every extension.</p>
            </div>
        </div>
    </section>

    @if($ideaCount > 0)
        <section class="marketplace-panel" data-idea-section>
            <div class="marketplace-section-head">
                <div>
                    <p class="marketplace-kicker">Free plugin ideas</p>
                    <h2>What should we build next?</h2>
                    <p>A working list of free extensions we want to see on ShopKeeprr. These are concepts, not installable packages yet. The list is deduplicated against the Store catalog and kept here so builders can find a useful place to start.</p>
                </div>
                <span class="marketplace-concept-count">{{ $ideaCount }} concepts</span>
            </div>
            <div class="marketplace-grid">
                @foreach($ideas as $idea)
                    @php
                        $ideaName = trim((string) ($idea['name'] ?? 'Untitled idea'));
                        $ideaDeveloper = trim((string) ($idea['developer'] ?? '')) ?: 'ShopKeeprr';
                        $ideaCategory = trim((string) ($idea['category'] ?? '')) ?: 'Extension';
                        $ideaTagline = trim((string) ($idea['tagline'] ?? '')) ?: 'A free extension concept for ShopKeeprr.';
                        $ideaPurpose = trim((string) ($idea['purpose'] ?? ''));
                        $ideaIcon = trim((string) ($idea['icon'] ?? ''));
                        if ($ideaIcon === '') {
                            $ideaWords = preg_split('/\s+/', $ideaName) ?: [];
                            $ideaIcon = strtoupper(implode('', array_map(fn ($word) => mb_substr($word, 0, 1), array_slice($ideaWords, 0, 2))));
                        }
                        $ideaPalette = $iconPalettes[abs(crc32((string) ($idea['slug'] ?? $ideaName))) % count($iconPalettes)];
                    @endphp
                    <article class="marketplace-card marketplace-card--idea"
                        data-marketplace-card
                        data-kind="idea"
                        data-free="1"
                        data-installed="0"
                        data-search="{{ strtolower($ideaName.' '.$ideaDeveloper.' '.$ideaTagline.' '.$ideaCategory.' '.$ideaPurpose) }}">
                        <div class="marketplace-card-head">
                            <div class="marketplace-icon" style="background: linear-gradient(135deg, {{ $ideaPalette[0] }}, {{ $ideaPalette[1] }});" aria-hidden="true">{{ $ideaIcon }}</div>
                            <div class="marketplace-card-title">
                                <div class="marketplace-card-meta">
                                    <span class="marketplace-type">Concept</span>
                                    <span class="marketplace-version">{{ $ideaCategory }}</span>
                                </div>
                                <h3 class="marketplace-card-name">{{ $ideaName }}</h3>
                                <p class="marketplace-card-author">by <strong>{{ $ideaDeveloper }}</strong></p>
                            </div>
                            <span class="marketplace-price is-free">Free</span>
                        </div>
                        <p class="marketplace-card-copy">{{ $ideaTagline }}</p>
                        @if($ideaPurpose !== '')
                            <p class="marketplace-idea-purpose">{{ $ideaPurpose }}</p>
                        @endif
                        <div class="marketplace-card-footer">
                            <span class="marketplace-pill is-concept">In the workshop</span>
                            <span class="marketplace-more">Not installable yet</span>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>
    @endif

    <dialog id="marketplace-detail" class="marketplace-dialog" aria-labelledby="marketplace-detail-title">
        <div class="marketplace-dialog-card">
            <div class="marketplace-dialog-head">
                <div id="marketplace-detail-icon" class="marketplace-detail-icon" aria-hidden="true">
                    <span data-detail-initials></span>
                    <img data-detail-cover alt="" hidden referrerpolicy="no-referrer">
                </div>
                <div class="marketplace-detail-identity">
                    <div class="marketplace-card-meta">
                        <span class="marketplace-type" data-detail-type></span>
                        <span class="marketplace-version" data-detail-version></span>
                    </div>
                    <h2 id="marketplace-detail-title" class="marketplace-detail-title" data-detail-title></h2>
                    <p class="marketplace-detail-author">by <strong data-detail-author></strong></p>
                    <div class="marketplace-detail-pills">
                        <span class="marketplace-pill" data-detail-state></span>
                        <span class="marketplace-pill is-blocked" data-detail-blocked hidden>Review blocked</span>
                    </div>
                </div>
                <button type="button" class="marketplace-detail-close" data-detail-close aria-label="Close details">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18"/></svg>
                </button>
            </div>

            <div class="marketplace-detail-body">
                <p class="marketplace-detail-description" data-detail-description></p>

                <div data-detail-feature-section>
                    <div class="marketplace-detail-heading">What it adds</div>
                    <ul class="marketplace-detail-features" data-detail-features></ul>
                </div>

                <div data-detail-release-section>
                    <div class="marketplace-detail-heading">Release</div>
                    <dl class="marketplace-detail-meta">
                        <div><dt>Version</dt><dd data-detail-release-version></dd></div>
                        <div><dt>Review</dt><dd data-detail-release-status></dd></div>
                        <div><dt>Size</dt><dd data-detail-release-size></dd></div>
                        <div><dt>Released</dt><dd data-detail-release-published></dd></div>
                    </dl>
                </div>

                <div class="marketplace-detail-status" data-detail-status role="status" hidden></div>
            </div>

            <div class="marketplace-detail-footer">
                <div class="marketplace-detail-price">
                    <span>Price</span>
                    <strong data-detail-price></strong>
                </div>

                <div class="marketplace-detail-actions">
                    <form method="post" action="{{ route('admin.marketplace.install') }}" data-detail-install hidden>
                        @csrf
                        <input type="hidden" name="product" data-detail-product>
                        <input type="hidden" name="version" data-detail-product-version>
                        <button type="submit" class="marketplace-button marketplace-button--primary">Install now</button>
                    </form>

                    <a href="#" target="_blank" rel="noopener noreferrer" class="marketplace-button marketplace-button--primary" data-detail-buy hidden>Buy in Store</a>
                    <button type="button" class="marketplace-button marketplace-button--secondary" data-detail-check hidden>Check purchase</button>
                    <span class="marketplace-detail-waiting" data-detail-waiting role="status" aria-live="polite" hidden>Waiting for payment confirmation...</span>

                    <form method="post" action="{{ route('admin.marketplace.connect') }}" data-detail-connect hidden>
                        @csrf
                        <button class="marketplace-button marketplace-button--primary">Connect Store account</button>
                    </form>

                    <a href="{{ route('admin.plugins.index') }}" class="marketplace-button marketplace-button--secondary" data-detail-manage hidden>Manage plugin</a>
                    <span class="marketplace-managed" data-detail-unavailable hidden>Not installable yet</span>
                </div>

                <p class="marketplace-detail-note" data-detail-note hidden></p>
            </div>
        </div>
    </dialog>
</div>
@endsection

@push('scripts')
<script>
(() => {
    const root = document.querySelector('#marketplace-app');
    if (!root) return;

    const ownershipUrl = @json(route('admin.marketplace.ownership'));
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const search = root.querySelector('#marketplace-search');
    const clear = root.querySelector('#marketplace-search-clear');
    const count = root.querySelector('#marketplace-count');
    const empty = root.querySelector('#marketplace-empty');
    const ideaSection = root.querySelector('[data-idea-section]');
    const filters = Array.from(root.querySelectorAll('[data-marketplace-filter]'));
    const cards = Array.from(root.querySelectorAll('[data-marketplace-card]'));
    let activeFilter = 'all';

    const apply = () => {
        const query = (search?.value || '').trim().toLowerCase();
        let visible = 0;

        cards.forEach((card) => {
            const kind = card.dataset.kind || 'plugin';
            const isIdea = kind === 'idea';
            const matchesSearch = query === '' || (card.dataset.search || '').includes(query);
            let matchesFilter = true;

            if (activeFilter === 'plugins') matchesFilter = kind === 'plugin';
            if (activeFilter === 'themes') matchesFilter = kind === 'theme';
            if (activeFilter === 'free') matchesFilter = !isIdea && card.dataset.free === '1';
            if (activeFilter === 'installed') matchesFilter = !isIdea && card.dataset.installed === '1';

            const show = matchesSearch && matchesFilter;
            card.classList.toggle('marketplace-hidden', !show);
            if (show) visible += 1;
        });

        if (count) count.textContent = String(visible);
        if (empty) empty.classList.toggle('marketplace-hidden', visible !== 0);
        if (ideaSection) ideaSection.classList.toggle('marketplace-hidden', activeFilter !== 'all');
        if (clear) clear.hidden = query === '';
    };

    filters.forEach((button) => {
        button.addEventListener('click', () => {
            activeFilter = button.dataset.marketplaceFilter || 'all';
            filters.forEach((item) => item.classList.toggle('is-active', item === button));
            apply();
        });
    });

    search?.addEventListener('input', apply);
    clear?.addEventListener('click', () => {
        if (!search) return;
        search.value = '';
        search.focus();
        apply();
    });

    const dialog = root.querySelector('#marketplace-detail');

    if (dialog) {
        const title = dialog.querySelector('[data-detail-title]');
        const type = dialog.querySelector('[data-detail-type]');
        const version = dialog.querySelector('[data-detail-version]');
        const author = dialog.querySelector('[data-detail-author]');
        const description = dialog.querySelector('[data-detail-description]');
        const icon = dialog.querySelector('#marketplace-detail-icon');
        const iconInitials = dialog.querySelector('[data-detail-initials]');
        const iconCover = dialog.querySelector('[data-detail-cover]');
        const state = dialog.querySelector('[data-detail-state]');
        const blockedPill = dialog.querySelector('[data-detail-blocked]');
        const featureSection = dialog.querySelector('[data-detail-feature-section]');
        const features = dialog.querySelector('[data-detail-features]');
        const releaseSection = dialog.querySelector('[data-detail-release-section]');
        const releaseVersion = dialog.querySelector('[data-detail-release-version]');
        const releaseStatus = dialog.querySelector('[data-detail-release-status]');
        const releaseSize = dialog.querySelector('[data-detail-release-size]');
        const releasePublished = dialog.querySelector('[data-detail-release-published]');
        const status = dialog.querySelector('[data-detail-status]');
        const price = dialog.querySelector('[data-detail-price]');
        const installForm = dialog.querySelector('[data-detail-install]');
        const installProduct = dialog.querySelector('[data-detail-product]');
        const installVersion = dialog.querySelector('[data-detail-product-version]');
        const buy = dialog.querySelector('[data-detail-buy]');
        const check = dialog.querySelector('[data-detail-check]');
        const waiting = dialog.querySelector('[data-detail-waiting]');
        const connect = dialog.querySelector('[data-detail-connect]');
        const manage = dialog.querySelector('[data-detail-manage]');
        const unavailable = dialog.querySelector('[data-detail-unavailable]');
        const note = dialog.querySelector('[data-detail-note]');
        let activeCard = null;
        let lastTrigger = null;
        let awaitingPurchase = false;
        let checkingOwnership = false;
        let checkoutPopup = null;
        let purchasePoll = null;
        let purchasePollDeadline = 0;
        let nextOwnershipCheck = 0;
        const purchasePollInterval = 3000;
        const purchasePollTimeout = 10 * 60 * 1000;

        const setStatus = (message, isError = false) => {
            status.textContent = message || '';
            status.hidden = !message;
            status.classList.toggle('is-error', isError);
        };

        const safeUrl = (value) => {
            if (!value) return '';

            try {
                const url = new URL(value, window.location.origin);
                return ['http:', 'https:'].includes(url.protocol) ? url.href : '';
            } catch (error) {
                return '';
            }
        };

        const formatBytes = (bytes) => {
            const size = Number(bytes || 0);
            if (!size) return '—';
            if (size < 1024) return `${size} B`;
            if (size < 1024 * 1024) return `${(size / 1024).toFixed(1)} KB`;
            return `${(size / (1024 * 1024)).toFixed(1)} MB`;
        };

        const formatDate = (value) => {
            if (!value) return '—';
            const date = new Date(value);
            return Number.isNaN(date.getTime()) ? value : date.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
        };

        const stopPurchasePolling = () => {
            if (purchasePoll !== null) window.clearInterval(purchasePoll);
            purchasePoll = null;
            purchasePollDeadline = 0;
            nextOwnershipCheck = 0;
            if (waiting) waiting.hidden = true;
        };

        const closeCheckoutPopup = () => {
            if (checkoutPopup && !checkoutPopup.closed) {
                try {
                    checkoutPopup.close();
                } catch (error) {
                    // The Store can be cross-origin; closing an opened window is best-effort.
                }
            }
            checkoutPopup = null;
        };

        const hideActions = () => {
            installForm.hidden = true;
            buy.hidden = true;
            check.hidden = true;
            connect.hidden = true;
            manage.hidden = true;
            unavailable.hidden = true;
        };

        const renderState = () => {
            if (!activeCard) return;

            const installed = activeCard.dataset.installed === '1';
            const owned = activeCard.dataset.owned === '1';
            const free = Number(activeCard.dataset.priceCents || 0) === 0;
            const blocked = activeCard.dataset.blocked === '1';

            if (installed) {
                state.textContent = 'Installed';
                state.className = 'marketplace-pill is-installed';
            } else if (owned) {
                state.textContent = 'Owned';
                state.className = 'marketplace-pill is-owned';
            } else if (free) {
                state.textContent = 'Free';
                state.className = 'marketplace-pill is-free';
            } else {
                state.textContent = 'Paid extension';
                state.className = 'marketplace-pill is-owned';
            }

            blockedPill.hidden = !blocked;
        };

        const renderActions = () => {
            if (!activeCard) return;

            hideActions();

            const installed = activeCard.dataset.installed === '1';
            const connected = activeCard.dataset.connection === '1';
            const approved = activeCard.dataset.approved === '1';
            const blocked = activeCard.dataset.blocked === '1';
            const owned = activeCard.dataset.owned === '1';
            const free = Number(activeCard.dataset.priceCents || 0) === 0;
            const canInstall = connected && approved && !blocked && (free || owned);

            if (installed) {
                manage.hidden = false;
                note.textContent = 'This extension is installed. Enable or update it from Plugins.';
                note.hidden = false;
                return;
            }

            if (blocked) {
                unavailable.textContent = 'The current release did not pass the Store review.';
                unavailable.hidden = false;
                note.textContent = 'Installation is disabled until the developer publishes an approved release.';
                note.hidden = false;
                return;
            }

            if (!connected) {
                connect.hidden = false;
                note.textContent = 'Connect the Store account that owns this extension. Your Store password never leaves the Store.';
                note.hidden = false;
                return;
            }

            if (canInstall) {
                installProduct.value = activeCard.dataset.slug || '';
                installVersion.value = activeCard.dataset.releaseVersion || activeCard.dataset.version || '';
                installForm.hidden = false;
                note.textContent = 'The package is downloaded and checksum-verified on this server. It stays disabled until you enable it in Plugins.';
                note.hidden = false;
                return;
            }

            if (! free && ! owned) {
                const purchaseUrl = safeUrl(activeCard.dataset.purchaseUrl || '');

                if (purchaseUrl) {
                    buy.href = purchaseUrl;
                    buy.hidden = false;
                }

                check.hidden = false;
                note.textContent = 'Complete checkout in the Store pop-up. This dialog checks your library automatically while it is open. You can also choose Check purchase.';
                note.hidden = false;
                return;
            }

            unavailable.textContent = 'This extension is not installable yet.';
            unavailable.hidden = false;
            note.textContent = 'The current release is not available for one-click installation.';
            note.hidden = false;
        };

        const openDetails = (trigger) => {
            const card = trigger.closest('[data-marketplace-card]');
            if (!card) return;

            activeCard = card;
            lastTrigger = trigger;
            awaitingPurchase = false;
            stopPurchasePolling();
            check.disabled = false;
            check.textContent = 'Check purchase';
            const cover = safeUrl(card.dataset.cover || '');
            const cardFeatures = (() => {
                try {
                    const parsed = JSON.parse(card.dataset.features || '[]');
                    return Array.isArray(parsed) ? parsed.filter((item) => String(item).trim() !== '') : [];
                } catch (error) {
                    return [];
                }
            })();

            title.textContent = card.dataset.name || 'Extension details';
            type.textContent = card.dataset.typeLabel || 'Extension';
            version.textContent = card.dataset.version ? `v${card.dataset.version}` : '';
            author.textContent = card.dataset.developer || 'ShopKeeprr';
            description.textContent = card.dataset.description || 'No description provided.';
            price.textContent = card.dataset.priceLabel || 'Free';
            setStatus('');

            icon.style.background = `linear-gradient(135deg, ${card.dataset.colorStart || '#0f766e'}, ${card.dataset.colorEnd || '#082b28'})`;
            iconInitials.textContent = card.dataset.initials || '';
            iconCover.hidden = true;
            iconInitials.hidden = false;
            iconCover.onerror = () => {
                iconCover.hidden = true;
                iconInitials.hidden = false;
            };

            if (cover) {
                iconCover.src = cover;
                iconCover.hidden = false;
                iconInitials.hidden = true;
            }

            features.replaceChildren();
            cardFeatures.forEach((feature) => {
                const item = document.createElement('li');
                item.textContent = String(feature);
                features.append(item);
            });
            featureSection.hidden = cardFeatures.length === 0;

            releaseSection.hidden = !card.dataset.releaseVersion;
            releaseVersion.textContent = card.dataset.releaseVersion || '—';
            releaseStatus.textContent = ({ clean: 'Passed', warning: 'Passed with warnings', blocked: 'Blocked' })[card.dataset.releaseStatus] || '—';
            releaseSize.textContent = formatBytes(card.dataset.releaseSize);
            releasePublished.textContent = formatDate(card.dataset.releasePublished);

            renderState();
            renderActions();

            document.body.classList.add('marketplace-dialog-open');
            if (typeof dialog.showModal === 'function') {
                dialog.showModal();
            } else {
                dialog.setAttribute('open', '');
            }
        };

        const closeDetails = () => {
            awaitingPurchase = false;
            stopPurchasePolling();
            if (typeof dialog.close === 'function' && dialog.open) {
                dialog.close();
            } else {
                dialog.removeAttribute('open');
            }
            document.body.classList.remove('marketplace-dialog-open');
            lastTrigger?.focus();
        };

        root.querySelectorAll('[data-marketplace-details]').forEach((trigger) => {
            trigger.addEventListener('click', () => openDetails(trigger));
        });

        dialog.querySelector('[data-detail-close]')?.addEventListener('click', closeDetails);
        installForm?.addEventListener('submit', () => {
            const button = installForm.querySelector('button[type="submit"]');
            if (button) {
                button.disabled = true;
                button.textContent = 'Installing…';
            }
        });
        dialog.addEventListener('click', (event) => {
            if (event.target === dialog) closeDetails();
        });
        dialog.addEventListener('cancel', (event) => {
            event.preventDefault();
            closeDetails();
        });
        dialog.addEventListener('close', () => {
            awaitingPurchase = false;
            stopPurchasePolling();
            document.body.classList.remove('marketplace-dialog-open');
            lastTrigger?.focus();
        });

        const checkOwnership = async ({ automatic = false, polling = false } = {}) => {
            if (!activeCard || checkingOwnership) return;

            const card = activeCard;
            checkingOwnership = true;

            if (! polling) {
                check.disabled = true;
                check.textContent = 'Checking…';
                setStatus(automatic ? 'Welcome back. Checking your Store library…' : 'Checking your linked Store account…');
            }

            try {
                const response = await fetch(ownershipUrl, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                });
                const payload = await response.json().catch(() => ({}));

                if (! response.ok) {
                    throw new Error(payload.message || 'The Store could not check this purchase.');
                }

                if (activeCard !== card || ! dialog.open) return;

                const owned = Array.isArray(payload.owned) ? payload.owned : [];

                if (owned.includes(card.dataset.slug)) {
                    card.dataset.owned = '1';
                    awaitingPurchase = false;
                    stopPurchasePolling();
                    closeCheckoutPopup();
                    renderState();
                    renderActions();
                    setStatus('Purchase found. You can install it now.');
                } else if (! polling) {
                    setStatus('No purchase found yet. Finish checkout in the Store, then check again.');
                }
            } catch (error) {
                if (activeCard === card && ! polling) {
                    setStatus(error.message || 'The Store could not check this purchase.', true);
                }
            } finally {
                checkingOwnership = false;
                if (! polling) {
                    check.disabled = false;
                    check.textContent = 'Check purchase';
                }
            }
        };

        const pollForPurchase = () => {
            if (!dialog.open || !awaitingPurchase) {
                stopPurchasePolling();
                return;
            }

            if (checkoutPopup && checkoutPopup.closed) {
                checkoutPopup = null;
                awaitingPurchase = false;
                stopPurchasePolling();
                setStatus('The checkout window was closed. Choose Buy in Store to reopen it, or use Check purchase.');
                return;
            }

            const now = Date.now();

            if (now >= purchasePollDeadline) {
                awaitingPurchase = false;
                stopPurchasePolling();
                setStatus('Payment confirmation is taking longer than expected. Use Check purchase to try again.');
                return;
            }

            if (now >= nextOwnershipCheck && !checkingOwnership) {
                nextOwnershipCheck = now + purchasePollInterval;
                void checkOwnership({ automatic: true, polling: true });
            }
        };

        const beginPurchasePolling = ({ popup = null, fallback = false } = {}) => {
            awaitingPurchase = true;
            checkoutPopup = popup;
            stopPurchasePolling();
            purchasePollDeadline = Date.now() + purchasePollTimeout;
            nextOwnershipCheck = Date.now() + purchasePollInterval;
            if (waiting) waiting.hidden = false;
            setStatus(fallback
                ? 'Checkout opened in a new tab. This dialog will confirm your purchase automatically.'
                : 'Complete checkout in the Store pop-up. This dialog will confirm your purchase automatically.');
            purchasePoll = window.setInterval(pollForPurchase, 1000);
            void checkOwnership({ automatic: true, polling: true });
        };

        buy?.addEventListener('click', (event) => {
            event.preventDefault();

            const url = safeUrl(activeCard?.dataset.purchaseUrl || '');
            if (!url) {
                setStatus('The Store checkout link is unavailable.', true);
                return;
            }

            const width = 520;
            const height = 740;
            const left = Math.max(0, Math.round((window.screen.availWidth - width) / 2));
            const top = Math.max(0, Math.round((window.screen.availHeight - height) / 2));
            const popup = window.open(url, 'shopkeeprr-checkout', `popup=yes,width=${width},height=${height},left=${left},top=${top},noopener=no`);

            if (!popup) {
                const fallback = document.createElement('a');
                fallback.href = url;
                fallback.target = '_blank';
                fallback.rel = 'noopener';
                document.body.append(fallback);
                fallback.click();
                fallback.remove();
                beginPurchasePolling({ fallback: true });
                return;
            }

            try {
                popup.focus();
            } catch (error) {
                // Focus can be denied by the browser; the popup is already open.
            }

            beginPurchasePolling({ popup });
        });

        check?.addEventListener('click', () => checkOwnership());

        const checkAfterReturn = () => {
            if (!dialog.open || document.visibilityState === 'hidden' || !awaitingPurchase) return;
            nextOwnershipCheck = Date.now() + purchasePollInterval;
            void checkOwnership({ automatic: true, polling: true });
        };

        window.addEventListener('focus', checkAfterReturn);
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible') checkAfterReturn();
        });
    }

    apply();
})();
</script>
@endpush
