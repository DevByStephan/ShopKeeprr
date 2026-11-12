@php
    $image = nexus_builder_image($settings['image'] ?? '');
    $layout = $settings['layout'] ?? 'split';
    $tone = $settings['tone'] ?? 'surface';
    $panel = match ($tone) {
        'light' => 'border border-[var(--line)] bg-[var(--card)]',
        'brand' => 'bg-[var(--brand-soft)]',
        'dark' => 'bg-[var(--ink-strong)] text-white',
        default => 'bg-[var(--surface-2)]',
    };
    $muted = $tone === 'dark' ? 'text-white/65' : 'text-[var(--muted)]';
    $centered = $layout === 'centered';
    $imageFirst = $layout === 'image-first';
@endphp

<div class="overflow-hidden rounded-[30px] {{ $panel }} px-6 py-10 sm:px-10 sm:py-14 lg:px-14 lg:py-16">
    <div class="grid items-center gap-10 {{ $centered ? '' : 'lg:grid-cols-[1.04fr_.96fr]' }}">
        <div class="{{ $centered ? 'mx-auto max-w-2xl text-center' : 'max-w-xl' }} {{ $imageFirst ? 'lg:order-2' : '' }}">
            @if(filled($settings['eyebrow'] ?? ''))
                <div class="inline-flex items-center gap-2 rounded-full border {{ $tone === 'dark' ? 'border-white/15 bg-white/10' : 'border-[var(--line)] bg-white/65' }} px-3 py-1.5 text-[10px] font-bold uppercase tracking-[.18em] {{ $tone === 'dark' ? 'text-white/80' : 'text-[var(--link)]' }}">
                    <span class="h-1.5 w-1.5 rounded-full bg-[var(--brand)]"></span>
                    <span data-builder-text="eyebrow">{{ $settings['eyebrow'] }}</span>
                </div>
            @endif

            <h1 class="{{ $centered ? 'mx-auto' : '' }} mt-5 font-display text-[42px] leading-[1.02] tracking-[-.025em] sm:text-5xl lg:text-[58px]">
                <span data-builder-text="heading">{!! nl2br(e($settings['heading'] ?? '')) !!}</span>
            </h1>

            @if(filled($settings['subheading'] ?? ''))
                <p class="mt-5 max-w-lg text-[15px] leading-7 {{ $muted }} {{ $centered ? 'mx-auto' : '' }}">
                    <span data-builder-text="subheading">{{ $settings['subheading'] }}</span>
                </p>
            @endif

            <div class="mt-8 flex flex-wrap items-center gap-3 {{ $centered ? 'justify-center' : '' }}">
                @if(filled($settings['primary_label'] ?? ''))
                    <a href="{{ nexus_builder_link($settings['primary_url'] ?? '') }}" class="btn-primary">{{ $settings['primary_label'] }}</a>
                @endif
                @if(filled($settings['secondary_label'] ?? ''))
                    <a href="{{ nexus_builder_link($settings['secondary_url'] ?? '') }}" class="{{ $tone === 'dark' ? 'btn bg-white/10 text-white hover:bg-white/15' : 'btn-secondary' }}">{{ $settings['secondary_label'] }}</a>
                @endif
            </div>

            @if(filled($settings['note'] ?? ''))
                <div class="mt-8 flex flex-wrap gap-x-5 gap-y-2 text-[11px] font-semibold uppercase tracking-[.1em] {{ $tone === 'dark' ? 'text-white/55' : 'text-[#77867f]' }}">
                    <span class="flex items-center gap-2"><span class="h-1.5 w-1.5 rounded-full bg-[var(--brand)]"></span><span data-builder-text="note">{{ $settings['note'] }}</span></span>
                </div>
            @endif
        </div>

        <div class="relative {{ $imageFirst ? 'lg:order-1' : '' }} {{ $centered ? 'mt-2' : '' }}">
            <div class="absolute -left-5 -top-5 h-32 w-32 rounded-full bg-[var(--accent)]/20 blur-2xl"></div>
            <div class="relative overflow-hidden rounded-[24px] border {{ $tone === 'dark' ? 'border-white/10' : 'border-white/70' }} bg-[var(--card)] shadow-[0_30px_70px_-38px_rgba(16,37,34,.55)]">
                @if($image)
                    <img src="{{ $image }}" alt="{{ $settings['image_alt'] ?? '' }}"
                         class="{{ $centered ? 'aspect-[16/8]' : 'aspect-[4/5]' }} w-full object-cover">
                @else
                    <div class="{{ $centered ? 'aspect-[16/8]' : 'aspect-[4/5]' }} relative flex items-center justify-center overflow-hidden bg-[var(--brand-soft)]">
                        <div class="absolute -left-10 top-10 h-40 w-40 rounded-full border-[18px] border-[var(--brand)]/20"></div>
                        <div class="absolute -bottom-12 -right-8 h-48 w-48 rounded-full bg-[var(--accent)]/25"></div>
                        <div class="relative text-center">
                            <div class="font-display text-4xl text-[var(--link)]">{{ mb_substr(nexus_setting('store.name', 'ShopKeeprr'), 0, 1) }}</div>
                            <div class="mt-2 text-[10px] font-bold uppercase tracking-[.2em] text-[var(--muted)]">{{ nexus_setting('store.name', 'ShopKeeprr') }}</div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
