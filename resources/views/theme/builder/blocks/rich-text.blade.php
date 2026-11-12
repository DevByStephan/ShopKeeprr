@if(filled($settings['eyebrow'] ?? ''))
    <div class="text-[10px] font-bold uppercase tracking-[.18em] text-[var(--link)]">
        <span data-builder-text="eyebrow">{{ $settings['eyebrow'] }}</span>
    </div>
@endif

<h2 class="mt-3 font-display text-3xl tracking-tight sm:text-4xl">
    <span data-builder-text="heading">{{ $settings['heading'] }}</span>
</h2>

<div class="builder-prose mt-6 text-[15px] leading-8 text-[var(--muted)]">
    {!! nexus_page_content((string) ($settings['body'] ?? '')) !!}
</div>
