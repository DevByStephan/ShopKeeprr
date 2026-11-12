@php
    $tone = $settings['tone'] ?? 'brand';
    $classes = match ($tone) {
        'ink' => 'bg-[var(--ink-strong)] text-white',
        'soft' => 'bg-[var(--brand-soft)] text-[var(--ink)]',
        default => 'bg-[var(--brand)] text-[var(--button-ink)]',
    };
    $linkClass = $tone === 'brand' ? 'text-[var(--button-ink)]' : 'text-[var(--link)]';
@endphp

<div class="announcement-bar {{ $classes }} rounded-[var(--card-radius)] px-5 py-3 text-center text-xs font-semibold"
     @if(! empty($settings['dismissible'])) data-dismissible="1" @endif>
    <span data-builder-text="message">{{ $settings['message'] ?? '' }}</span>
    @if(! empty($settings['link_label']))
        <a href="{{ nexus_builder_link($settings['link_url'] ?? '') }}" class="ml-2 font-bold underline underline-offset-2 {{ $linkClass }}">
            {{ $settings['link_label'] }}
        </a>
    @endif
    @if(! empty($settings['dismissible']))
        <button type="button" class="ml-3 align-middle text-base leading-none opacity-60 hover:opacity-100" aria-label="Dismiss announcement" onclick="this.closest('.announcement-bar').remove()">&times;</button>
    @endif
</div>
