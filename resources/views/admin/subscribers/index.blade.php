@extends('admin.layouts.app')

@section('title', 'Subscribers')
@section('eyebrow', 'Marketing')
@section('heading', 'Newsletter subscribers')

@section('content')
<div class="card">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-[#edf2ef] px-5 py-4">
        <div>
            <h2 class="font-display text-xl">The list</h2>
            <p class="mt-1 text-xs text-[#687771]">Emails collected by the newsletter section in the storefront builder.</p>
        </div>
        <form method="get" class="flex gap-2">
            <input name="q" value="{{ $search }}" class="input h-9 w-52 text-xs" placeholder="Search email">
            <button class="btn-secondary btn-sm">Search</button>
        </form>
    </div>
    @if($subscribers->isEmpty())
        <div class="px-5 py-12 text-center text-sm text-[#687771]">No subscribers yet. Add a newsletter section to the storefront.</div>
    @else
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead><tr><th>Email</th><th>Source</th><th>Subscribed</th><th>IP address</th></tr></thead>
                <tbody>
                    @foreach($subscribers as $subscriber)
                        <tr>
                            <td class="font-semibold">{{ $subscriber->email }}</td>
                            <td class="text-[#687771]">{{ $subscriber->source ?: 'storefront' }}</td>
                            <td>{{ $subscriber->subscribed_at?->format('M j, Y g:i A') ?? '—' }}</td>
                            <td class="font-mono text-[11px] text-[#8a9892]">{{ $subscriber->ip_address ?: '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="border-t border-[#edf2ef] px-5 py-3">{{ $subscribers->links() }}</div>
    @endif
</div>
@endsection
