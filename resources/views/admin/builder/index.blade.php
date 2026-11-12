@php
    $icons = [
        'hero' => '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 15l5-5 4 4 3-3 6 5"/><path d="M7 8h.01"/>',
        'gallery' => '<rect x="3" y="6" width="14" height="12" rx="2"/><path d="M7 3h14v12"/><path d="m3 15 4-4 3 3 2-2 5 4"/>',
        'products' => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
        'categories' => '<path d="M12 3 3 8l9 5 9-5-9-5Z"/><path d="m3 13 9 5 9-5"/><path d="m3 17 9 5 9-5"/>',
        'promo' => '<path d="M4 10v4l3 1 10 4V5L7 9l-3 1Z"/><path d="M7 15v4a1 1 0 0 0 1 1h2l1-4"/><path d="M20 9v6"/>',
        'quote' => '<path d="M9 8H5v5h4v4H5"/><path d="M19 8h-4v5h4v4h-4"/>',
        'mail' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
        'text' => '<path d="M4 6h16M4 11h11M4 16h16M4 21h9"/>',
        'image' => '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="8.5" cy="9.5" r="1.5"/><path d="m3 17 5-5 4 4 3-3 6 6"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'check' => '<path d="M12 3 5 6v5c0 4.4 2.9 8.4 7 10 4.1-1.6 7-5.6 7-10V6l-7-3Z"/><path d="m9 12 2 2 4-4"/>',
    ];

    $builderState = [
        'blocks' => is_array($page->blocks) ? $page->blocks : [],
        'definitions' => $definitions,
        'assetBase' => asset('/'),
        'csrf' => csrf_token(),
        'published' => $hasPublished,
        'enabled' => $isEnabled,
        'endpoints' => [
            'preview' => route('admin.builder.preview'),
            'draft' => route('admin.builder.draft'),
            'publish' => route('admin.builder.publish'),
            'discard' => route('admin.builder.discard'),
            'reset' => route('admin.builder.reset'),
            'disable' => route('admin.builder.disable'),
            'image' => route('admin.builder.image'),
        ],
    ];
@endphp
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>Storefront builder — {{ nexus_setting('store.name', 'ShopKeeprr') }}</title>
    @vite(['resources/css/app.css', 'resources/js/builder.js'])
