@extends('admin.layouts.app')

@section('title', 'Shipping')
@section('eyebrow', 'Operations')
@section('heading', 'Shipping')

@section('content')
<div class="grid gap-5 xl:grid-cols-[1.5fr_1fr]">
    <div class="space-y-5">
        @forelse($zones as $zone)
            <div class="card overflow-hidden">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-[#e3e9e5] px-5 py-4">
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="font-display text-xl">{{ $zone->name }}</h2>
                            <span class="badge {{ $zone->is_active ? 'badge-emerald' : 'badge-slate' }}">{{ $zone->is_active ? 'Active' : 'Inactive' }}</span>
                        </div>
                        <div class="mt-1 text-xs text-[#8a9892]">{{ implode(', ', $zone->countries ?? []) }}@if($zone->states) · {{ implode(', ', $zone->states) }}@endif</div>
                    </div>
                    <div class="flex items-center gap-3">
                        <details class="relative">
                            <summary class="cursor-pointer list-none text-xs font-bold text-[#0f766e]">Edit zone</summary>
                            <form method="post" action="{{ route('admin.shipping.zones.update', $zone) }}" class="absolute right-0 z-20 mt-2 w-72 space-y-2 rounded-xl border border-[#e3e9e5] bg-white p-4 shadow-xl">
                                @csrf
                                @method('PATCH')
                                <input name="name" value="{{ $zone->name }}" class="input" required>
                                <input name="countries" value="{{ implode(', ', $zone->countries ?? []) }}" class="input" placeholder="US, CA" required>
                                <input name="states" value="{{ implode(', ', $zone->states ?? []) }}" class="input" placeholder="States (optional)">
                                <label class="flex items-center gap-2 text-xs"><input type="checkbox" name="is_active" value="1" class="accent-[#0f766e]" @checked($zone->is_active)> Active</label>
                                <button class="btn-primary w-full !py-2 text-xs">Save zone</button>
                            </form>
                        </details>
                        <form method="post" action="{{ route('admin.shipping.zones.destroy', $zone) }}" onsubmit="return confirm('Delete this zone and its methods?')">
                            @csrf
                            @method('DELETE')
                            <button class="text-xs font-semibold text-rose-600">Delete</button>
                        </form>
                    </div>
                </div>

                <div class="divide-y divide-[#edf2ef]">
                    @forelse($zone->methods as $method)
                        <form method="post" action="{{ route('admin.shipping.methods.update', $method) }}" class="grid gap-2 px-5 py-3 sm:grid-cols-[1.3fr_.8fr_.6fr_.7fr_auto]">
                            @csrf
                            @method('PATCH')
                            <input name="zone_id" type="hidden" value="{{ $zone->id }}">
                            <input name="name" value="{{ $method->name }}" class="input !py-2 text-xs" required>
                            <select name="type" class="select !py-2 text-xs">
                                <option value="flat_rate" @selected($method->type === 'flat_rate')>Flat rate</option>
                                <option value="free" @selected($method->type === 'free')>Free</option>
                                <option value="weight_based" @selected($method->type === 'weight_based')>Weight based</option>
                            </select>
                            <input name="cost" type="number" step="0.01" min="0" value="{{ $method->cost }}" class="input !py-2 text-xs" placeholder="Cost">
                            <input name="free_threshold" type="number" step="0.01" min="0" value="{{ $method->free_threshold }}" class="input !py-2 text-xs" placeholder="Free over">
                            <div class="flex items-center gap-1">
                                <input type="hidden" name="is_active" value="0">
                                <label class="flex items-center gap-1 text-[10px]"><input type="checkbox" name="is_active" value="1" class="accent-[#0f766e]" @checked($method->is_active)> On</label>
                                <button class="rounded-lg p-1.5 text-[#0f766e] hover:bg-[#eef4f0]" aria-label="Save"><svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20 7 9.5 17.5 4 12"/></svg></button>
                            </div>
                        </form>
                    @empty
                        <p class="px-5 py-4 text-sm text-[#8a9892]">No delivery methods in this zone yet.</p>
                    @endforelse
                </div>

                <details class="border-t border-[#e3e9e5] px-5 py-3">
                    <summary class="cursor-pointer text-xs font-bold text-[#0f766e]">+ Add method</summary>
                    <form method="post" action="{{ route('admin.shipping.methods.store') }}" class="mt-3 grid gap-2 sm:grid-cols-3">
                        @csrf
                        <input type="hidden" name="zone_id" value="{{ $zone->id }}">
                        <input name="name" class="input !py-2 text-xs" placeholder="Standard delivery" required>
                        <select name="type" class="select !py-2 text-xs"><option value="flat_rate">Flat rate</option><option value="free">Free</option><option value="weight_based">Weight based</option></select>
                        <input name="cost" type="number" step="0.01" min="0" class="input !py-2 text-xs" placeholder="Cost">
                        <input name="free_threshold" type="number" step="0.01" min="0" class="input !py-2 text-xs" placeholder="Free over">
                        <input name="per_kg" type="number" step="0.01" min="0" class="input !py-2 text-xs" placeholder="Per kg">
                        <label class="flex items-center gap-2 text-xs"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" class="accent-[#0f766e]" checked> Active</label>
                        <button class="btn-secondary !py-2 text-xs sm:col-span-3">Add method</button>
                    </form>
                </details>
            </div>
        @empty
            <div class="card card-pad text-center">
                <p class="text-sm text-[#8a9892]">No shipping zones yet. Create your first zone on the right.</p>
            </div>
        @endforelse
    </div>

    <div class="space-y-5">
        <form method="post" action="{{ route('admin.shipping.zones.store') }}" class="card card-pad">
            @csrf
            <h2 class="font-display text-xl">Add shipping zone</h2>
            <div class="mt-4 space-y-3">
                <div><label class="label" for="zone_name">Zone name</label><input id="zone_name" name="name" class="input" placeholder="United States" required></div>
                <div><label class="label" for="countries">Countries</label><input id="countries" name="countries" class="input" placeholder="US, CA" required><p class="mt-1 text-[11px] text-[#8a9892]">Comma-separated ISO codes.</p></div>
                <div><label class="label" for="states">States / regions (optional)</label><input id="states" name="states" class="input" placeholder="CA, NY"></div>
                <div><label class="label" for="sort_order">Priority</label><input id="sort_order" type="number" min="0" name="sort_order" value="0" class="input !w-24"></div>
                <label class="flex items-center gap-2 text-xs"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" class="accent-[#0f766e]" checked> Active</label>
            </div>
            <button class="btn-primary mt-4 w-full">Create zone</button>
        </form>

        <div class="card card-pad text-xs leading-5 text-[#687771]">
            <div class="text-[10px] font-bold uppercase tracking-[.14em] text-[#8a9892]">How rates work</div>
            <p class="mt-2">The first zone that covers the customer's country (and state, if set) supplies the delivery options. A free-shipping threshold on a method overrides its base cost. Weight-based methods add the per-kg rate to the base cost.</p>
        </div>
    </div>
</div>
@endsection
