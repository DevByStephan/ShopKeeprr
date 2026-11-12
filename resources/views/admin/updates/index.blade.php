@extends('admin.layouts.app')

@section('title', 'Updates')
@section('eyebrow', 'System')
@section('heading', 'Updates')

@section('content')
@php
    $warnings = $plan?->warnings ?? [];
    $target = $plan && $plan->releases !== [] ? $plan->releases[array_key_last($plan->releases)] : null;
    $blocking = $plan?->hasBlockingWarnings() ?? false;
@endphp

<div class="space-y-5">
    @if($updateError || ($plan && $plan->error))
        <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900">
            <div class="flex items-start gap-3">
                <svg class="mt-0.5 h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01"/></svg>
                <div>
                    <div class="font-bold">The update check could not complete</div>
                    <p class="mt-1 text-xs leading-5">{{ $updateError ?: $plan->error }}</p>
                    <p class="mt-2 text-[11px] leading-5 text-rose-800/80">Your store has not been changed. Check the manifest URL and server connectivity, then try again. You can also update from the command line with <code class="rounded bg-white/70 px-1">php artisan shopkeeprr:update</code>.</p>
                </div>
            </div>
        </div>
    @endif

    <div class="grid gap-5 xl:grid-cols-[1.25fr_1fr]">
        <section class="card card-pad">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <div class="text-[10px] font-bold uppercase tracking-[.14em] text-[#0f766e]">Installed release</div>
                    <div class="mt-1 flex items-baseline gap-2">
                        <h2 class="font-display text-4xl">{{ $currentVersion }}</h2>
                        <span class="badge badge-slate">module API {{ $moduleApi }}</span>
                    </div>
                    <p class="mt-2 max-w-xl text-xs leading-5 text-[#687771]">
                        ShopKeeprr checks a signed release manifest, verifies the package hash and RSA-SHA256 signature, then backs up the database and changed files before touching the storefront.
                    </p>
                </div>
                <form method="post" action="{{ route('admin.updates.check') }}">
                    @csrf
                    <button class="btn-primary !px-5 !py-2.5 text-xs">
                        <span class="inline-flex items-center gap-2">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M20 12a8 8 0 1 1-2.3-5.6M20 4v5h-5"/></svg>
                            Check for updates
                        </span>
                    </button>
                </form>
            </div>

            <div class="mt-5 grid gap-3 text-[11px] sm:grid-cols-2">
                <div class="rounded-xl border border-[#e3e9e5] bg-[#fafcfb] px-3 py-2.5">
                    <div class="font-bold uppercase tracking-[.12em] text-[#8a9892]">Channel</div>
                    <div class="mt-1 font-mono text-xs text-[#17231f]">{{ $channel }}</div>
                </div>
                <div class="rounded-xl border border-[#e3e9e5] bg-[#fafcfb] px-3 py-2.5">
                    <div class="font-bold uppercase tracking-[.12em] text-[#8a9892]">Last check</div>
                    <div class="mt-1 text-xs text-[#17231f]">
                        {{ $lastCheckedAt ? \Illuminate\Support\Carbon::parse($lastCheckedAt)->diffForHumans() : 'Not checked on this device yet' }}
                    </div>
                </div>
                <div class="rounded-xl border border-[#e3e9e5] bg-[#fafcfb] px-3 py-2.5 sm:col-span-2">
                    <div class="font-bold uppercase tracking-[.12em] text-[#8a9892]">Manifest</div>
                    <div class="mt-1 break-all font-mono text-[11px] text-[#17231f]">{{ $manifestUrl }}</div>
                </div>
            </div>

            <div class="mt-4 flex flex-wrap items-center gap-x-4 gap-y-2 text-[11px] text-[#687771]">
                <span class="inline-flex items-center gap-1.5"><span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span> HTTPS transport</span>
                <span class="inline-flex items-center gap-1.5"><span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span> SHA-256 package hash</span>
                <span class="inline-flex items-center gap-1.5"><span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span> RSA-SHA256 signature</span>
            </div>
        </section>

        <section class="card card-pad">
            @if($plan === null)
                <div class="flex h-full flex-col justify-center py-4 text-center">
                    <div class="mx-auto grid h-12 w-12 place-items-center rounded-2xl bg-[#e7f5f0] text-[#0f766e]">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path d="M12 3v12M8 11l4 4 4-4"/><path d="M5 20h14"/></svg>
                    </div>
                    <h3 class="mt-4 font-display text-2xl">Check for a newer release</h3>
                    <p class="mx-auto mt-2 max-w-sm text-xs leading-5 text-[#687771]">Use the button on the left. Release notes, compatibility warnings and the backup plan are shown before anything is installed.</p>
                </div>
            @elseif($plan->error)
                <div class="flex h-full flex-col justify-center py-4 text-center">
                    <div class="mx-auto grid h-12 w-12 place-items-center rounded-2xl bg-rose-50 text-rose-600">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01"/></svg>
                    </div>
                    <h3 class="mt-4 font-display text-2xl">No installable release</h3>
                    <p class="mx-auto mt-2 max-w-sm text-xs leading-5 text-[#687771]">The manifest was reached, but no upgrade path applies to this installation. See the message above.</p>
                </div>
            @elseif($plan->upToDate())
                <div class="flex h-full flex-col justify-center py-4 text-center">
                    <div class="mx-auto grid h-12 w-12 place-items-center rounded-2xl bg-emerald-50 text-emerald-600">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M20 7 9.5 17.5 4 12"/></svg>
                    </div>
                    <h3 class="mt-4 font-display text-2xl">You're up to date</h3>
                    <p class="mx-auto mt-2 max-w-sm text-xs leading-5 text-[#687771]">{{ $currentVersion }} is the newest release on the {{ $channel }} channel. Signed updates are included for every 1.1 installation.</p>
                    <div class="mx-auto mt-4 rounded-full bg-emerald-50 px-3 py-1 text-[11px] font-bold text-emerald-700">No action needed</div>
                </div>
            @else
                <div class="flex h-full flex-col">
                    <div class="flex items-center gap-2">
                        <span class="inline-flex h-2 w-2 animate-pulse rounded-full bg-amber-500"></span>
                        <span class="text-[10px] font-bold uppercase tracking-[.14em] text-amber-700">Update available</span>
                    </div>
                    <h3 class="mt-2 font-display text-3xl">{{ $target?->version }}</h3>
                    <p class="mt-1 text-xs text-[#687771]">
                        {{ $target?->changedFileCount() }} files · {{ $target?->humanSize() }}
                        @if($plan->releases !== [] && count($plan->releases) > 1)
                            · {{ count($plan->releases) }} releases in this chain
                        @endif
                    </p>

                    @if($target?->highlights)
                        <ul class="mt-4 space-y-2 text-xs leading-5 text-[#3f4f49]">
                            @foreach(array_slice($target->highlights, 0, 4) as $highlight)
                                <li class="flex gap-2"><span class="mt-1.5 h-1 w-1 shrink-0 rounded-full bg-[#0f766e]"></span><span>{{ $highlight }}</span></li>
                            @endforeach
                        </ul>
                    @endif

                    <div class="mt-auto pt-5">
                        <a href="{{ route('admin.updates.confirm', ['version' => $target->version]) }}" class="btn-primary flex w-full items-center justify-center gap-2 !py-2.5 text-xs">
                            Review & install {{ $target->version }}
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                        </a>
                        <p class="mt-2 text-center text-[10px] text-[#8a9892]">You will see the release notes and backup plan first.</p>
                    </div>
                </div>
            @endif
        </section>
    </div>

    @if($warnings !== [])
        <section class="card card-pad">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <div class="text-[10px] font-bold uppercase tracking-[.14em] text-[#8a9892]">Compatibility</div>
                    <h2 class="mt-1 font-display text-2xl">Installed modules & this release</h2>
                </div>
                <span class="badge {{ $blocking ? 'badge-rose' : 'badge-amber' }}">{{ $blocking ? 'Needs acknowledgement' : 'Review recommended' }}</span>
            </div>
            <div class="mt-4 space-y-2">
                @foreach($warnings as $warning)
                    @php($severity = $warning['severity'] ?? 'notice')
                    <div class="flex items-start gap-3 rounded-xl border px-3.5 py-3 text-xs leading-5
                        {{ $severity === 'blocking' ? 'border-rose-200 bg-rose-50 text-rose-900' : ($severity === 'warning' ? 'border-amber-200 bg-amber-50 text-amber-900' : 'border-[#e3e9e5] bg-[#fafcfb] text-[#52625c]') }}">
                        <span class="mt-1 h-1.5 w-1.5 shrink-0 rounded-full {{ $severity === 'blocking' ? 'bg-rose-500' : ($severity === 'warning' ? 'bg-amber-500' : 'bg-[#8a9892]') }}"></span>
                        <span>
                            <strong class="font-bold uppercase tracking-wide">{{ $severity === 'blocking' ? 'Blocking' : ($severity === 'warning' ? 'Warning' : 'Note') }}</strong>
                            — {{ $warning['message'] }}
                            @if($warning['module'])
                                <span class="ml-1 rounded bg-white/60 px-1.5 py-0.5 font-mono text-[10px]">{{ $warning['module'] }}</span>
                            @endif
                        </span>
                    </div>
                @endforeach
            </div>
            <p class="mt-3 text-[11px] leading-5 text-[#8a9892]">Modules live in <code class="rounded bg-[#f5f8f6] px-1">modules/</code> and are never overwritten by an update. A module that declares an incompatible version is not disabled automatically; you must confirm the warning before installing.</p>
        </section>
    @endif

    <section class="card overflow-hidden">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-[#e3e9e5] px-5 py-4">
            <div>
                <div class="text-[10px] font-bold uppercase tracking-[.14em] text-[#8a9892]">History</div>
                <h2 class="mt-1 font-display text-2xl">Update runs & backups</h2>
            </div>
            <span class="text-[11px] text-[#8a9892]">Keeps the last {{ (int) config('nexuscart.updates.backup_keep', 5) }} backup sets</span>
        </div>

        @if($runs->isEmpty())
            <div class="px-5 py-10 text-center">
                <p class="text-sm text-[#687771]">No updates have been installed on this store yet.</p>
                <p class="mt-1 text-[11px] text-[#8a9892]">The first update will create a database dump and a file backup before changing anything.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-xs">
                    <thead class="bg-[#f8faf9] text-[10px] uppercase tracking-[.12em] text-[#8a9892]">
                        <tr>
                            <th class="px-5 py-3 font-bold">Release</th>
                            <th class="px-4 py-3 font-bold">Status</th>
                            <th class="px-4 py-3 font-bold">When</th>
                            <th class="px-4 py-3 font-bold">Files</th>
                            <th class="px-4 py-3 font-bold">Backup</th>
                            <th class="px-4 py-3 font-bold">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#edf2ef]">
                        @foreach($runs as $run)
                            <tr class="align-top">
                                <td class="px-5 py-3">
                                    <div class="font-bold text-[#17231f]">{{ $run->to_version }}</div>
                                    <div class="text-[10px] text-[#8a9892]">from {{ $run->from_version }}</div>
                                    @if($run->error)
                                        <details class="mt-1">
                                            <summary class="cursor-pointer text-[10px] font-bold text-rose-600">Error details</summary>
                                            <p class="mt-1 max-w-md whitespace-pre-line text-[10px] leading-4 text-[#7a3a3a]">{{ $run->error }}</p>
                                        </details>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <span class="badge badge-{{ $run->statusTone() }}">{{ $run->statusLabel() }}</span>
                                    @if($run->step && ! $run->isFinished())
                                        <div class="mt-1 text-[10px] text-[#8a9892]">{{ $run->step }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-[11px] text-[#687771]">
                                    <div>{{ $run->started_at?->format('M j, Y H:i') ?? '—' }}</div>
                                    <div class="text-[10px] text-[#8a9892]">{{ $run->duration() ?? '' }}</div>
                                </td>
                                <td class="px-4 py-3 text-[11px] text-[#687771]">{{ $run->files_changed }}</td>
                                <td class="px-4 py-3">
                                    @if($run->backup_path)
                                        <div class="max-w-[15rem] break-all font-mono text-[10px] text-[#687771]">{{ $run->backup_path }}</div>
                                        <div class="max-w-[15rem] break-all font-mono text-[10px] text-[#8a9892]">{{ $run->database_backup_path }}</div>
                                    @else
                                        <span class="text-[11px] text-[#8a9892]">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    @if($run->isRollbackable())
                                        <details class="relative">
                                            <summary class="cursor-pointer rounded-lg border border-[#dce5e0] bg-white px-3 py-1.5 text-[11px] font-bold text-[#17231f] hover:border-[#0f766e]">Roll back</summary>
                                            <form method="post" action="{{ route('admin.updates.rollback', $run) }}" class="mt-2 w-64 rounded-xl border border-amber-200 bg-amber-50 p-3 text-[11px] leading-5 text-amber-900">
                                                @csrf
                                                <p class="font-bold">Restore {{ $run->from_version }}?</p>
                                                <p class="mt-1">This replaces the files and database with the backups taken before {{ $run->to_version }}. Orders and customers created since then will be removed.</p>
                                                <label class="mt-2 flex items-start gap-2">
                                                    <input type="checkbox" name="confirm" value="1" required class="mt-0.5">
                                                    <span>I understand and want to restore both backups.</span>
                                                </label>
                                                <button class="mt-2 w-full rounded-lg bg-amber-600 px-3 py-2 text-[11px] font-bold text-white">Restore backup</button>
                                            </form>
                                        </details>
                                    @elseif($run->status === 'rolled_back')
                                        <span class="text-[11px] font-semibold text-amber-700">Restored</span>
                                    @else
                                        <span class="text-[11px] text-[#8a9892]">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <div class="grid gap-4 md:grid-cols-3">
        <div class="card card-pad">
            <div class="text-[10px] font-bold uppercase tracking-[.14em] text-[#0f766e]">Automatic rollback</div>
            <p class="mt-2 text-xs leading-5 text-[#687771]">If a download, file copy, migration or verification step fails, ShopKeeprr restores the database and the files it replaced, then brings the storefront back.</p>
        </div>
        <div class="card card-pad">
            <div class="text-[10px] font-bold uppercase tracking-[.14em] text-[#0f766e]">Maintenance mode</div>
            <p class="mt-2 text-xs leading-5 text-[#687771]">Customers see a friendly maintenance page while the update runs, so no order can be placed halfway through a schema change. The admin update screen stays reachable.</p>
        </div>
        <div class="card card-pad">
            <div class="text-[10px] font-bold uppercase tracking-[.14em] text-[#0f766e]">Command line</div>
            <p class="mt-2 text-xs leading-5 text-[#687771]">Prefer SSH? Run <code class="rounded bg-[#f5f8f6] px-1">php artisan shopkeeprr:update</code> to check, review and install, or add <code class="rounded bg-[#f5f8f6] px-1">--check</code> to only inspect the release.</p>
        </div>
    </div>
</div>
@endsection
