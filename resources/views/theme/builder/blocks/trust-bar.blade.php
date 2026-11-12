@php
    $items = $data['items'] ?? [];
    $columns = match ((string) ($settings['columns'] ?? '4')) {
        '2' => 'sm:grid-cols-2',
        '3' => 'sm:grid-cols-3',
        default => 'sm:grid-cols-2 lg:grid-cols-4',
    };
@endphp

@if($items !== [])
    <div class="grid gap-5 {{ $columns }}">
        @foreach($items as $item)
            <div class="flex gap-3">
                <span class="mt-0.5 grid h-8 w-8 shrink-0 place-items-center rounded-full bg-[var(--brand-soft)] text-[var(--link)]">
                    @if($loop->index % 3 === 0)
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.9" viewBox="0 0 24 24"><path d="M3 7h11v9H3zM14 11h4l3 3v2h-7z"/><circle cx="7" cy="18" r="1.5"/><circle cx="18" cy="18" r="1.5"/></svg>
                    @elseif($loop->index % 3 === 1)
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.9" viewBox="0 0 24 24"><path d="M20 12a8 8 0 1 1-2.3-5.7M20 4v5h-5"/></svg>
                    @else
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.9" viewBox="0 0 24 24"><path d="M12 3 5 6v5c0 4.4 2.9 8.4 7 10 4.1-1.6 7-5.6 7-10V6l-7-3Z"/><path d="m9 12 2 2 4-4"/></svg>
                    @endif
                </span>
                <div>
                    <div class="text-[13px] font-bold">{{ $item['title'] }}</div>
                    <p class="mt-1 text-xs leading-5 text-[var(--muted)]">{{ $item['copy'] }}</p>
                </div>
            </div>
        @endforeach
    </div>
@endif
