@php
    $slides = $data['slides'] ?? [];
    $height = match ($settings['height'] ?? 'tall') {
        'short' => 'min-h-[320px] sm:min-h-[360px]',
        'medium' => 'min-h-[400px] sm:min-h-[460px]',
        default => 'min-h-[470px] sm:min-h-[560px]',
    };
@endphp

@if($slides === [])
    <div class="rounded-[26px] border border-dashed border-[var(--line)] bg-[var(--surface-2)] px-6 py-16 text-center">
        <div class="font-display text-2xl">Add your first slide</div>
        <p class="mt-2 text-sm text-[var(--muted)]">Use the settings panel to add an image, headline and button for each slide.</p>
    </div>
@else
    <div class="relative overflow-hidden rounded-[26px] bg-[var(--ink-strong)] {{ $height }}"
         x-data="storefrontCarousel(@js($slides), {{ ! empty($settings['autoplay']) ? 'true' : 'false' }})"
         x-init="boot()">
        @foreach($slides as $index => $slide)
            @php($slideImage = nexus_builder_image($slide['image'] ?? ''))
            <div x-show="index === {{ $index }}" x-transition.opacity.duration.700ms class="absolute inset-0">
                @if($slideImage)
                    <img src="{{ $slideImage }}" alt="" class="absolute inset-0 h-full w-full object-cover">
                @endif
                <div class="absolute inset-0 bg-gradient-to-t from-[#0b1d19]/90 via-[#0b1d19]/45 to-transparent"></div>
                <div class="relative flex h-full items-end">
                    <div class="w-full px-6 pb-12 pt-20 sm:px-10 sm:pb-14 lg:px-14">
                        <div class="max-w-2xl">
                            @if(filled($slide['eyebrow'] ?? ''))
                                <div class="inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/10 px-3 py-1.5 text-[10px] font-bold uppercase tracking-[.18em] text-white/80 backdrop-blur">
                                    <span class="h-1.5 w-1.5 rounded-full bg-white"></span>
                                    {{ $slide['eyebrow'] }}
                                </div>
                            @endif
                            @if(filled($slide['heading'] ?? ''))
                                <h2 class="mt-4 max-w-xl font-display text-4xl leading-[1.04] text-white sm:text-5xl">{{ $slide['heading'] }}</h2>
                            @endif
                            @if(filled($slide['copy'] ?? ''))
                                <p class="mt-4 max-w-lg text-sm leading-7 text-white/65">{{ $slide['copy'] }}</p>
                            @endif
                            @if(filled($slide['label'] ?? ''))
                                <a href="{{ nexus_builder_link($slide['url'] ?? '') }}" class="btn-primary btn-sm mt-6">{{ $slide['label'] }}</a>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @endforeach

        @if(count($slides) > 1)
            <div class="absolute right-5 top-5 flex gap-2">
                <button type="button" class="grid h-9 w-9 place-items-center rounded-full border border-white/25 bg-black/20 text-white backdrop-blur transition hover:bg-black/35" @click="prev()" aria-label="Previous slide">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m15 6-6 6 6 6"/></svg>
                </button>
                <button type="button" class="grid h-9 w-9 place-items-center rounded-full border border-white/25 bg-black/20 text-white backdrop-blur transition hover:bg-black/35" @click="next()" aria-label="Next slide">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m9 6 6 6-6 6"/></svg>
                </button>
            </div>
            <div class="absolute bottom-5 right-6 flex items-center gap-1.5">
                @foreach($slides as $index => $slide)
                    <button type="button" class="h-1.5 rounded-full transition-all" :class="index === {{ $index }} ? 'w-7 bg-white' : 'w-1.5 bg-white/40'" @click="go({{ $index }})" aria-label="Go to slide {{ $index + 1 }}"></button>
                @endforeach
            </div>
        @endif
    </div>
@endif
