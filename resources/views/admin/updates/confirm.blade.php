@extends('admin.layouts.app')

@section('title', 'Install '.$release->version)
@section('eyebrow', 'System · Updates')
@section('heading', 'Review update '.$release->version)

@section('content')
@php($blocking = $plan->hasBlockingWarnings())

<div class="space-y-5">
    <div>
        <a href="{{ route('admin.updates.index') }}" class="inline-flex items-center gap-1.5 text-[11px] font-bold text-[#0f766e] hover:underline">
            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 12H5M11 18l-6-6 6-6"/></svg>
            Back to updates
        </a>
    </div>

    @if(session('update_error'))
        <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900">{{ session('update_error') }}</div>
    @endif

    <div class="grid gap-5 xl:grid-cols-[1.4fr_1fr]">
        <div class="space-y-5">
            <section class="card card-pad">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <div class="text-[10px] font-bold uppercase tracking-[.14em] text-[#0f766e]">Release notes</div>
                        <h2 class="mt-1 font-display text-3xl">{{ $release->version }}</h2>
                        <p class="mt-1 text-xs text-[#687771]">
                            Released {{ $release->releasedAt ?: 'recently' }} · {{ $release->changedFileCount() }} files · {{ $release->humanSize() }}
                            @if($release->removedFiles !== [])
                                · {{ count($release->removedFiles) }} removed
                            @endif
                        </p>
                    </div>
                    <span class="badge badge-emerald">Signature verified</span>
                </div>

                @if($release->highlights !== [])
                    <div class="mt-5 rounded-xl border border-[#dcece6] bg-[#f4fbf8] p-4">
                        <div class="text-[10px] font-bold uppercase tracking-[.14em] text-[#0f766e]">What's new</div>
                        <ul class="mt-2 space-y-1.5 text-xs leading-5 text-[#3f4f49]">
                            @foreach($release->highlights as $highlight)
                                <li class="flex gap-2"><span class="mt-1.5 h-1 w-1 shrink-0 rounded-full bg-[#0f766e]"></span><span>{{ $highlight }}</span></li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="prose-nexus mt-5 max-w-none text-sm leading-6 text-[#3f4f49]">
                    {!! nexus_page_content($release->notes) !!}
                </div>
            </section>

            @if(count($plan->releases) > 1)
                <section class="card card-pad">
                    <div class="text-[10px] font-bold uppercase tracking-[.14em] text-[#8a9892]">Upgrade chain</div>
                    <h2 class="mt-1 font-display text-2xl">This update installs {{ count($plan->releases) }} releases in order</h2>
                    <div class="mt-4 space-y-3">
                        @foreach($plan->releases as $step)
                            <div class="rounded-xl border {{ $step->version === $release->version ? 'border-[#0f766e] bg-[#f4fbf8]' : 'border-[#e3e9e5] bg-[#fafcfb]' }} p-3.5">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <div class="text-sm font-bold text-[#17231f]">{{ $step->version }}</div>
                                    <span class="text-[10px] text-[#8a9892]">{{ $step->changedFileCount() }} files · {{ $step->humanSize() }}</span>
                                </div>
                                @if(trim($step->notes) !== '')
                                    <p class="mt-1.5 line-clamp-3 whitespace-pre-line text-[11px] leading-5 text-[#687771]">{{ \Illuminate\Support\Str::limit(trim($step->notes), 260) }}</p>
                                @endif
                            </div>
                        @endforeach
                    </div>
                    <p class="mt-3 text-[11px] leading-5 text-[#8a9892]">Each step gets its own backup and migration run. If a later step fails, the store is restored to the previous successful step.</p>
                </section>
            @endif

            @if($plan->warnings !== [])
                <section class="card card-pad">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <div class="text-[10px] font-bold uppercase tracking-[.14em] text-[#8a9892]">Compatibility check</div>
                            <h2 class="mt-1 font-display text-2xl">Installed modules</h2>
                        </div>
                        <span class="badge {{ $blocking ? 'badge-rose' : 'badge-amber' }}">{{ $blocking ? 'Acknowledgement required' : 'Review before installing' }}</span>
                    </div>
                    <div class="mt-4 space-y-2">
                        @foreach($plan->warnings as $warning)
                            @php($severity = $warning['severity'] ?? 'notice')
                            <div class="rounded-xl border px-3.5 py-3 text-xs leading-5
                                {{ $severity === 'blocking' ? 'border-rose-200 bg-rose-50 text-rose-900' : ($severity === 'warning' ? 'border-amber-200 bg-amber-50 text-amber-900' : 'border-[#e3e9e5] bg-[#fafcfb] text-[#52625c]') }}">
                                <strong class="font-bold uppercase tracking-wide">{{ $severity === 'blocking' ? 'Blocking' : ($severity === 'warning' ? 'Warning' : 'Note') }}</strong>
                                — {{ $warning['message'] }}
                            </div>
                        @endforeach
                    </div>
                    <p class="mt-3 text-[11px] leading-5 text-[#8a9892]">Your module files and database rows are never modified by a core update. If a module is incompatible, disable it before installing or ask its author for a release that supports {{ $release->version }}.</p>
                </section>
            @endif
        </div>

        <div class="space-y-5">
            <section class="card card-pad">
                <div class="text-[10px] font-bold uppercase tracking-[.14em] text-[#0f766e]">Before you install</div>
                <h2 class="mt-1 font-display text-2xl">What will happen</h2>
                <ol class="mt-4 space-y-3 text-xs leading-5 text-[#687771]">
                    <li class="flex gap-3"><span class="grid h-5 w-5 shrink-0 place-items-center rounded-full bg-[#e7f5f0] text-[10px] font-bold text-[#0f766e]">1</span><span>The package is downloaded and checked against the signed SHA-256 hash and RSA-SHA256 release signature.</span></li>
                    <li class="flex gap-3"><span class="grid h-5 w-5 shrink-0 place-items-center rounded-full bg-[#e7f5f0] text-[10px] font-bold text-[#0f766e]">2</span><span>The storefront enters maintenance mode. Customers cannot place orders while files change.</span></li>
                    <li class="flex gap-3"><span class="grid h-5 w-5 shrink-0 place-items-center rounded-full bg-[#e7f5f0] text-[10px] font-bold text-[#0f766e]">3</span><span>A full database backup and a backup of every file being replaced are written to <code class="rounded bg-[#f5f8f6] px-1">storage/app/updates/backups</code>.</span></li>
                    <li class="flex gap-3"><span class="grid h-5 w-5 shrink-0 place-items-center rounded-full bg-[#e7f5f0] text-[10px] font-bold text-[#0f766e]">4</span><span>Files are replaced, migrations run in a clean process where available, and the installation is verified.</span></li>
                    <li class="flex gap-3"><span class="grid h-5 w-5 shrink-0 place-items-center rounded-full bg-[#e7f5f0] text-[10px] font-bold text-[#0f766e]">5</span><span>Maintenance mode is cleared and the storefront returns with your orders, customers, settings, themes and modules intact.</span></li>
                </ol>
            </section>

            <section class="card card-pad !border-amber-200 !bg-[#fffbf2]">
                <div class="flex items-start gap-3">
                    <svg class="mt-0.5 h-5 w-5 shrink-0 text-amber-600" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M12 9v4M12 17h.01"/><path d="M10.3 3.9 2.7 18a2 2 0 0 0 1.8 3h15a2 2 0 0 0 1.8-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/></svg>
                    <div>
                        <div class="text-xs font-bold text-amber-900">Rollback window</div>
                        <p class="mt-1 text-[11px] leading-5 text-amber-900/80">If the update fails, both backups are restored automatically. A manual rollback is also available from the Updates screen, but it restores the database to before this update — orders placed afterwards are removed. Keep the current backup until you are satisfied with the release.</p>
                    </div>
                </div>
            </section>

            <form method="post" action="{{ route('admin.updates.apply') }}" class="card card-pad">
                @csrf
                <input type="hidden" name="version" value="{{ $release->version }}">

                <div class="grid grid-cols-2 gap-3 text-[11px]">
                    <div class="rounded-lg border border-[#e3e9e5] bg-[#fafcfb] px-3 py-2">
                        <div class="font-bold uppercase tracking-[.1em] text-[#8a9892]">From</div>
                        <div class="mt-0.5 font-mono text-xs text-[#17231f]">{{ $currentVersion }}</div>
                    </div>
                    <div class="rounded-lg border border-[#e3e9e5] bg-[#fafcfb] px-3 py-2">
                        <div class="font-bold uppercase tracking-[.1em] text-[#8a9892]">To</div>
                        <div class="mt-0.5 font-mono text-xs text-[#17231f]">{{ $plan->targetVersion() }}</div>
                    </div>
                </div>

                @if($blocking)
                    <label class="mt-4 flex items-start gap-2 rounded-xl border border-rose-200 bg-rose-50 p-3 text-[11px] leading-5 text-rose-900">
                        <input type="checkbox" name="acknowledge_modules" value="1" required class="mt-0.5">
                        <span>I understand that one or more installed modules declare that they are incompatible with this release, and I accept the risk.</span>
                    </label>
                @endif

                <label class="mt-4 flex items-start gap-2 text-[11px] leading-5 text-[#687771]">
                    <input type="checkbox" name="accepted" value="1" required class="mt-0.5">
                    <span>I have read the release notes. I understand that ShopKeeprr will take a database and file backup, enable maintenance mode, and that rolling back later restores the database to this moment.</span>
                </label>

                <button class="btn-primary mt-4 w-full !py-2.5 text-xs">
                    Install {{ $plan->targetVersion() }} now
                </button>
                <p class="mt-2 text-center text-[10px] leading-4 text-[#8a9892]">The page may take a minute while the backup and migrations run. Do not close it.</p>
            </form>

            <div class="card card-pad">
                <div class="text-[10px] font-bold uppercase tracking-[.14em] text-[#8a9892]">Backup location</div>
                <p class="mt-2 break-all font-mono text-[11px] leading-5 text-[#17231f]">storage/app/updates/backups</p>
                <p class="mt-2 text-[11px] leading-5 text-[#687771]">Each run keeps <code class="rounded bg-[#f5f8f6] px-1">files.zip</code>, <code class="rounded bg-[#f5f8f6] px-1">backup.json</code> and a database dump. The newest {{ (int) config('nexuscart.updates.backup_keep', 5) }} runs are retained.</p>
            </div>
        </div>
    </div>
</div>
@endsection