</head>
<body class="builder-shell">
    <header class="builder-topbar">
        <div class="flex min-w-0 items-center gap-3">
            <a href="{{ route('admin.dashboard') }}" class="builder-icon-btn" title="Back to admin" aria-label="Back to admin">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m15 18-6-6 6-6"/></svg>
            </a>
            <div class="hidden h-7 w-px bg-[#dfe7e2] sm:block"></div>
            <div class="min-w-0">
                <div class="flex items-center gap-2">
                    <span class="truncate font-display text-[17px] leading-tight">Storefront builder</span>
                    <span class="rounded-full bg-[#e7f5f0] px-2 py-0.5 text-[9px] font-bold uppercase tracking-[.1em] text-[#0f766e]">Home</span>
                </div>
                <div class="flex items-center gap-1.5 text-[10px] text-[#8a9892]">
                    <span id="save-status" class="builder-save-status" data-tone="{{ $dirty ? 'dirty' : 'saved' }}">{{ $dirty ? 'Unsaved changes' : 'Draft saved' }}</span>
                    <span>·</span>
                    <span>Changes autosave as a draft</span>
                </div>
            </div>
        </div>

        <div class="hidden items-center gap-1 rounded-xl border border-[#e3e9e5] bg-white p-1 md:flex">
            <button type="button" class="builder-device-btn is-active" data-device="desktop" title="Desktop preview">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="12" rx="1.5"/><path d="M8 20h8M12 16v4"/></svg>
            </button>
            <button type="button" class="builder-device-btn" data-device="tablet" title="Tablet preview">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><rect x="5" y="3" width="14" height="18" rx="2"/><path d="M11 18h2"/></svg>
            </button>
            <button type="button" class="builder-device-btn" data-device="mobile" title="Mobile preview">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><rect x="7" y="2" width="10" height="20" rx="2"/><path d="M11 18h2"/></svg>
            </button>
        </div>

        <div class="flex items-center gap-2">
            <button type="button" id="toggle-library" class="builder-icon-btn lg:hidden" aria-label="Open sections">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
            </button>
            <button type="button" id="toggle-settings" class="builder-icon-btn xl:hidden" aria-label="Open section settings">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M4 7h16M4 12h10M4 17h7"/></svg>
            </button>
            <a id="preview-link" href="{{ route('admin.builder.preview') }}" target="_blank" rel="noopener" class="builder-secondary-btn hidden sm:inline-flex">
                Preview
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 4h6v6M20 4l-9 9"/><path d="M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5"/></svg>
            </a>
            <button type="button" id="publish-btn" class="builder-primary-btn">
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 19V5M6 11l6-6 6 6"/></svg>
                Publish
            </button>
        </div>
    </header>

    <div class="builder-workspace">
        <aside class="builder-panel builder-library-panel">
            <div class="builder-panel-head">
                <div class="flex items-center gap-1 rounded-lg bg-[#f3f7f4] p-1">
                    <button type="button" class="builder-tab is-active flex-1" data-builder-tab="blocks">Sections</button>
                    <button type="button" class="builder-tab flex-1" data-builder-tab="layers">Layers</button>
                </div>
            </div>

            <div id="blocks-tab" class="builder-panel-body">
                <label class="relative block">
                    <span class="sr-only">Search sections</span>
                    <svg class="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-[#9aa8a2]" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg>
                    <input id="block-search" type="search" class="input h-9 pl-9 text-xs" placeholder="Search sections…">
                </label>

                <div class="mt-4 space-y-5" id="builder-library">
                    @foreach($grouped as $category => $items)
                        <div data-library-group>
                            <div class="builder-library-group">{{ $category }}</div>
                            <div class="mt-2 grid gap-2">
                                @foreach($items as $type => $definition)
                                    <button type="button" draggable="true" data-add-block="{{ $type }}"
                                            data-search="{{ strtolower($definition['label'].' '.$definition['description'].' '.$category) }}"
                                            class="builder-block-card">
                                        <span class="builder-block-icon">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24">{!! $icons[$definition['icon']] ?? $icons['text'] !!}</svg>
                                        </span>
                                        <span class="min-w-0 text-left">
                                            <span class="block text-[12px] font-semibold text-[#17231f]">{{ $definition['label'] }}</span>
                                            <span class="mt-0.5 block text-[10px] leading-4 text-[#8a9892]">{{ $definition['description'] }}</span>
                                        </span>
                                        <span class="ml-auto text-[16px] font-light text-[#b7c2bd]">+</span>
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div id="layers-tab" class="builder-panel-body hidden">
                <p class="text-[10px] leading-4 text-[#8a9892]">Drag a layer to reorder the page. Click one to edit its content.</p>
                <div id="layers-list" class="mt-3 space-y-1.5"></div>
                <div class="mt-4 rounded-xl border border-dashed border-[#dfe7e2] p-3 text-[10px] leading-4 text-[#8a9892]">
                    Tip: you can also drag sections directly in the preview, or drag a new section from this panel onto the page.
                </div>
            </div>

            <div class="builder-panel-foot">
                <svg class="h-3.5 w-3.5 shrink-0 text-[#0f766e]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20 7 9.5 17.5 4 12"/></svg>
                <span>The active theme supplies colours, fonts and product cards. Open Theme to fine-tune them.</span>
            </div>
        </aside>

        <main class="builder-canvas">
            <div class="builder-canvas-bar">
                <div class="flex items-center gap-2">
                    <span class="grid h-6 w-6 place-items-center rounded-md bg-[#e7f5f0] text-[9px] font-bold text-[#0f766e]">{{ mb_substr(nexus_setting('store.name', 'ShopKeeprr'), 0, 1) }}</span>
                    <span class="text-[11px] font-semibold text-[#4d5d56]">Homepage</span>
                    <span class="rounded bg-[#f0f4f1] px-1.5 py-0.5 text-[9px] font-semibold text-[#8a9892]">Draft preview</span>
                </div>
                <div class="hidden items-center gap-1.5 text-[10px] text-[#8a9892] sm:flex">
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5Z"/></svg>
                    Click any text on the page to edit it
                </div>
            </div>

            <div class="builder-canvas-stage">
                <div class="builder-device-frame" id="builder-device" data-device="desktop">
                    <iframe id="builder-frame" src="{{ route('admin.builder.preview') }}" title="Storefront preview"></iframe>
                    <div id="frame-loading" class="builder-frame-loading">
                        <span class="builder-spinner"></span>
                        <span>Loading your storefront…</span>
                    </div>
                </div>
            </div>
        </main>

        <aside class="builder-panel builder-settings-panel">
            <div class="builder-panel-head flex items-center justify-between">
                <div>
                    <div class="text-[10px] font-bold uppercase tracking-[.14em] text-[#0f766e]">Build</div>
                    <div class="text-sm font-semibold text-[#17231f]">Section settings</div>
                </div>
                <button type="button" id="close-settings" class="builder-icon-btn xl:hidden" aria-label="Close settings">×</button>
            </div>

            <div class="border-b border-[#edf2ef] bg-[#fbfdfc] px-4 py-3">
                <div class="flex items-center justify-between gap-2">
                    <span class="text-[9px] font-bold uppercase tracking-[.14em] text-[#8a9892]">Page status</span>
                    <span id="page-state-badge" class="badge {{ $isEnabled ? 'badge-emerald' : ($hasPublished ? 'badge-amber' : 'badge-slate') }}">{{ $isEnabled ? 'Builder active' : ($hasPublished ? 'Theme homepage active' : 'Not published') }}</span>
                </div>
                <p id="page-state-copy" class="mt-1.5 text-[10px] leading-4 text-[#687771]">
                    @if($isEnabled)
                        Your builder layout is live on the storefront.
                    @elseif($hasPublished)
                        Your published layout is saved. The theme homepage is live.
                    @else
                        Your theme homepage is still live. Publish this layout when it is ready to replace it.
                    @endif
                </p>
                <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1.5">
                    <button type="button" id="disable-btn" class="builder-text-btn {{ $isEnabled ? '' : 'hidden' }}">Use theme homepage</button>
                    <button type="button" id="discard-btn" class="builder-text-btn">Discard draft</button>
                    <button type="button" id="reset-btn" class="builder-text-btn">Starter sections</button>
                    <a href="{{ route('admin.theme.index') }}" class="builder-text-btn">Theme colours &amp; fonts →</a>
                </div>
            </div>

            <div id="settings-empty" class="builder-panel-body">
                <div class="rounded-2xl border border-[#e3e9e5] bg-white p-4">
                    <div class="text-[10px] font-bold uppercase tracking-[.12em] text-[#8a9892]">How it works</div>
                    <ol class="mt-3 space-y-3 text-[11px] leading-5 text-[#687771]">
                        <li class="flex gap-2"><span class="grid h-5 w-5 shrink-0 place-items-center rounded-full bg-[#e7f5f0] text-[9px] font-bold text-[#0f766e]">1</span> Drag a section from the left onto the page, or click it to add at the end.</li>
                        <li class="flex gap-2"><span class="grid h-5 w-5 shrink-0 place-items-center rounded-full bg-[#e7f5f0] text-[9px] font-bold text-[#0f766e]">2</span> Click a section to change its content, spacing and background here.</li>
                        <li class="flex gap-2"><span class="grid h-5 w-5 shrink-0 place-items-center rounded-full bg-[#e7f5f0] text-[9px] font-bold text-[#0f766e]">3</span> Publish when it feels right. Drafts stay private until then.</li>
                    </ol>
                </div>
            </div>

            <div id="settings-editor" class="hidden">
                <div class="builder-settings-heading">
                    <div class="min-w-0">
                        <div id="selected-block-type" class="text-[9px] font-bold uppercase tracking-[.14em] text-[#0f766e]">Section</div>
                        <div id="selected-block-label" class="truncate text-sm font-semibold text-[#17231f]">Section</div>
                    </div>
                    <div class="flex shrink-0 items-center gap-1">
                        <button type="button" class="builder-icon-btn" data-block-action="up" title="Move up" aria-label="Move section up">
                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m6 15 6-6 6 6"/></svg>
                        </button>
                        <button type="button" class="builder-icon-btn" data-block-action="down" title="Move down" aria-label="Move section down">
                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
                        </button>
                        <button type="button" class="builder-icon-btn" data-block-action="duplicate" title="Duplicate" aria-label="Duplicate section">
                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><rect x="8" y="8" width="12" height="12" rx="1.5"/><path d="M16 8V4H4v12h4"/></svg>
                        </button>
                        <button type="button" class="builder-icon-btn text-rose-600" data-block-action="delete" title="Delete" aria-label="Delete section">
                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3"/></svg>
                        </button>
                    </div>
                </div>

                <div class="builder-panel-body">
                    <div id="block-fields" class="space-y-4"></div>

                    <details class="mt-6 border-t border-[#edf2ef] pt-4" open>
                        <summary class="cursor-pointer text-[10px] font-bold uppercase tracking-[.14em] text-[#8a9892]">Spacing &amp; background</summary>
                        <div id="block-style" class="mt-4 space-y-4"></div>
                    </details>

                    <div class="mt-6 rounded-xl border border-dashed border-[#dfe7e2] p-3 text-[10px] leading-4 text-[#8a9892]">
                        Some text can be edited directly on the page — click it in the preview. Changes save automatically.
                    </div>
                </div>
            </div>
        </aside>
    </div>

    <div id="builder-toast" class="builder-toast" role="status" aria-live="polite"></div>

    <script type="application/json" id="builder-state">@json($builderState, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT)</script>
</body>
</html>
