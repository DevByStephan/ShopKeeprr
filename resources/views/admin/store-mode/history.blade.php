@extends('admin.layouts.app')

@section('title', 'Store model history')
@section('eyebrow', 'System')
@section('heading', 'Store model history')

@section('content')
<div class="mb-5 flex flex-wrap items-center justify-between gap-3">
    <p class="max-w-3xl text-sm leading-6 text-[#687771]">Every completed switch is recorded with the catalogue archive/restore counts, cart reconciliation and the preflight warnings the administrator saw.</p>
    <div class="flex gap-2">
        <a href="{{ route('admin.store-mode.integrity') }}" class="btn-secondary !px-4 !py-2 text-xs">Integrity audit</a>
        <a href="{{ route('admin.settings.index', ['section' => 'mode']) }}" class="btn-secondary !px-4 !py-2 text-xs">Store settings</a>
    </div>
</div>

<div class="table-wrap">
    <table class="data-table">
        <thead><tr><th>When</th><th>Switch</th><th>Revision</th><th>Admin</th><th>Catalog</th><th>Carts</th><th>Note</th></tr></thead>
        <tbody>
            @forelse($switches as $switch)
                @php($affected = $switch->affected ?? [])
                <tr>
                    <td class="whitespace-nowrap text-xs">{{ $switch->created_at?->format('Y-m-d H:i') }}</td>
                    <td>
                        <span class="badge badge-slate">{{ $affected['from_mode'] ?? $switch->from_mode ?? 'initial' }}</span>
                        <span class="px-1 text-[#8a9892]">→</span>
                        <span class="badge badge-emerald">{{ $switch->to_mode }}</span>
                    </td>
                    <td class="text-xs font-semibold">{{ $switch->mode_revision }}</td>
                    <td class="text-xs">{{ $switch->user?->name ?? 'System' }}</td>
                    <td class="text-xs">
                        {{ $affected['catalog']['archived'] ?? 0 }} archived ·
                        {{ $affected['catalog']['restored'] ?? 0 }} restored
                    </td>
                    <td class="text-xs">
                        {{ $affected['carts']['carts'] ?? 0 }} checked ·
                        {{ $affected['carts']['invalidated'] ?? 0 }} changed
                    </td>
                    <td class="max-w-[22rem] truncate text-xs" title="{{ $switch->note }}">{{ $switch->note ?: '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="py-10 text-center text-sm text-[#8a9892]">No model switches recorded yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-5">{{ $switches->links() }}</div>
@endsection
