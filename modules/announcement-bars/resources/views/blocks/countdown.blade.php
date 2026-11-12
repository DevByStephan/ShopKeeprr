@php
    $endsAt = $settings['ends_at'] ?? '';
    $timestamp = $endsAt ? strtotime($endsAt) : null;
@endphp

<div class="rounded-[28px] border border-[var(--line)] bg-[var(--card)] px-6 py-9 text-center sm:px-10">
    @if(filled($settings['eyebrow'] ?? ''))
        <div class="text-[10px] font-bold uppercase tracking-[.18em] text-[var(--link)]">
            <span data-builder-text="eyebrow">{{ $settings['eyebrow'] }}</span>
        </div>
    @endif
    <h2 class="mx-auto mt-3 max-w-2xl font-display text-3xl leading-tight tracking-tight sm:text-4xl">
        <span data-builder-text="heading">{{ $settings['heading'] ?? '' }}</span>
    </h2>
    @if(filled($settings['copy'] ?? ''))
        <p class="mx-auto mt-3 max-w-xl text-sm leading-7 text-[var(--muted)]">
            <span data-builder-text="copy">{{ $settings['copy'] }}</span>
        </p>
    @endif

    @if($timestamp)
        <div class="mt-7 flex justify-center gap-2 sm:gap-3" data-countdown="{{ $timestamp }}" data-expired="Offer ended">
            @foreach(['Days', 'Hours', 'Minutes', 'Seconds'] as $label)
                <div class="min-w-[68px] rounded-xl border border-[var(--line)] bg-[var(--surface-2)] px-3 py-3 sm:min-w-[84px]">
                    <div class="font-display text-2xl tabular-nums sm:text-3xl" data-countdown-unit="{{ strtolower($label) }}">--</div>
                    <div class="mt-1 text-[9px] font-bold uppercase tracking-[.12em] text-[var(--muted)]">{{ $label }}</div>
                </div>
            @endforeach
        </div>
    @endif

    @if(filled($settings['button_label'] ?? ''))
        <a href="{{ nexus_builder_link($settings['button_url'] ?? '') }}" class="btn-primary mt-6">{{ $settings['button_label'] }}</a>
    @endif
</div>

@once
    @push('scripts')
        <script>
            (() => {
                const tick = () => {
                    document.querySelectorAll('[data-countdown]').forEach((node) => {
                        const end = Number(node.dataset.countdown) * 1000;
                        const left = Math.max(0, end - Date.now());
                        const parts = {
                            days: Math.floor(left / 86400000),
                            hours: Math.floor((left % 86400000) / 3600000),
                            minutes: Math.floor((left % 3600000) / 60000),
                            seconds: Math.floor((left % 60000) / 1000),
                        };
                        Object.entries(parts).forEach(([unit, value]) => {
                            const el = node.querySelector('[data-countdown-unit="' + unit + '"]');
                            if (el) el.textContent = String(value).padStart(2, '0');
                        });
                    });
                };
                tick();
                if (!window.__nexusCountdownTimer) {
                    window.__nexusCountdownTimer = setInterval(tick, 1000);
                }
            })();
        </script>
    @endpush
@endonce
