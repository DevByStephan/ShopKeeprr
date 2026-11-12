@php
    $endsAt = $data['ends_at'] ?? null;
    $expired = ! $endsAt || $endsAt->isPast();
@endphp

<div class="rounded-[28px] border border-[var(--line)] bg-[var(--card)] px-6 py-10 text-center sm:px-10">
    @if(filled($settings['eyebrow'] ?? ''))
        <div class="text-[10px] font-bold uppercase tracking-[.18em] text-[var(--link)]">
            <span data-builder-text="eyebrow">{{ $settings['eyebrow'] }}</span>
        </div>
    @endif
    <h2 class="mx-auto mt-3 max-w-2xl font-display text-3xl leading-tight tracking-tight sm:text-4xl">
        <span data-builder-text="heading">{{ $settings['heading'] }}</span>
    </h2>
    @if(filled($settings['copy'] ?? ''))
        <p class="mx-auto mt-3 max-w-xl text-sm leading-7 text-[var(--muted)]">
            <span data-builder-text="copy">{{ $settings['copy'] }}</span>
        </p>
    @endif

    @if($expired)
        <p class="mt-7 text-sm font-semibold text-[var(--success)]">
            <span data-builder-text="expired_text">{{ $settings['expired_text'] ?? 'This offer has ended.' }}</span>
        </p>
    @else
        <div class="mx-auto mt-7 max-w-lg" x-data="countdownBanner({{ $endsAt->getTimestamp() }})" x-init="boot()">
            <div x-show="! expired" class="grid grid-cols-4 gap-2 sm:gap-3">
                @foreach(['days' => 'Days', 'hours' => 'Hours', 'minutes' => 'Mins', 'seconds' => 'Secs'] as $key => $label)
                    <div class="rounded-2xl border border-[var(--line)] bg-[var(--surface)] px-2 py-3">
                        <div class="font-display text-2xl tabular-nums sm:text-3xl" x-text="parts.{{ $key }}">00</div>
                        <div class="mt-1 text-[9px] font-bold uppercase tracking-[.14em] text-[var(--muted)]">{{ $label }}</div>
                    </div>
                @endforeach
            </div>
            <p x-show="expired" x-cloak class="text-sm font-semibold text-[var(--success)]">{{ $settings['expired_text'] ?? 'This offer has ended.' }}</p>
        </div>
    @endif

    @if(filled($settings['button_label'] ?? ''))
        <a href="{{ nexus_builder_link($settings['button_url'] ?? '') }}" class="btn-primary mt-7">{{ $settings['button_label'] }}</a>
    @endif
</div>
