@extends('admin.layouts.app')

@section('title', 'Plugins')
@section('eyebrow', 'Storefront')
@section('heading', 'Plugins & modules')

@section('content')
<div class="grid gap-5 xl:grid-cols-[1.5fr_1fr]">
    <div class="space-y-4">
        @forelse($modules as $module)
            <div class="card p-5">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="font-display text-xl">{{ $module->name }}</h2>
                            <span class="badge badge-slate">{{ $module->type?->value ?? 'utility' }}</span>
                            <span class="badge {{ $module->is_enabled ? 'badge-emerald' : 'badge-slate' }}">{{ $module->is_enabled ? 'Enabled' : 'Disabled' }}</span>
                        </div>
                        <p class="mt-1 max-w-xl text-sm leading-6 text-[#687771]">{{ $module->description ?: 'No description provided.' }}</p>
                        <div class="mt-2 text-[11px] text-[#8a9892]">v{{ $module->version }} · {{ $module->slug }}</div>
                    </div>
                    <div class="flex items-center gap-2">
                        @if(\Illuminate\Support\Facades\Route::has($module->slug.'.admin.index'))
                            <a href="{{ route($module->slug.'.admin.index') }}" class="btn-secondary !px-4 !py-2 text-xs">Manage</a>
                        @endif
                        <form method="post" action="{{ route('admin.plugins.toggle', $module) }}">
                            @csrf
                            @method('PATCH')
                            <button class="btn-secondary !px-4 !py-2 text-xs">{{ $module->is_enabled ? 'Disable' : 'Enable' }}</button>
                        </form>
                        <form method="post" action="{{ route('admin.plugins.destroy', $module) }}" onsubmit="return confirm('Remove this module and its files?')">
                            @csrf
                            @method('DELETE')
                            <button class="text-xs font-semibold text-rose-600">Remove</button>
                        </form>
                    </div>
                </div>
            </div>
        @empty
            <div class="card card-pad text-center">
                <div class="mx-auto grid h-12 w-12 place-items-center rounded-xl bg-[#e7f5f0] text-[#0f766e]">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path d="M9 4h6v3a2 2 0 1 0 4 0h1v6h-3a2 2 0 1 0 0 4v3H9v-3a2 2 0 1 0-4 0H4v-6h3a2 2 0 1 0 0-4H4V4h5Z"/></svg>
                </div>
                <h2 class="mt-4 font-display text-2xl">No modules installed</h2>
                <p class="mt-2 text-sm text-[#687771]">Drop in payment processors, marketing tools and other extensions as separate modules. The core checkout never has to change.</p>
            </div>
        @endforelse
    </div>

    <div class="space-y-5">
        <div class="card card-pad !border-amber-300 !bg-[#fff8ec]">
            <h2 class="font-display text-lg text-[#7a4c07]">Modules are trusted code</h2>
            <p class="mt-2 text-xs leading-5 text-[#7a5a1e]">
                A module is not sandboxed. It runs with the same access as ShopKeeprr itself: it can read or change your database, files and customer data, and any migrations bundled in the package run automatically during installation.
                Only install modules from authors you trust, and take a database backup first.
            </p>
        </div>

        <form method="post" action="{{ route('admin.plugins.upload') }}" enctype="multipart/form-data" class="card card-pad">
            @csrf
            <h2 class="font-display text-xl">Install a module</h2>
            <p class="mt-1 text-xs leading-5 text-[#687771]">Upload a ZIP containing <code class="rounded bg-[#f5f8f6] px-1">module.json</code> at its root.</p>
            <input type="file" name="module" accept=".zip" class="mt-4 block w-full text-xs file:mr-3 file:rounded-full file:border-0 file:bg-[#0f766e] file:px-4 file:py-2 file:text-xs file:font-bold file:text-white" required>
            <label class="mt-3 flex items-start gap-2 text-[11px] leading-5 text-[#687771]">
                <input type="checkbox" name="trust" value="1" required class="mt-0.5">
                <span>I trust this author. I understand the module can access my store's data and run its own migrations.</span>
            </label>
            <button class="btn-primary mt-4 w-full">Upload & install</button>
            <p class="mt-2 text-[11px] text-[#8a9892]">Modules are disabled after install until you enable them.</p>
        </form>

        <div class="card card-pad">
            <h2 class="font-display text-xl">module.json</h2>
            <pre class="mt-3 overflow-x-auto rounded-xl bg-[#102522] p-4 text-[11px] leading-5 text-[#c9e7df]">{
  "name": "Square Payments",
  "slug": "square-payments",
  "version": "1.0.0",
  "type": "payment",
  "provider": "Modules\\Square\\SquareServiceProvider",
  "namespace": "Modules\\Square\\",
  "source": "src",
  "description": "Square card payments."
}</pre>
            <p class="mt-3 text-[11px] leading-5 text-[#8a9892]">A module can ship migrations, routes, views and a service provider. Keep the slug lowercase, hyphenated and unique.</p>
        </div>
    </div>
</div>
@endsection
