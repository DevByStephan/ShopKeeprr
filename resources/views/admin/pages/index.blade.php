@extends('admin.layouts.app')

@section('title', 'Pages')
@section('eyebrow', 'Operations')
@section('heading', 'Pages')

@section('content')
<div class="mb-5 flex flex-wrap items-center justify-between gap-3">
    <p class="text-sm text-[#687771]">About, shipping, returns, privacy and any other storefront content.</p>
    <a href="{{ route('admin.pages.create') }}" class="btn-primary !px-5 !py-2.5 text-xs">+ New page</a>
</div>

<div class="mb-5 grid gap-4 lg:grid-cols-[1.35fr_1fr]"
     x-data="{ show: false }">
    <div class="card card-pad">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <div class="text-[10px] font-bold uppercase tracking-[.14em] text-[#0f766e]">Required storefront pages</div>
                <h2 class="mt-1 font-display text-xl">Terms &amp; Privacy</h2>
                <p class="mt-1 max-w-xl text-xs leading-5 text-[#687771]">These pages are linked from the storefront footer and checkout. Replace the starter wording with your own policies, then keep them published.</p>
            </div>
            <span class="badge {{ $legalPages->has('terms') && $legalPages->has('privacy') ? 'badge-emerald' : 'badge-amber' }}">
                {{ $legalPages->has('terms') && $legalPages->has('privacy') ? 'Both present' : 'Action needed' }}
            </span>
        </div>
        <div class="mt-4 grid gap-2 sm:grid-cols-2">
            @foreach([['terms', 'Terms of Service'], ['privacy', 'Privacy Policy']] as [$slug, $label])
                @php($legal = $legalPages->get($slug))
                <div class="flex items-center justify-between gap-3 rounded-xl border border-[#e3e9e5] bg-[#fafcfb] px-3.5 py-3">
                    <div class="min-w-0">
                        <div class="truncate text-xs font-semibold">{{ $label }}</div>
                        <div class="mt-0.5 text-[10px] {{ $legal?->is_published ? 'text-emerald-700' : 'text-amber-700' }}">
                            {{ $legal ? ($legal->is_published ? 'Published at /pages/'.$slug : 'Draft — not visible') : 'Not created yet' }}
                        </div>
                    </div>
                    @if($legal)
                        <a href="{{ route('admin.pages.edit', $legal) }}" class="shrink-0 text-[11px] font-bold text-[#0f766e]">Edit →</a>
                    @endif
                </div>
            @endforeach
        </div>
        <div class="mt-4 flex flex-wrap items-center gap-3">
            <button type="button" @click="show = !show" class="text-[11px] font-semibold text-[#0f766e]">Restore starter policies</button>
            <span class="text-[10px] text-[#8a9892]">This replaces the current wording in both pages.</span>
        </div>
        <div x-show="show" x-cloak class="mt-3 rounded-xl border border-amber-200 bg-amber-50 p-3.5 text-xs leading-5 text-amber-900">
            <p>Restore the generic starter Terms and Privacy Policy? Any edits you made to those two pages will be replaced.</p>
            <form method="post" action="{{ route('admin.pages.legal.restore') }}" class="mt-3">
                @csrf
                <button class="rounded-lg bg-amber-700 px-3 py-2 text-[11px] font-bold text-white hover:bg-amber-800">Yes, restore starter policies</button>
            </form>
        </div>
    </div>
    <div class="card card-pad text-xs leading-5 text-[#687771]">
        <div class="text-[10px] font-bold uppercase tracking-[.14em] text-[#8a9892]">Before you publish</div>
        <p class="mt-2">These are generic starter templates, not legal advice. Add your business name, address, jurisdiction and contact details, then have a qualified professional review both pages for your products and country.</p>
        <p class="mt-2">The storefront links to <code class="rounded bg-[#f5f8f6] px-1">/pages/terms</code> and <code class="rounded bg-[#f5f8f6] px-1">/pages/privacy</code> from the footer.</p>
    </div>
</div>
<div class="table-wrap">
    <table class="data-table">
        <thead><tr><th>Title</th><th>Slug</th><th>Status</th><th>Updated</th><th></th></tr></thead>
        <tbody>
            @forelse($pages as $page)
                <tr>
                    <td class="font-semibold">{{ $page->title }}</td>
                    <td class="font-mono text-xs text-[#687771]">/pages/{{ $page->slug }}</td>
                    <td><span class="badge {{ $page->is_published ? 'badge-emerald' : 'badge-slate' }}">{{ $page->is_published ? 'Published' : 'Draft' }}</span></td>
                    <td class="text-xs">{{ $page->updated_at?->format('M j, Y') }}</td>
                    <td class="text-right">
                        <div class="flex justify-end gap-3">
                            @if($page->is_published)<a href="{{ route('storefront.page', $page->slug) }}" target="_blank" class="text-[11px] font-semibold text-[#687771] hover:text-[#0f766e]">View</a>@endif
                            <a href="{{ route('admin.pages.edit', $page) }}" class="text-[11px] font-bold text-[#0f766e]">Edit →</a>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="py-10 text-center text-sm text-[#8a9892]">No pages yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="mt-5">{{ $pages->links() }}</div>
@endsection
