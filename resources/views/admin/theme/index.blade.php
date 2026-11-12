@extends('admin.layouts.app')

@section('title', 'Theme')
@section('eyebrow', 'Storefront')
@section('heading', 'Theme & templates')

@section('content')
<div class="space-y-5">
    <section class="overflow-hidden rounded-2xl border border-[#1f3a34] bg-[#102522] p-5 text-white sm:p-6">
        <div class="flex flex-wrap items-center justify-between gap-5">
            <div class="max-w-2xl">
                <div class="text-[10px] font-bold uppercase tracking-[.18em] text-[#7fd8c9]">No code needed</div>
                <h2 class="mt-2 font-display text-2xl">Build the homepage with the visual editor.</h2>
                <p class="mt-2 text-xs leading-5 text-white/55">Drag in a hero, carousel, products, categories, testimonials or a countdown. Edit text directly on the page and preview desktop or mobile before publishing. Your theme still supplies the colours, fonts and product cards.</p>
            </div>
            <a href="{{ route('admin.builder.index') }}" class="btn-primary">Open storefront builder →</a>
        </div>
    </section>

    <section class="card card-pad">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <div class="text-[10px] font-bold uppercase tracking-[.14em] text-[#0f766e]">Included looks</div>
                <h2 class="mt-1 font-display text-2xl">Choose a storefront theme</h2>
                <p class="mt-1 max-w-2xl text-xs leading-5 text-[#687771]">Every theme ships with a complete layout, a colour-checked palette and matching header, footer and product cards. Switching is instant and does not touch products, orders or customers.</p>
            </div>
            <span class="badge badge-emerald">{{ count($presets) }} themes included</span>
        </div>

        <div class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            @foreach($presets as $key => $preset)
                @php($palette = $preset['palette'])
                <article class="flex flex-col overflow-hidden rounded-2xl border {{ $key === $activePreset ? 'border-[#0f766e] ring-4 ring-[#0f766e]/10' : 'border-[#e3e9e5]' }} bg-white">
                    <div class="relative overflow-hidden border-b border-[#e9efeb] p-3" style="background: {{ $palette['surface'] }}">
                        <div class="overflow-hidden rounded-lg shadow-sm" style="background: {{ $palette['card'] }}">
                            <div class="flex items-center gap-1.5 px-2.5 py-2" style="background: {{ $palette['header-bg'] }}">
                                <span class="h-2.5 w-2.5 rounded-full" style="background: {{ $palette['brand'] }}"></span>
                                <span class="h-1.5 w-10 rounded-full" style="background: {{ $palette['header-ink'] }}; opacity:.8"></span>
                                <span class="ml-auto h-4 w-14 rounded" style="background: {{ $palette['accent'] }}"></span>
                            </div>
                            <div class="p-2">
                                <div class="relative overflow-hidden rounded p-2.5" style="background: {{ $key === 'editorial' ? $palette['surface-2'] : $palette['header-bg'] }}">
                                    <div class="h-1.5 w-12 rounded-full" style="background: {{ $key === 'editorial' ? $palette['brand'] : $palette['accent'] }}"></div>
                                    <div class="mt-1.5 h-2 w-20 rounded-full" style="background: {{ $key === 'editorial' ? $palette['ink'] : '#ffffff' }}; opacity:.85"></div>
                                    <div class="mt-1 h-1.5 w-14 rounded-full" style="background: {{ $key === 'editorial' ? $palette['muted'] : '#ffffff' }}; opacity:.45"></div>
                                    <div class="mt-2 h-3 w-9 rounded" style="background: {{ $palette['brand'] }}"></div>
                                </div>
                                <div class="mt-1.5 grid grid-cols-3 gap-1">
                                    @for($i = 0; $i < 3; $i++)
                                        <div class="overflow-hidden rounded" style="background: {{ $palette['surface-2'] }}">
                                            <div class="aspect-square" style="background: {{ $palette['line'] }}"></div>
                                            <div class="space-y-1 p-1">
                                                <div class="h-1 rounded-full" style="background: {{ $palette['ink'] }}; opacity:.35"></div>
                                                <div class="h-1 w-2/3 rounded-full" style="background: {{ $palette['price'] }}; opacity:.8"></div>
                                            </div>
                                        </div>
                                    @endfor
                                </div>
                            </div>
                        </div>
                        @if($key === $activePreset)
                            <span class="absolute right-2 top-2 rounded-full bg-[#0f766e] px-2 py-1 text-[9px] font-bold uppercase tracking-wide text-white">Active</span>
                        @endif
                    </div>

                    <div class="flex flex-1 flex-col p-4">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <h3 class="text-sm font-bold text-[#17231f]">{{ $preset['label'] }}</h3>
                                <div class="text-[10px] font-semibold uppercase tracking-[.1em] text-[#0f766e]">{{ $preset['tagline'] }}</div>
                            </div>
                            <div class="flex shrink-0 -space-x-1">
                                @foreach(array_slice($preset['preview'], 0, 4) as $swatch)
                                    <span class="h-3.5 w-3.5 rounded-full border border-white shadow-sm" style="background: {{ $swatch }}"></span>
                                @endforeach
                            </div>
                        </div>
                        <p class="mt-2 text-[11px] leading-5 text-[#687771]">{{ $preset['description'] }}</p>
                        <div class="mt-2 text-[10px] leading-4 text-[#8a9892]"><span class="font-semibold text-[#5d6d66]">Best for:</span> {{ $preset['best_for'] }}</div>

                        <div class="mt-auto pt-4">
                            @if($key === $activePreset)
                                <button type="button" disabled class="w-full rounded-lg border border-[#dce5e0] bg-[#f5f8f6] px-3 py-2 text-xs font-bold text-[#8a9892]">Currently active</button>
                            @else
                                <form method="post" action="{{ route('admin.theme.activate') }}">
                                    @csrf
                                    <input type="hidden" name="preset" value="{{ $key }}">
                                    <button class="w-full rounded-lg bg-[#0f766e] px-3 py-2 text-xs font-bold text-white transition hover:bg-[#0b5c56]">Use this theme</button>
                                </form>
                            @endif
                        </div>
                    </div>
                </article>
            @endforeach
        </div>

        <div class="mt-4 rounded-xl border border-[#e3e9e5] bg-[#fafcfb] px-4 py-3 text-[11px] leading-5 text-[#687771]">
            <strong class="text-[#17231f]">How switching works:</strong> activating a theme applies its curated button, accent, heading and surface colours. Your custom CSS and any template overrides you have saved are kept. Fine-tune colours and fonts in Design tokens below.
        </div>
    </section>

    <div class="grid gap-5 xl:grid-cols-[1.4fr_1fr]">
        <div class="space-y-5">
            <form method="post" action="{{ route('admin.theme.update') }}" class="card card-pad">
                @csrf
                @method('PATCH')
                <input type="hidden" name="preset" value="{{ $activePreset }}">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <h2 class="font-display text-xl">Design tokens</h2>
                        <p class="mt-1 text-[11px] text-[#687771]">Fine-tune the active theme. The layout stays the same; these values drive its colour and type system.</p>
                    </div>
                    <span class="badge badge-slate">{{ $presets[$activePreset]['label'] ?? 'Editorial' }}</span>
                </div>

                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="label" for="primary_color">Primary action colour</label>
                        <div class="flex items-center gap-2">
                            <input id="primary_color" type="color" name="primary_color" value="{{ $settings['theme.primary_color'] }}" class="h-10 w-12 rounded-lg border border-[#dce5e0] bg-white p-1">
                            <input value="{{ $settings['theme.primary_color'] }}" class="input font-mono text-xs" readonly>
                        </div>
                    </div>
                    <div>
                        <label class="label" for="accent_color">Accent colour</label>
                        <div class="flex items-center gap-2">
                            <input id="accent_color" type="color" name="accent_color" value="{{ $settings['theme.accent_color'] }}" class="h-10 w-12 rounded-lg border border-[#dce5e0] bg-white p-1">
                            <input value="{{ $settings['theme.accent_color'] }}" class="input font-mono text-xs" readonly>
                        </div>
                    </div>
                    <div>
                        <label class="label" for="heading_font">Heading font</label>
                        <select id="heading_font" name="heading_font" class="select">@foreach(['Fraunces','DM Serif Display','Playfair Display','Georgia','Inter'] as $font)<option value="{{ $font }}" @selected($settings['theme.heading_font'] === $font)>{{ $font }}</option>@endforeach</select>
                    </div>
                    <div>
                        <label class="label" for="body_font">Body font</label>
                        <select id="body_font" name="body_font" class="select">@foreach(['Inter','DM Sans','Manrope','System UI','Georgia'] as $font)<option value="{{ $font }}" @selected($settings['theme.body_font'] === $font)>{{ $font }}</option>@endforeach</select>
                    </div>
                    <div>
                        <label class="label" for="product_columns">Products per row</label>
                        <select id="product_columns" name="product_columns" class="select">@foreach([2,3,4] as $columns)<option value="{{ $columns }}" @selected((int) $settings['theme.product_columns'] === $columns)>{{ $columns }} columns</option>@endforeach</select>
                    </div>
                </div>
                <div class="mt-4">
                    <label class="label" for="custom_css">Custom CSS</label>
                    <textarea id="custom_css" name="custom_css" rows="8" class="textarea font-mono text-xs">{{ $settings['theme.custom_css'] }}</textarea>
                    <p class="mt-1 text-[11px] text-[#8a9892]">Injected into the storefront head. Use it for small brand tweaks.</p>
                </div>
                <button class="btn-primary mt-5">Save theme</button>
            </form>

            <div class="card card-pad">
                <h2 class="font-display text-xl">Templates</h2>
                <p class="mt-1 text-xs text-[#687771]">Edit a copy of a storefront template. Your changes are stored in <code class="rounded bg-[#f5f8f6] px-1">resources/views/theme/custom</code> and can be reset at any time. Custom overrides take priority over the active theme.</p>
                <div class="mt-4 divide-y divide-[#edf2ef]">
                    @foreach($files as $file)
                        <div class="flex items-center justify-between gap-3 py-2.5">
                            <div class="flex items-center gap-2 text-sm">
                                <svg class="h-4 w-4 text-[#8a9892]" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path d="M6 3h9l3 3v15H6z"/><path d="M14 3v4h4"/></svg>
                                <span class="font-mono text-xs">{{ $file }}.blade.php</span>
                            </div>
                            <a href="{{ route('admin.theme.edit', $file) }}" class="text-xs font-bold text-[#0f766e]">Edit →</a>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="space-y-5">
            <div class="card overflow-hidden">
                <div class="flex items-center justify-between border-b border-[#e3e9e5] px-5 py-4">
                    <h2 class="font-display text-xl">Live palette</h2>
                    <a href="{{ route('storefront.home') }}" target="_blank" class="text-[11px] font-bold text-[#0f766e]">Open storefront ↗</a>
                </div>
                <div class="p-5" style="background: {{ $presets[$activePreset]['palette']['surface'] ?? '#f6f3ec' }}">
                    <div class="overflow-hidden rounded-2xl border" style="border-color: {{ $presets[$activePreset]['palette']['line'] ?? '#e3e9e5' }}; background: {{ $presets[$activePreset]['palette']['card'] ?? '#fff' }}">
                        <div class="flex items-center gap-2 px-4 py-3" style="background: {{ $presets[$activePreset]['palette']['header-bg'] ?? '#fff' }}; color: {{ $presets[$activePreset]['palette']['header-ink'] ?? '#17231f' }}">
                            <span class="grid h-6 w-6 place-items-center rounded-md text-[10px] font-bold" style="background: {{ $presets[$activePreset]['palette']['brand'] ?? '#0f766e' }}; color: {{ $presets[$activePreset]['palette']['button-ink'] ?? '#fff' }}">{{ mb_substr(nexus_setting('store.name', 'ShopKeeprr'), 0, 1) }}</span>
                            <span class="text-xs font-bold">{{ nexus_setting('store.name', 'ShopKeeprr') }}</span>
                            <span class="ml-auto h-5 w-16 rounded-full" style="background: {{ $presets[$activePreset]['palette']['surface-2'] ?? '#eef2ef' }}"></span>
                        </div>
                        <div class="p-4">
                            <div class="text-[10px] font-bold uppercase tracking-[.18em]" style="color: {{ $presets[$activePreset]['palette']['link'] ?? '#0f766e' }}">{{ nexus_setting('store.hero_eyebrow', 'The everyday edit') }}</div>
                            <div class="mt-2 text-2xl leading-tight" style="font-family: '{{ $settings['theme.heading_font'] }}', Georgia, serif; color: {{ $presets[$activePreset]['palette']['ink'] ?? '#17231f' }}">{{ nexus_setting('store.hero_title', 'Good design, made to last.') }}</div>
                            <p class="mt-2 text-xs leading-5" style="color: {{ $presets[$activePreset]['palette']['muted'] ?? '#687771' }}">{{ nexus_setting('store.hero_subtitle') }}</p>
                            <span class="mt-4 inline-block px-4 py-2 text-[11px] font-bold" style="background: {{ $settings['theme.primary_color'] }}; color: {{ $presets[$activePreset]['palette']['button-ink'] ?? '#fff' }}; border-radius: {{ $presets[$activePreset]['button_radius'] ?? '999px' }}">Shop the collection</span>
                        </div>
                    </div>
                    <div class="mt-4 grid grid-cols-3 gap-2">
                        @for($i = 0; $i < 3; $i++)
                            <div class="aspect-[4/5] rounded-xl border border-white/60 bg-white/70"></div>
                        @endfor
                    </div>
                </div>
            </div>

            <div class="card card-pad text-xs leading-5 text-[#687771]">
                <div class="text-[10px] font-bold uppercase tracking-[.14em] text-[#8a9892]">Safe editing</div>
                <p class="mt-2">Templates are compiled before they are saved, so a syntax error will be rejected instead of taking the storefront down. Backups of edited files are kept in <code class="rounded bg-[#f5f8f6] px-1">storage/app/theme-backups</code>.</p>
            </div>

            <div class="card card-pad">
                <div class="text-[10px] font-bold uppercase tracking-[.14em] text-[#8a9892]">Theme notes</div>
                <ul class="mt-2 space-y-2 text-xs leading-5 text-[#687771]">
                    <li class="flex gap-2"><span class="mt-1.5 h-1 w-1 shrink-0 rounded-full bg-[#0f766e]"></span> Themes are responsive and keyboard accessible.</li>
                    <li class="flex gap-2"><span class="mt-1.5 h-1 w-1 shrink-0 rounded-full bg-[#0f766e]"></span><span>Product cards and headers adapt automatically to B2B, subscription and retail modes.</span></li>
                    <li class="flex gap-2"><span class="mt-1.5 h-1 w-1 shrink-0 rounded-full bg-[#0f766e]"></span><span>More themes can be dropped into <code class="rounded bg-[#f5f8f6] px-1">resources/views/theme/presets</code> and registered in <code class="rounded bg-[#f5f8f6] px-1">config/themes.php</code>.</span></li>
                </ul>
            </div>
        </div>
    </div>
</div>
@endsection
