@extends('admin.layouts.app')

@section('title', __('admin.woocommerce.title'))
@section('eyebrow', __('admin.woocommerce.eyebrow'))
@section('heading', __('admin.woocommerce.title'))

@section('content')
<div class="space-y-5">
    <section class="rounded-2xl border border-[#dfe8e2] bg-gradient-to-br from-[#fffdf8] to-[#f2f8f4] p-6">
        <div class="flex flex-wrap items-end justify-between gap-5">
            <div>
                <div class="text-[10px] font-extrabold uppercase tracking-[.14em] text-[#0f766e]">{{ __('admin.woocommerce.api_eyebrow') }}</div>
                <h2 class="mt-1 font-display text-3xl font-medium tracking-tight text-[#17231f]">{{ __('admin.woocommerce.api_heading') }}</h2>
                <p class="mt-2 max-w-3xl text-xs leading-6 text-[#687771]">{{ __('admin.woocommerce.api_lede') }}</p>
            </div>
            <span class="rounded-full bg-white px-3 py-1.5 text-[10px] font-bold text-[#0f604f] ring-1 ring-[#cfddd5]">{{ __('admin.woocommerce.api_badge') }}</span>
        </div>
    </section>

    <section class="rounded-2xl border border-[#dfe8e2] bg-white p-5">
        <div class="text-[10px] font-bold uppercase tracking-wider text-[#8a9892]">{{ __('admin.woocommerce.api_title') }}</div>
        <form method="post" action="{{ route('admin.migrations.woocommerce.connections.store') }}" class="mt-3 grid gap-3 md:grid-cols-2">
            @csrf
            <label class="block">
                <span class="mb-1 block text-[10px] font-semibold text-[#53645c]">{{ __('admin.woocommerce.connection_name') }}</span>
                <input type="text" name="name" value="{{ old('name') }}" required maxlength="100" placeholder="{{ __('admin.woocommerce.connection_name_placeholder') }}" class="w-full rounded-lg border border-[#cfddd5] bg-[#fbfdfc] px-3 py-2 text-xs">
            </label>
            <label class="block">
                <span class="mb-1 block text-[10px] font-semibold text-[#53645c]">{{ __('admin.woocommerce.base_url') }}</span>
                <input type="url" name="base_url" value="{{ old('base_url') }}" required maxlength="500" placeholder="{{ __('admin.woocommerce.base_url_placeholder') }}" class="w-full rounded-lg border border-[#cfddd5] bg-[#fbfdfc] px-3 py-2 text-xs">
            </label>
            <label class="block">
                <span class="mb-1 block text-[10px] font-semibold text-[#53645c]">{{ __('admin.woocommerce.consumer_key') }}</span>
                <input type="text" name="consumer_key" value="{{ old('consumer_key') }}" required maxlength="1000" autocomplete="off" class="w-full rounded-lg border border-[#cfddd5] bg-[#fbfdfc] px-3 py-2 font-mono text-xs">
            </label>
            <label class="block">
                <span class="mb-1 block text-[10px] font-semibold text-[#53645c]">{{ __('admin.woocommerce.consumer_secret') }}</span>
                <input type="password" name="consumer_secret" required maxlength="1000" autocomplete="new-password" class="w-full rounded-lg border border-[#cfddd5] bg-[#fbfdfc] px-3 py-2 font-mono text-xs">
            </label>
            <div class="md:col-span-2 flex flex-wrap items-center gap-3">
                <button class="btn-primary rounded-lg px-5 py-2.5 text-sm font-bold">{{ __('admin.woocommerce.connect') }}</button>
                <span class="text-[11px] leading-5 text-[#8a9892]">{{ __('admin.woocommerce.api_hint') }}</span>
            </div>
        </form>
        <p class="mt-3 text-[11px] leading-5 text-[#8a9892]">{{ __('admin.woocommerce.credentials_hint') }}</p>
    </section>

    <section class="overflow-hidden rounded-2xl border border-[#dfe8e2] bg-white">
        <div class="border-b border-[#e7eee9] px-5 py-4 text-[10px] font-bold uppercase tracking-wider text-[#8a9892]">{{ __('admin.woocommerce.connections') }}</div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-[#fbfdfc] text-[10px] uppercase tracking-wider text-[#8a9892]">
                    <tr>
                        <th class="px-4 py-2">{{ __('admin.woocommerce.connection_name') }}</th>
                        <th class="px-4 py-2">{{ __('admin.woocommerce.api_counts') }}</th>
                        <th class="px-4 py-2">{{ __('admin.woocommerce.status') }}</th>
                        <th class="px-4 py-2">{{ __('admin.woocommerce.last_tested') }}</th>
                        <th class="px-4 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#eef3f0]">
                    @forelse($connections as $connection)
                        @php($counts = $connection->counts())
                        <tr>
                            <td class="px-4 py-3">
                                <div class="font-semibold text-[#17231f]">{{ $connection->name }}</div>
                                <div class="mt-0.5 max-w-[320px] truncate text-[10px] text-[#8a9892]">{{ $connection->base_url }}</div>
                            </td>
                            <td class="px-4 py-3 text-[#53645c]">
                                @if($counts !== [])
                                    {{ number_format((int) ($counts['products'] ?? 0)) }} products · {{ number_format((int) ($counts['customers'] ?? 0)) }} customers · {{ number_format((int) ($counts['orders'] ?? 0)) }} orders · {{ number_format((int) ($counts['taxes'] ?? 0)) }} tax rates
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <span class="rounded-full px-2.5 py-1 text-[10px] font-bold {{ $connection->status === 'connected' ? 'bg-emerald-50 text-emerald-700' : ($connection->status === 'failed' ? 'bg-rose-50 text-rose-700' : 'bg-amber-50 text-amber-700') }}">
                                    {{ $connection->statusLabel() }}
                                </span>
                                @if($connection->last_error)
                                    <div class="mt-1 max-w-[280px] text-[10px] leading-4 text-rose-700">{{ $connection->last_error }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-[#8a9892]">{{ $connection->last_tested_at?->format('Y-m-d H:i') ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap justify-end gap-2">
                                    <form method="post" action="{{ route('admin.migrations.woocommerce.connections.test', $connection) }}">
                                        @csrf
                                        <button class="rounded-lg border border-[#cfddd5] bg-white px-2.5 py-1.5 text-[10px] font-bold text-[#4d6258]">{{ __('admin.woocommerce.test_connection') }}</button>
                                    </form>
                                    @if($connection->status === 'connected')
                                        <form method="post" action="{{ route('admin.migrations.woocommerce.connections.start', $connection) }}">
                                            @csrf
                                            <button class="btn-primary rounded-lg px-2.5 py-1.5 text-[10px] font-bold">{{ __('admin.woocommerce.start_migration') }}</button>
                                        </form>
                                    @endif
                                    <form method="post" action="{{ route('admin.migrations.woocommerce.connections.destroy', $connection) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button class="rounded-lg border border-rose-200 bg-rose-50 px-2.5 py-1.5 text-[10px] font-bold text-rose-700">{{ __('admin.woocommerce.delete_connection') }}</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-10 text-center text-[#687771]">{{ __('admin.woocommerce.no_connections') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="rounded-2xl border border-[#dfe8e2] bg-white p-5">
        <div class="text-[10px] font-bold uppercase tracking-wider text-[#8a9892]">{{ __('admin.woocommerce.file') }}</div>
        <form method="post" action="{{ route('admin.migrations.woocommerce.store') }}" enctype="multipart/form-data" class="mt-3 flex flex-wrap items-end gap-3">
            @csrf
            <label class="block min-w-[260px] flex-1">
                <span class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-[#8a9892]">{{ __('admin.woocommerce.file') }}</span>
                <input type="file" name="archive" accept=".zip,.csv,.txt,application/zip,text/csv,text/plain" required class="w-full rounded-lg border border-[#cfddd5] bg-[#fbfdfc] px-3 py-2 text-xs">
            </label>
            <button class="rounded-lg border border-[#cfddd5] bg-white px-4 py-2.5 text-xs font-bold text-[#4d6258]">{{ __('admin.woocommerce.upload') }}</button>
        </form>
        <p class="mt-3 text-[11px] leading-5 text-[#8a9892]">{{ __('admin.woocommerce.upload_hint') }}</p>
    </section>

    <section class="overflow-hidden rounded-2xl border border-[#dfe8e2] bg-white">
        <div class="border-b border-[#e7eee9] px-5 py-4 text-[10px] font-bold uppercase tracking-wider text-[#8a9892]">{{ __('admin.woocommerce.history') }}</div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-[#fbfdfc] text-[10px] uppercase tracking-wider text-[#8a9892]">
                    <tr>
                        <th class="px-4 py-2">{{ __('admin.woocommerce.source') }}</th>
                        <th class="px-4 py-2">{{ __('admin.woocommerce.detected') }}</th>
                        <th class="px-4 py-2">{{ __('admin.woocommerce.status') }}</th>
                        <th class="px-4 py-2">{{ __('admin.woocommerce.result') }}</th>
                        <th class="px-4 py-2">{{ __('admin.woocommerce.uploaded') }}</th>
                        <th class="px-4 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#eef3f0]">
                    @forelse($migrations as $migration)
                        @php($stats = $migration->stats ?? [])
                        <tr>
                            <td class="px-4 py-3 font-semibold text-[#17231f]">
                                {{ $migration->original_filename }}
                                <div class="mt-0.5 text-[10px] font-normal text-[#8a9892]">{{ $migration->isApi() ? __('admin.woocommerce.api_source') : __('admin.woocommerce.files_source') }}</div>
                            </td>
                            <td class="px-4 py-3 text-[#53645c]">{{ $migration->detectedSummary() }}</td>
                            <td class="px-4 py-3">
                                <span class="rounded-full bg-[#eaf6f0] px-2.5 py-1 text-[10px] font-bold text-[#0f604f]">{{ $migration->statusLabel() }}</span>
                            </td>
                            <td class="px-4 py-3 text-[#53645c]">
                                @if($stats)
                                    {{ data_get($stats, 'products.created', 0) + data_get($stats, 'variations.created', 0) }} created · {{ data_get($stats, 'customers.created', 0) }} customers · {{ data_get($stats, 'orders.created', 0) }} orders · {{ data_get($stats, 'taxes.created', 0) }} tax rates
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-4 py-3 text-[#8a9892]">{{ $migration->created_at?->format('Y-m-d H:i') }}</td>
                            <td class="px-4 py-3 text-right"><a href="{{ route('admin.migrations.woocommerce.show', $migration) }}" class="font-bold text-[#0f766e]">{{ __('admin.woocommerce.open') }}</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-10 text-center text-[#687771]">{{ __('admin.woocommerce.empty') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    {{ $migrations->links() }}

    <div class="grid gap-3 md:grid-cols-2">
        <p class="rounded-2xl border border-[#dfe8e2] bg-white p-4 text-[11px] leading-5 text-[#8a9892]">{{ __('admin.woocommerce.api_fallback_note') }}</p>
        <p class="rounded-2xl border border-[#dfe8e2] bg-white p-4 text-[11px] leading-5 text-[#8a9892]">{{ __('admin.woocommerce.what_is_imported') }}</p>
    </div>
</div>
@endsection
