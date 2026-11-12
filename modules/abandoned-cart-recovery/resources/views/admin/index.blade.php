@extends('admin.layouts.app')

@section('title', 'Abandoned checkouts')
@section('eyebrow', 'Plugins')
@section('heading', 'Abandoned cart recovery')

@section('content')
<div class="grid gap-4 sm:grid-cols-4">
    <div class="card card-pad"><div class="stat-label">Reminders sent</div><div class="stat-value">{{ number_format($sent) }}</div></div>
    <div class="card card-pad"><div class="stat-label">Links clicked</div><div class="stat-value">{{ number_format($clicked) }}</div></div>
    <div class="card card-pad"><div class="stat-label">Recovered</div><div class="stat-value">{{ number_format($recovered) }}</div></div>
    <div class="card card-pad"><div class="stat-label">Waiting to send</div><div class="stat-value">{{ number_format($waiting) }}</div></div>
</div>

<div class="mt-5 grid gap-5 xl:grid-cols-[1fr_1.6fr]">
    <div class="space-y-5">
        <form method="post" action="{{ route('admin.abandoned.settings') }}" class="card card-pad">
            @csrf
            @method('PATCH')
            <div class="flex items-center justify-between">
                <h2 class="font-display text-xl">Recovery schedule</h2>
                <label class="flex items-center gap-2 text-xs font-semibold"><input type="checkbox" name="enabled" value="1" class="accent-[#0f766e]" @checked($enabled)> Enabled</label>
            </div>
            <div class="mt-4 space-y-3">
                <div>
                    <label class="label" for="delay_hours">Send after</label>
                    <div class="flex items-center gap-2">
                        <input id="delay_hours" type="number" min="1" max="168" name="delay_hours" value="{{ $delayHours }}" class="input">
                        <span class="text-xs text-[#687771]">hours</span>
                    </div>
                </div>
                <div>
                    <label class="label" for="max_age_days">Stop after</label>
                    <div class="flex items-center gap-2">
                        <input id="max_age_days" type="number" min="1" max="90" name="max_age_days" value="{{ $maxAgeDays }}" class="input">
                        <span class="text-xs text-[#687771]">days</span>
                    </div>
                </div>
                <p class="text-[11px] leading-5 text-[#8a9892]">Only unpaid checkouts with an email address receive a reminder. Paid and cancelled orders are excluded automatically.</p>
                <button class="btn-primary w-full text-xs">Save schedule</button>
            </div>
        </form>

        <form method="post" action="{{ route('admin.abandoned.run') }}" class="card card-pad">
            @csrf
            <h2 class="font-display text-xl">Run now</h2>
            <p class="mt-1 text-xs leading-5 text-[#8a9892]">The scheduler runs every 30 minutes. Use this to send eligible reminders immediately after changing the schedule.</p>
            <button class="btn-secondary mt-4 w-full text-xs">Send eligible reminders</button>
        </form>
    </div>

    <div class="card card-pad">
        <h2 class="font-display text-xl">Reminder log</h2>
        <div class="mt-3 overflow-x-auto">
            <table class="data-table">
                <thead><tr><th>Order</th><th>Customer</th><th>Sent</th><th>Clicked</th><th>Status</th></tr></thead>
                <tbody>
                    @forelse($reminders as $reminder)
                        <tr>
                            <td><a href="{{ $reminder->order ? route('admin.orders.show', $reminder->order) : '#' }}" class="font-semibold text-[#0f766e]">{{ $reminder->order?->number ?? 'Deleted order' }}</a></td>
                            <td class="text-xs">{{ $reminder->email }}</td>
                            <td class="text-[11px]">{{ $reminder->sent_at?->format('M j, g:ia') }}</td>
                            <td class="text-[11px]">{{ $reminder->clicked_at?->format('M j, g:ia') ?? '—' }}</td>
                            <td><span class="badge {{ $reminder->recovered() ? 'badge-emerald' : ($reminder->clicked_at ? 'badge-blue' : 'badge-slate') }}">{{ $reminder->recovered() ? 'Recovered' : ($reminder->clicked_at ? 'Clicked' : 'Sent') }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-10 text-center text-sm text-[#8a9892]">No reminders sent yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $reminders->links() }}</div>
    </div>
</div>
@endsection
