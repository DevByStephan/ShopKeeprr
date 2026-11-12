@php
    $quotes = $data['quotes'] ?? [];
    $columns = ($settings['columns'] ?? '3') === '2' ? 'sm:grid-cols-2' : 'sm:grid-cols-2 lg:grid-cols-3';
@endphp

@if($quotes === [])
    @if($builderPreview ?? false)
        <div class="rounded-[26px] border border-dashed border-[var(--line)] bg-[var(--card)] px-6 py-14 text-center">
            <div class="font-display text-2xl">No quotes yet</div>
            <p class="mt-2 text-sm leading-6 text-[var(--muted)]">Add one quote per line in the settings panel.</p>
        </div>
    @endif
@else
    <div class="mx-auto max-w-2xl text-center">
        @if(filled($settings['eyebrow'] ?? ''))
            <div class="text-[10px] font-bold uppercase tracking-[.18em] text-[var(--link)]">
                <span data-builder-text="eyebrow">{{ $settings['eyebrow'] }}</span>
            </div>
        @endif
        <h2 class="mt-3 font-display text-3xl tracking-tight sm:text-4xl">
            <span data-builder-text="heading">{{ $settings['heading'] }}</span>
        </h2>
    </div>

    <div class="mt-8 grid gap-4 {{ $columns }}">
        @foreach($quotes as $quote)
            <figure class="flex h-full flex-col rounded-[22px] border border-[var(--line)] bg-[var(--card)] p-6">
                <svg class="h-7 w-7 text-[var(--brand)]/35" fill="currentColor" viewBox="0 0 32 32" aria-hidden="true"><path d="M13 8v7H8c0 4.5 2 6.7 5 8l-2 3c-5-2.4-8-6.2-8-12V8h10Zm15 0v7h-5c0 4.5 2 6.7 5 8l-2 3c-5-2.4-8-6.2-8-12V8h10Z"/></svg>
                <blockquote class="mt-4 flex-1 text-[15px] leading-7 text-[var(--ink)]">{{ $quote['quote'] }}</blockquote>
                <figcaption class="mt-5 border-t border-[var(--line)] pt-4">
                    <div class="text-sm font-semibold">{{ $quote['name'] }}</div>
                    @if(filled($quote['detail']))
                        <div class="mt-0.5 text-[10px] font-bold uppercase tracking-[.12em] text-[var(--muted)]">{{ $quote['detail'] }}</div>
                    @endif
                </figcaption>
            </figure>
        @endforeach
    </div>
@endif
