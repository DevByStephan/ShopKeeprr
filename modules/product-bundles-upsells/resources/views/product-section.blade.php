@if(isset($bundles) && $bundles->isNotEmpty())
    <section class="mt-10 border-t border-[var(--line)] pt-8">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <div class="text-[10px] font-bold uppercase tracking-[.18em] text-[var(--link)]">Better together</div>
                <h2 class="mt-1 font-display text-3xl tracking-tight">Frequently bought together</h2>
            </div>
            <p class="text-[11px] text-[var(--muted)]">One click adds the complete set at the bundle price.</p>
        </div>

        <div class="mt-5 grid gap-4">
            @foreach($bundles as $bundle)
                <div class="rounded-2xl border border-[var(--line)] bg-white p-5">
                    <div class="grid gap-5 lg:grid-cols-[1fr_260px] lg:items-center">
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="font-display text-2xl leading-tight">{{ $bundle->name }}</h3>
                                <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-[9px] font-black uppercase tracking-[.12em] text-emerald-700">{{ $bundle->discountLabel() }}</span>
                                @unless($bundle->isAvailable())
                                    <span class="badge badge-rose">Out of stock</span>
                                @endunless
                            </div>
                            @if($bundle->description)<p class="mt-1 text-xs leading-5 text-[var(--muted)]">{{ $bundle->description }}</p>@endif
                            <div class="mt-4 flex flex-wrap items-center gap-3">
                                @foreach($bundle->products as $item)
                                    <div class="flex items-center gap-2">
                                        <div class="h-12 w-12 overflow-hidden rounded-lg border border-[var(--line)] bg-[var(--surface-2)]">
                                            @if($item->imageUrl())
                                                <img src="{{ $item->imageUrl() }}" alt="" class="h-full w-full object-cover">
                                            @else
                                                <img src="{{ asset('images/placeholder.svg') }}" alt="" class="h-full w-full object-cover">
                                            @endif
                                        </div>
                                        <div class="max-w-[150px]">
                                            <div class="truncate text-xs font-semibold">{{ $item->name }}</div>
                                            <div class="text-[10px] text-[var(--muted)]">{{ $item->pivot->quantity > 1 ? $item->pivot->quantity.' × ' : '' }}{{ nexus_money($item->price) }}</div>
                                        </div>
                                        @if(! $loop->last)<span class="px-1 text-lg text-[var(--muted)]">+</span>@endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                        <div class="border-t border-[var(--line)] pt-4 lg:border-l lg:border-t-0 lg:pl-5 lg:pt-0">
                            <div class="flex items-baseline gap-2">
                                <span class="font-display text-3xl">{{ nexus_money($bundle->bundlePrice()) }}</span>
                                <span class="text-xs text-[var(--muted)] line-through">{{ nexus_money($bundle->subtotal()) }}</span>
                            </div>
                            <div class="mt-1 text-[10px] font-semibold text-emerald-700">You save {{ nexus_money($bundle->discount()) }}</div>
                            <form method="post" action="{{ route('bundles.add', $bundle->slug) }}" class="mt-3">
                                @csrf
                                <button class="btn-primary w-full text-xs" @disabled(! $bundle->isAvailable())>Add bundle to bag</button>
                            </form>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </section>
@endif
