@php
    $categories = $data['categories'] ?? collect();
    $grid = match ($categories->count()) {
        2 => 'lg:grid-cols-2',
        3 => 'lg:grid-cols-3',
        default => 'lg:grid-cols-4',
    };
@endphp

@if($categories->isEmpty())
    @if($builderPreview ?? false)
        <div class="rounded-[26px] border border-dashed border-[var(--line)] bg-[var(--card)] px-6 py-14 text-center">
            <div class="font-display text-2xl">No categories yet</div>
            <p class="mt-2 text-sm leading-6 text-[var(--muted)]">Create active categories and their tiles will appear here automatically.</p>
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
        </div>
        @if(filled($settings['cta_label'] ?? ''))
            <a href="{{ nexus_builder_link($settings['cta_url'] ?? '') }}" class="shrink-0 text-xs font-bold uppercase tracking-[.1em] text-[var(--muted)] transition hover:text-[var(--link)]">{{ $settings['cta_label'] }} →</a>
        @endif
    </div>

    <div class="mt-6 grid gap-4 sm:grid-cols-2 {{ $grid }}">
        @foreach($categories as $category)
            <a href="{{ route('storefront.category', $category->slug) }}" class="group relative flex h-40 items-end overflow-hidden rounded-[20px] border border-[var(--line)] bg-[var(--surface-2)] p-5 transition duration-300 hover:-translate-y-0.5 hover:shadow-xl">
                @if($category->imageUrl())
                    <img src="{{ $category->imageUrl() }}" alt="{{ $category->name }}" class="absolute inset-0 h-full w-full object-cover opacity-90 transition duration-700 group-hover:scale-105" loading="lazy">
                    <div class="absolute inset-0 bg-gradient-to-t from-[#0b1d19]/75 via-[#0b1d19]/10 to-transparent"></div>
                @else
                    <div class="absolute -right-6 -top-8 h-32 w-32 rounded-full border-[16px] border-[var(--brand)]/10"></div>
                    <div class="absolute -bottom-10 right-10 h-28 w-28 rounded-full bg-[var(--accent)]/10"></div>
                @endif
                <div class="relative">
                    <div class="text-[10px] font-bold uppercase tracking-[.14em] {{ $category->imageUrl() ? 'text-white/65' : 'text-[var(--muted)]' }}">{{ $category->products_count }} {{ \Illuminate\Support\Str::plural('product', $category->products_count) }}</div>
                    <div class="mt-1 font-display text-xl {{ $category->imageUrl() ? 'text-white' : 'text-[var(--ink)]' }}">{{ $category->name }}</div>
                </div>
                <span class="absolute right-4 top-4 grid h-8 w-8 place-items-center rounded-full {{ $category->imageUrl() ? 'bg-white/15 text-white' : 'bg-white text-[var(--link)]' }} opacity-0 backdrop-blur transition group-hover:opacity-100" aria-hidden="true">→</span>
            </a>
        @endforeach
    </div>
@endif
