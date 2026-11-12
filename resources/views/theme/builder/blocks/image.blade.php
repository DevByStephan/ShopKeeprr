@php
    $image = nexus_builder_image($settings['image'] ?? '');
    $ratio = match ($settings['ratio'] ?? 'wide') {
        'square' => 'aspect-square',
        'portrait' => 'aspect-[4/5]',
        'natural' => '',
        default => 'aspect-[16/9]',
    };
    $rounded = ! empty($settings['rounded']) ? 'rounded-[26px]' : 'rounded-none';
@endphp

@if($image)
    <figure>
        <div class="overflow-hidden {{ $rounded }} bg-[var(--surface-2)]">
            @if(filled($settings['link'] ?? ''))
                <a href="{{ nexus_builder_link($settings['link']) }}" class="block">
                    <img src="{{ $image }}" alt="{{ $settings['alt'] ?? '' }}" class="{{ $ratio }} w-full object-cover" loading="lazy">
                </a>
            @else
                <img src="{{ $image }}" alt="{{ $settings['alt'] ?? '' }}" class="{{ $ratio }} w-full object-cover" loading="lazy">
            @endif
        </div>
        @if(filled($settings['caption'] ?? ''))
            <figcaption class="mt-3 text-xs leading-5 text-[var(--muted)]">
                <span data-builder-text="caption">{{ $settings['caption'] }}</span>
            </figcaption>
        @endif
    </figure>
@elseif($builderPreview ?? false)
    <div class="flex {{ $ratio }} items-center justify-center rounded-[26px] border border-dashed border-[var(--line)] bg-[var(--surface-2)] text-sm text-[var(--muted)]">
        Choose an image in the settings panel.
    </div>
@endif
