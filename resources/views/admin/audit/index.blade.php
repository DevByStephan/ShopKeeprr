@extends('admin.layouts.app')

@section('title', 'Audit log')
@section('eyebrow', 'System')
@section('heading', 'Audit log')

@section('content')
<form method="get" class="card mb-5 flex flex-wrap items-end gap-3 p-4">
    <div class="min-w-[200px] flex-1"><label class="label" for="q">Action contains</label><input id="q" name="q" value="{{ request('q') }}" class="input" placeholder="orders.status"></div>
    <div><label class="label" for="user">User ID</label><input id="user" name="user" type="number" min="1" value="{{ request('user') }}" class="input !w-28"></div>
    <div><label class="label" for="from">From</label><input id="from" type="date" name="from" value="{{ request('from') }}" class="input"></div>
    <button class="btn-secondary !px-5 !py-2.5 text-xs">Filter</button>
</form>

<div class="table-wrap">
    <table class="data-table">
        <thead><tr><th>When</th><th>User</th><th>Action</th><th>Request</th><th>IP</th></tr></thead>
        <tbody>
            @forelse($logs as $log)
                <tr>
                    <td class="whitespace-nowrap text-xs">{{ $log->created_at?->format('M j, Y g:i a') }}</td>
                    <td class="text-xs">{{ $log->user?->name ?? 'System' }}<div class="text-[#8a9892]">{{ $log->user?->email }}</div></td>
                    <td class="font-mono text-[11px] font-semibold">{{ $log->action }}</td>
                    <td class="text-[11px] text-[#687771]"><span class="font-bold">{{ $log->method }}</span> {{ Str::limit($log->url, 60) }}</td>
                    <td class="font-mono text-[11px]">{{ $log->ip_address }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="py-10 text-center text-sm text-[#8a9892]">No activity recorded yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="mt-5">{{ $logs->links() }}</div>
@endsection
