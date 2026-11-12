@php
    $products = $data['products'] ?? collect();
    $columns = match ((string) ($settings['columns'] ?? '4')) {
        '2' => 'sm:grid-cols-2',
        '3' => 'sm:grid-cols-2 lg:grid-cols-3',
        default => 'sm:grid-cols-2 lg:grid-cols-4',
    };
@endphp

@if($products->isEmpty())
    @if($builderPreview ?? false)
        <div class="rounded-[26px] border border-dashed border-[var(--line)] bg-[var(--card)] px-6 py-14 text-center">
            <div class="font-display text-2xl">No products to show yet</div>
            <p class="mt-2 text-sm leading-6 text-[var(--muted)]">Add active products in the catalog, then choose a different source or publish them.</p>
        </div>
    @endif
@else
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
            @if(filled($settings['eyebrow'] ?? ''))
                <div class="text-[10px] font-bold uppercase tracking-[.18em] text-[var(--link)]">
                    <span data-builder-text="eyebrow">{{ $settings['eyebrow'] }}</span>
                </div>
            @endif
            <h2 class="mt-2 font-display text-3xl tracking-tight">
                <span data-builder-text="heading">{{ $settings['heading'] }}</span>
            </h2>
            @if(filled($settings['copy'] ?? ''))
                <p class="mt-3 max-w-xl text-sm leading-6 text-[var(--muted)]">
                    <span data-builder-text="copy">{{ $settings['copy'] }}</span>
                </p>
            @endif
        </div>
        @if(filled($settings['cta_label'] ?? ''))
            <a href="{{ nexus_builder_link($settings['cta_url'] ?? '') }}" class="shrink-0 text-xs font-bold uppercase tracking-[.1em] text-[var(--muted)] transition hover:text-[var(--link)]">{{ $settings['cta_label'] }} →</a>
        @endif
    </div>

    <div class="mt-7 grid grid-cols-2 gap-x-5 gap-y-9 {{ $columns }}">
        @foreach($products as $product)
            @include(nexus_partial('partials.product-card'), ['product' => $product])
        @endforeach
    </div>
@endif
