@extends('admin.layouts.app')

@section('title', __('admin.woocommerce.review'))
@section('eyebrow', __('admin.woocommerce.eyebrow'))
@section('heading', $migration->original_filename)

@section('content')
@php($stats = $migration->stats ?? [])
@php($options = $migration->options ?? [])
@php($files = $migration->files ?? [])
@php($progress = $migration->progress ?? [])
@php($isApi = $migration->isApi())
<div class="space-y-5">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <a href="{{ route('admin.migrations.woocommerce.index') }}" class="text-xs font-bold text-[#0f766e]">← {{ __('admin.woocommerce.back') }}</a>
        <div class="flex flex-wrap items-center gap-2">
            <span class="rounded-full bg-[#eaf6f0] px-3 py-1 text-[10px] font-bold text-[#0f604f]">{{ $migration->statusLabel() }}</span>
            @if($migration->error_report_path)
                <a href="{{ route('admin.migrations.woocommerce.errors', $migration) }}" class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-1.5 text-[11px] font-bold text-rose-700">{{ __('admin.woocommerce.download_errors') }}</a>
            @endif
        </div>
    </div>

    @if(data_get($stats, 'errors.0.message'))
        <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-xs text-rose-900">{{ data_get($stats, 'errors.0.message') }}</div>
    @endif

    @if($isApi)
        <section class="rounded-2xl border border-[#dfe8e2] bg-white p-5">
            <div class="text-[10px] font-bold uppercase tracking-wider text-[#8a9892]">{{ __('admin.woocommerce.api_connection') }}</div>
            @if($migration->connection)
                @php($connection = $migration->connection)
                @php($counts = $connection->counts())
                <div class="mt-3 flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <div class="text-sm font-bold text-[#17231f]">{{ $connection->name }}</div>
                        <div class="mt-1 break-all text-[11px] text-[#687771]">{{ $connection->base_url }}</div>
                        <div class="mt-1 text-[10px] text-[#8a9892]">{{ __('admin.woocommerce.last_tested') }}: {{ $connection->last_tested_at?->format('Y-m-d H:i') ?? '—' }}</div>
                    </div>
                    <div class="text-right text-[10px] text-[#53645c]">
                        <span class="rounded-full px-2.5 py-1 font-bold {{ $connection->status === 'connected' ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700' }}">{{ $connection->statusLabel() }}</span>
                        @if($counts !== [])
                            <div class="mt-2">{{ number_format((int) ($counts['products'] ?? 0)) }} products · {{ number_format((int) ($counts['customers'] ?? 0)) }} customers · {{ number_format((int) ($counts['orders'] ?? 0)) }} orders · {{ number_format((int) ($counts['taxes'] ?? 0)) }} tax rates</div>
                        @endif
                    </div>
                </div>
                @if($connection->last_error)
                    <p class="mt-3 rounded-lg bg-rose-50 px-3 py-2 text-[11px] leading-5 text-rose-800">{{ $connection->last_error }}</p>
                @endif
            @else
                <p class="mt-3 rounded-lg bg-rose-50 px-3 py-2 text-[11px] leading-5 text-rose-800">{{ __('admin.woocommerce.api_not_available') }}</p>
            @endif
        </section>

        @if(in_array($migration->status, ['running', 'failed'], true))
            @php($phase = data_get($progress, 'phase', 'products'))
            @php($phaseLabels = ['taxes' => __('admin.woocommerce.api_phase_taxes'), 'products' => __('admin.woocommerce.api_phase_products'), 'customers' => __('admin.woocommerce.api_phase_customers'), 'orders' => __('admin.woocommerce.api_phase_orders'), 'done' => __('admin.woocommerce.api_phase_done')])
            <section id="api-progress" data-url="{{ route('admin.migrations.woocommerce.api.batch', $migration) }}" class="rounded-2xl border border-[#dfe8e2] bg-white p-5">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div class="text-[10px] font-bold uppercase tracking-wider text-[#8a9892]">{{ __('admin.woocommerce.api_progress') }}</div>
                    <div id="api-progress-percent" class="text-sm font-bold text-[#0f766e]">{{ (int) data_get($progress, 'percent', 0) }}%</div>
                </div>
                <div class="mt-3 h-2 overflow-hidden rounded-full bg-[#eef3f0]">
                    <div id="api-progress-bar" class="h-full rounded-full bg-[#0f766e] transition-[width] duration-300" style="width: {{ (int) data_get($progress, 'percent', 0) }}%"></div>
                </div>
                <div class="mt-3 flex flex-wrap gap-x-5 gap-y-1 text-[11px] text-[#53645c]">
                    <span>{{ __('admin.woocommerce.api_phase') }}: <strong id="api-progress-phase">{{ $phaseLabels[$phase] ?? $phase }}</strong></span>
                    <span>{{ __('admin.woocommerce.api_processed') }}: <strong id="api-progress-count">{{ (int) data_get($progress, 'counts.taxes', 0) + (int) data_get($progress, 'counts.products', 0) + (int) data_get($progress, 'counts.customers', 0) + (int) data_get($progress, 'counts.orders', 0) }}</strong> @if(data_get($progress, 'totals.taxes', 0) + data_get($progress, 'totals.products', 0) + data_get($progress, 'totals.customers', 0) + data_get($progress, 'totals.orders', 0) > 0) {{ __('admin.woocommerce.api_of') }} {{ (int) data_get($progress, 'totals.taxes', 0) + (int) data_get($progress, 'totals.products', 0) + (int) data_get($progress, 'totals.customers', 0) + (int) data_get($progress, 'totals.orders', 0) }} @endif</span>
                    <span id="api-progress-detail">{{ (int) data_get($progress, 'counts.taxes', 0) }} tax rates · {{ (int) data_get($progress, 'counts.products', 0) }} products · {{ (int) data_get($progress, 'counts.variations', 0) }} variations · {{ (int) data_get($progress, 'counts.customers', 0) }} customers · {{ (int) data_get($progress, 'counts.orders', 0) }} orders</span>
                </div>
                @if($migration->status === 'failed')
                    <p id="api-progress-error" class="mt-3 rounded-lg bg-rose-50 px-3 py-2 text-[11px] leading-5 text-rose-800">{{ data_get($migration->remote_state, 'last_error', __('admin.woocommerce.api_failed')) }}</p>
                    <button type="button" id="api-resume" class="mt-3 rounded-lg border border-[#cfddd5] bg-white px-3 py-2 text-[11px] font-bold text-[#4d6258]">{{ __('admin.woocommerce.api_resume') }}</button>
                @endif
            </section>
        @endif

        @if(in_array($migration->status, ['ready', 'failed', 'previewed', 'completed'], true) && $migration->connection)
            <form method="post" action="{{ route('admin.migrations.woocommerce.api.run', $migration) }}" class="space-y-5">
                @csrf
                <section class="rounded-2xl border border-[#dfe8e2] bg-white p-5">
                    <div class="text-[10px] font-bold uppercase tracking-wider text-[#8a9892]">{{ __('admin.woocommerce.api_run_options') }}</div>
                    <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        <label class="flex items-center gap-2 text-[11px] text-[#53645c]"><input type="checkbox" name="update_existing" value="1" class="rounded border-[#cfddd5]" @checked($options['update_existing'] ?? true)> {{ __('admin.woocommerce.update_existing') }}</label>
                        <label class="flex items-center gap-2 text-[11px] text-[#53645c]"><input type="checkbox" name="create_categories" value="1" class="rounded border-[#cfddd5]" @checked($options['create_categories'] ?? true)> {{ __('admin.woocommerce.create_categories') }}</label>
                        <label class="flex items-center gap-2 text-[11px] text-[#53645c]"><input type="checkbox" name="download_images" value="1" class="rounded border-[#cfddd5]" @checked($options['download_images'] ?? false)> {{ __('admin.woocommerce.download_images') }}</label>
                        <label class="flex items-center gap-2 text-[11px] text-[#53645c]"><input type="checkbox" name="continue_on_errors" value="1" class="rounded border-[#cfddd5]" @checked($options['continue_on_errors'] ?? true)> {{ __('admin.woocommerce.continue') }}</label>
                    </div>
                    <p class="mt-3 text-[11px] leading-5 text-[#8a9892]">{{ __('admin.woocommerce.download_images_hint') }}</p>
                    <p class="mt-2 text-[11px] leading-5 text-[#8a9892]">{{ __('admin.woocommerce.password_hint') }}</p>
                </section>

                <label class="flex items-start gap-2 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-[11px] leading-5 text-amber-900">
                    <input type="checkbox" name="confirm_import" value="1" class="mt-0.5 rounded border-amber-300">
                    <span>{{ __('admin.woocommerce.confirm') }}</span>
                </label>

                <div class="grid gap-3 md:grid-cols-2">
                    <p class="rounded-2xl border border-[#dfe8e2] bg-white p-4 text-[11px] leading-5 text-[#8a9892]">{{ __('admin.woocommerce.api_dry_run_note') }}</p>
                    <p class="rounded-2xl border border-[#dfe8e2] bg-white p-4 text-[11px] leading-5 text-[#8a9892]">{{ __('admin.woocommerce.api_import_note') }}</p>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-3">
                    <a href="{{ route('admin.migrations.woocommerce.index') }}" class="text-xs font-bold text-[#687771]">{{ __('admin.woocommerce.cancel') }}</a>
                    <div class="flex flex-wrap gap-2">
                        <button name="mode" value="dry_run" formnovalidate class="rounded-lg border border-[#cfddd5] bg-white px-4 py-2.5 text-xs font-bold text-[#4d6258]">{{ __('admin.woocommerce.api_start_dry_run') }}</button>
                        <button name="mode" value="apply" class="btn-primary rounded-lg px-5 py-2.5 text-xs font-bold">{{ __('admin.woocommerce.api_start_import') }}</button>
                    </div>
                </div>
            </form>
        @endif
    @else
        <section class="overflow-hidden rounded-2xl border border-[#dfe8e2] bg-white">
            <div class="border-b border-[#e7eee9] px-5 py-4 text-[10px] font-bold uppercase tracking-wider text-[#8a9892]">{{ __('admin.woocommerce.files') }}</div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-[#fbfdfc] text-[10px] uppercase tracking-wider text-[#8a9892]">
                        <tr>
                            <th class="px-4 py-2">{{ __('admin.woocommerce.source') }}</th>
                            <th class="px-4 py-2">Type</th>
                            <th class="px-4 py-2">{{ __('admin.woocommerce.rows') }}</th>
                            <th class="px-4 py-2">{{ __('admin.woocommerce.encoding') }}</th>
                            <th class="px-4 py-2">{{ __('admin.woocommerce.delimiter') }}</th>
                            <th class="px-4 py-2">{{ __('admin.woocommerce.size') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#eef3f0]">
                        @forelse($files as $file)
                            <tr>
                                <td class="px-4 py-3 font-semibold text-[#17231f]">{{ $file['name'] ?? basename((string) ($file['path'] ?? '')) }}</td>
                                <td class="px-4 py-3">
                                    @if(($file['type'] ?? 'unknown') === 'unknown')
                                        <span class="rounded-full bg-amber-50 px-2 py-1 text-[10px] font-bold text-amber-700">{{ __('admin.woocommerce.unknown') }}</span>
                                    @else
                                        <span class="rounded-full bg-[#eaf6f0] px-2 py-1 text-[10px] font-bold text-[#0f604f]">{{ __('admin.woocommerce.'.($file['type'] ?? 'unknown')) }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-[#53645c]">{{ number_format((int) ($file['rows'] ?? 0)) }}</td>
                                <td class="px-4 py-3 text-[#53645c]">{{ $file['encoding'] ?? '—' }}</td>
                                <td class="px-4 py-3 text-[#53645c]">{{ ($file['delimiter'] ?? '') === "\t" ? 'Tab' : ($file['delimiter'] ?? '—') }}</td>
                                <td class="px-4 py-3 text-[#53645c]">{{ number_format(((int) ($file['size'] ?? 0)) / 1024, 1) }} KB</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-10 text-center text-[#687771]">{{ __('admin.woocommerce.no_files') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        @if($files !== [])
            <section class="space-y-3">
                <div class="text-[10px] font-bold uppercase tracking-wider text-[#8a9892]">{{ __('admin.woocommerce.preview') }}</div>
                @foreach($files as $index => $file)
                    @php($preview = $previews[$index] ?? null)
                    @if($preview)
                        <details class="overflow-hidden rounded-2xl border border-[#dfe8e2] bg-white">
                            <summary class="cursor-pointer px-4 py-3 text-xs font-bold text-[#17231f]">{{ $file['name'] ?? 'CSV' }} <span class="ml-2 font-normal text-[#8a9892]">{{ count($preview['headers'] ?? []) }} columns · {{ count($preview['rows'] ?? []) }} preview rows</span></summary>
                            @if(!empty($preview['error']))
                                <div class="border-t border-rose-100 bg-rose-50 px-4 py-3 text-xs text-rose-900">{{ $preview['error'] }}</div>
                            @else
                                <div class="overflow-x-auto border-t border-[#eef3f0]">
                                    <table class="w-full text-left text-[10px]">
                                        <thead class="bg-[#fbfdfc] text-[#8a9892]"><tr>@foreach(array_slice($preview['headers'], 0, 8) as $header)<th class="whitespace-nowrap px-3 py-2">{{ $header }}</th>@endforeach</tr></thead>
                                        <tbody class="divide-y divide-[#eef3f0]">
                                            @forelse($preview['rows'] as $row)
                                                <tr>@foreach(array_slice($preview['headers'], 0, 8) as $header)<td class="max-w-[240px] truncate px-3 py-2 text-[#53645c]">{{ $row[$header] ?? '' }}</td>@endforeach</tr>
                                            @empty
                                                <tr><td colspan="8" class="px-3 py-6 text-center text-[#8a9892]">{{ __('admin.woocommerce.no_preview') }}</td></tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </details>
                    @endif
                @endforeach
            </section>
        @endif

        @if($migration->status !== 'uploaded' && $files !== [])
            <form method="post" action="{{ route('admin.migrations.woocommerce.run', $migration) }}" class="space-y-5">
                @csrf
                <section class="rounded-2xl border border-[#dfe8e2] bg-white p-5">
                    <div class="text-[10px] font-bold uppercase tracking-wider text-[#8a9892]">{{ __('admin.woocommerce.options') }}</div>
                    <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        <label class="flex items-center gap-2 text-[11px] text-[#53645c]"><input type="checkbox" name="update_existing" value="1" class="rounded border-[#cfddd5]" @checked($options['update_existing'] ?? true)> {{ __('admin.woocommerce.update_existing') }}</label>
                        <label class="flex items-center gap-2 text-[11px] text-[#53645c]"><input type="checkbox" name="create_categories" value="1" class="rounded border-[#cfddd5]" @checked($options['create_categories'] ?? true)> {{ __('admin.woocommerce.create_categories') }}</label>
                        <label class="flex items-center gap-2 text-[11px] text-[#53645c]"><input type="checkbox" name="download_images" value="1" class="rounded border-[#cfddd5]" @checked($options['download_images'] ?? false)> {{ __('admin.woocommerce.download_images') }}</label>
                        <label class="flex items-center gap-2 text-[11px] text-[#53645c]"><input type="checkbox" name="continue_on_errors" value="1" class="rounded border-[#cfddd5]" @checked($options['continue_on_errors'] ?? true)> {{ __('admin.woocommerce.continue') }}</label>
                    </div>
                    <p class="mt-3 text-[11px] leading-5 text-[#8a9892]">{{ __('admin.woocommerce.download_images_hint') }}</p>
                    <p class="mt-2 text-[11px] leading-5 text-[#8a9892]">{{ __('admin.woocommerce.password_hint') }}</p>
                </section>

                <label class="flex items-start gap-2 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-[11px] leading-5 text-amber-900">
                    <input type="checkbox" name="confirm_import" value="1" required class="mt-0.5 rounded border-amber-300">
                    <span>{{ __('admin.woocommerce.confirm') }}</span>
                </label>

                <div class="flex flex-wrap items-center justify-between gap-3">
                    <a href="{{ route('admin.migrations.woocommerce.index') }}" class="text-xs font-bold text-[#687771]">{{ __('admin.woocommerce.cancel') }}</a>
                    <div class="flex flex-wrap gap-2">
                        <button name="mode" value="dry_run" formnovalidate class="rounded-lg border border-[#cfddd5] bg-white px-4 py-2.5 text-xs font-bold text-[#4d6258]">{{ __('admin.woocommerce.dry_run') }}</button>
                        <button name="mode" value="apply" class="btn-primary rounded-lg px-5 py-2.5 text-xs font-bold">{{ __('admin.woocommerce.run') }}</button>
                    </div>
                </div>
            </form>
        @endif
    @endif

    @if($stats)
        <section class="space-y-3">
            <div class="text-[10px] font-bold uppercase tracking-wider text-[#8a9892]">{{ __('admin.woocommerce.summary') }}</div>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                @foreach(['products' => 'products', 'variations' => 'variations', 'customers' => 'customers', 'orders' => 'orders', 'taxes' => 'taxes'] as $key => $label)
                    @php($bucket = data_get($stats, $key, []))
                    <div class="rounded-2xl border border-[#dfe8e2] bg-white p-4">
                        <div class="text-[10px] font-bold uppercase tracking-wider text-[#8a9892]">{{ __('admin.woocommerce.'.$label) }}</div>
                        <div class="mt-1 text-2xl font-bold text-[#17231f]">{{ number_format((int) ($bucket['total'] ?? 0)) }}</div>
                        <div class="mt-2 flex flex-wrap gap-x-3 gap-y-1 text-[10px] text-[#687771]">
                            <span class="text-emerald-700">{{ __('admin.woocommerce.created') }} {{ (int) ($bucket['created'] ?? 0) }}</span>
                            <span class="text-blue-700">{{ __('admin.woocommerce.updated') }} {{ (int) ($bucket['updated'] ?? 0) }}</span>
                            <span>{{ __('admin.woocommerce.skipped') }} {{ (int) ($bucket['skipped'] ?? 0) }}</span>
                            <span class="text-rose-700">{{ __('admin.woocommerce.failed') }} {{ (int) ($bucket['failed'] ?? 0) }}</span>
                        </div>
                        @if($key === 'orders')
                            <div class="mt-2 text-[10px] text-[#8a9892]">{{ __('admin.woocommerce.items') }}: {{ number_format((int) ($bucket['items'] ?? 0)) }}</div>
                        @endif
                        @if($key === 'taxes')
                            <div class="mt-2 text-[10px] text-[#8a9892]">{{ __('admin.woocommerce.tax_settings') }}: {{ number_format((int) ($bucket['settings'] ?? 0)) }}</div>
                        @endif
                    </div>
                @endforeach
            </div>

            <div class="grid gap-3 lg:grid-cols-2">
                <div class="rounded-2xl border border-[#dfe8e2] bg-white p-4">
                    <div class="text-[10px] font-bold uppercase tracking-wider text-[#8a9892]">{{ __('admin.woocommerce.warnings') }}</div>
                    @forelse(data_get($stats, 'warnings', []) as $warning)
                        <p class="mt-2 text-[11px] leading-5 text-amber-800">{{ $warning['message'] ?? '' }} @if(!empty($warning['file']))<span class="text-[#8a9892]">({{ $warning['file'] }})</span>@endif</p>
                    @empty
                        <p class="mt-2 text-[11px] text-[#8a9892]">{{ __('admin.woocommerce.no_warnings') }}</p>
                    @endforelse
                </div>
                <div class="rounded-2xl border border-[#dfe8e2] bg-white p-4">
                    <div class="text-[10px] font-bold uppercase tracking-wider text-[#8a9892]">{{ __('admin.woocommerce.errors') }}</div>
                    @forelse(data_get($stats, 'errors', []) as $error)
                        <p class="mt-2 text-[11px] leading-5 text-rose-800">{{ $error['message'] ?? '' }} @if(!empty($error['file']))<span class="text-[#8a9892]">({{ $error['file'] }}:{{ $error['row'] ?? 0 }})</span>@endif</p>
                    @empty
                        <p class="mt-2 text-[11px] text-[#8a9892]">{{ __('admin.woocommerce.no_errors') }}</p>
                    @endforelse
                </div>
            </div>
            <p class="text-[11px] leading-5 text-[#8a9892]">{{ __('admin.woocommerce.warning_limit') }}</p>
        </section>
    @endif

    <div class="grid gap-3 md:grid-cols-2">
        <p class="rounded-2xl border border-[#dfe8e2] bg-white p-4 text-[11px] leading-5 text-[#8a9892]">{{ __('admin.woocommerce.guest_hint') }}</p>
        <p class="rounded-2xl border border-[#dfe8e2] bg-white p-4 text-[11px] leading-5 text-[#8a9892]">{{ $isApi ? __('admin.woocommerce.api_import_note') : __('admin.woocommerce.format_note') }}</p>
    </div>
</div>

@if($isApi && in_array($migration->status, ['running', 'failed'], true))
    <script>
        (function () {
            const box = document.getElementById('api-progress');
            if (! box) return;

            const url = box.dataset.url;
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
            const phases = {!! json_encode(['taxes' => __('admin.woocommerce.api_phase_taxes'), 'products' => __('admin.woocommerce.api_phase_products'), 'customers' => __('admin.woocommerce.api_phase_customers'), 'orders' => __('admin.woocommerce.api_phase_orders'), 'done' => __('admin.woocommerce.api_phase_done')]) !!};
            const labels = {
                percent: document.getElementById('api-progress-percent'),
                bar: document.getElementById('api-progress-bar'),
                phase: document.getElementById('api-progress-phase'),
                count: document.getElementById('api-progress-count'),
                detail: document.getElementById('api-progress-detail'),
                error: document.getElementById('api-progress-error'),
                resume: document.getElementById('api-resume'),
            };
            let busy = false;

            function render(progress) {
                if (! progress) return;
                const counts = progress.counts || {};
                const totals = progress.totals || {};
                const processed = Number(counts.taxes || 0) + Number(counts.products || 0) + Number(counts.customers || 0) + Number(counts.orders || 0);
                const total = Number(totals.taxes || 0) + Number(totals.products || 0) + Number(totals.customers || 0) + Number(totals.orders || 0);

                if (labels.percent) labels.percent.textContent = progress.percent + '%';
                if (labels.bar) labels.bar.style.width = progress.percent + '%';
                if (labels.phase) labels.phase.textContent = phases[progress.phase] || progress.phase;
                if (labels.count) labels.count.textContent = processed + (total > 0 ? ' ' + {!! json_encode(__('admin.woocommerce.api_of')) !!} + ' ' + total : '');
                if (labels.detail) {
                    labels.detail.textContent = [counts.taxes, counts.products, counts.variations, counts.customers, counts.orders]
                        .map((value, index) => Number(value || 0) + ' ' + ['tax rates', 'products', 'variations', 'customers', 'orders'][index])
                        .join(' · ');
                }
            }

            async function step() {
                if (busy) return;
                busy = true;
                try {
                    const response = await fetch(url, {
                        method: 'POST',
                        headers: {'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf},
                        body: '{}',
                    });
                    const data = await response.json();
                    if (data.progress) render(data.progress);
                    if (! response.ok) throw new Error(data.message || 'The API migration stopped.');
                    if (data.done) {
                        window.location.reload();
                        return;
                    }
                    busy = false;
                    window.setTimeout(step, 250);
                } catch (error) {
                    busy = false;
                    if (labels.resume) labels.resume.disabled = false;
                    if (labels.error) {
                        labels.error.textContent = error.message;
                        labels.error.classList.remove('hidden');
                    } else {
                        window.alert(error.message);
                    }
                }
            }

            if (labels.resume) {
                labels.resume.addEventListener('click', () => {
                    labels.resume.disabled = true;
                    step();
                });
            } else {
                step();
            }
        })();
    </script>
@endif
@endsection
