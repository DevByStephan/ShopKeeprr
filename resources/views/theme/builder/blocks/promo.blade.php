@php
    $image = nexus_builder_image($settings['image'] ?? '');
    $imageRight = ($settings['layout'] ?? 'image-left') === 'image-right';
@endphp

<div class="overflow-hidden rounded-[28px] border border-[var(--line)] bg-[var(--card)]">
    <div class="grid items-stretch lg:grid-cols-2">
        <div class="relative min-h-[280px] overflow-hidden bg-[var(--surface-2)] {{ $imageRight ? 'lg:order-2' : '' }}">
            @if($image)
                <img src="{{ $image }}" alt="{{ $settings['image_alt'] ?? '' }}" class="absolute inset-0 h-full w-full object-cover" loading="lazy">
            @else
                <div class="absolute inset-0 flex items-center justify-center bg-[var(--brand-soft)]">
                    <div class="absolute -left-8 top-8 h-40 w-40 rounded-full border-[18px] border-[var(--brand)]/15"></div>
                    <div class="absolute -bottom-10 right-6 h-44 w-44 rounded-full bg-[var(--accent)]/20"></div>
                    <div class="relative font-display text-5xl text-[var(--link)]">{{ mb_substr(nexus_setting('store.name', 'ShopKeeprr'), 0, 1) }}</div>
                </div>
            @endif
        </div>

        <div class="flex items-center px-6 py-10 sm:px-10 lg:px-12">
            <div class="max-w-lg">
                @if(filled($settings['eyebrow'] ?? ''))
                    <div class="text-[10px] font-bold uppercase tracking-[.18em] text-[var(--link)]">
                        <span data-builder-text="eyebrow">{{ $settings['eyebrow'] }}</span>
                    </div>
                @endif
                <h2 class="mt-3 font-display text-3xl leading-tight tracking-tight sm:text-4xl">
                    <span data-builder-text="heading">{!! nl2br(e($settings['heading'] ?? '')) !!}</span>
                </h2>
                @if(filled($settings['copy'] ?? ''))
                    <p class="mt-4 text-sm leading-7 text-[var(--muted)]">
                        <span data-builder-text="copy">{{ $settings['copy'] }}</span>
                    </p>
                @endif
                @if(filled($settings['button_label'] ?? ''))
                    <a href="{{ nexus_builder_link($settings['button_url'] ?? '') }}" class="btn-secondary mt-6">{{ $settings['button_label'] }}</a>
                @endif
            </div>
        </div>
    </div>
</div>
