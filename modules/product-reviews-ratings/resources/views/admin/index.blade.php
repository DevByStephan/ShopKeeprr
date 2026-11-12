@extends('admin.layouts.app')

@section('title', 'Product reviews')
@section('eyebrow', 'Plugins')
@section('heading', 'Reviews')

@section('content')
<div class="mb-5 flex flex-wrap items-center justify-between gap-3">
    <div class="flex flex-wrap gap-2">
        @foreach(['' => 'All', 'pending' => 'Pending', 'approved' => 'Approved', 'hidden' => 'Hidden'] as $value => $label)
            <a href="{{ route('admin.reviews.index', $value ? ['status' => $value] : []) }}"
               class="rounded-full px-4 py-2 text-xs font-bold {{ (string) request('status') === (string) $value ? 'bg-[#0f766e] text-white' : 'border border-[#e3e9e5] bg-white text-[#687771] hover:border-[#0f766e]' }}">
                {{ $label }}@if($value === 'pending' && $counts['pending'])<span class="ml-1 rounded-full bg-white/20 px-1.5">{{ $counts['pending'] }}</span>@endif
            </a>
        @endforeach
    </div>
    <div class="text-xs text-[#8a9892]">{{ $counts['approved'] }} published · {{ $counts['pending'] }} waiting</div>
</div>

<div class="space-y-4">
    @forelse($reviews as $review)
        <div class="card card-pad">
            <div class="grid gap-5 lg:grid-cols-[1fr_300px]">
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-sm tracking-[.08em] text-amber-500">@for($i = 1; $i <= 5; $i++){{ $i <= $review->rating ? '★' : '☆' }}@endfor</span>
                        <a href="{{ $review->product ? route('admin.products.edit', $review->product) : '#' }}" class="font-semibold text-[#0f766e] hover:underline">{{ $review->product?->name ?? 'Deleted product' }}</a>
                        @if($review->is_verified)<span class="badge badge-emerald">Verified</span>@endif
                        <span class="badge {{ $review->status === 'approved' ? 'badge-emerald' : ($review->status === 'pending' ? 'badge-amber' : 'badge-slate') }}">{{ ucfirst($review->status) }}</span>
                    </div>
                    <div class="mt-1 text-[11px] text-[#8a9892]">{{ $review->user?->name ?? 'Deleted account' }} · {{ $review->created_at?->format('M j, Y g:ia') }}</div>
                    @if($review->title)<div class="mt-3 text-sm font-bold">{{ $review->title }}</div>@endif
                    <p class="mt-1 whitespace-pre-line text-sm leading-6 text-[#4c5a54]">{{ $review->body }}</p>
                    @if($review->photoUrl())
                        <a href="{{ $review->photoUrl() }}" target="_blank"><img src="{{ $review->photoUrl() }}" alt="" class="mt-3 h-24 w-24 rounded-lg object-cover"></a>
                    @endif
                </div>
                <form method="post" action="{{ route('admin.reviews.update', $review) }}" class="space-y-3 rounded-xl border border-[#e3e9e5] bg-[#fafcfb] p-4">
                    @csrf
                    @method('PATCH')
                    <div>
                        <label class="label" for="status-{{ $review->id }}">Status</label>
                        <select id="status-{{ $review->id }}" name="status" class="select">
                            @foreach(['pending' => 'Pending', 'approved' => 'Approved', 'hidden' => 'Hidden'] as $value => $label)
                                <option value="{{ $value }}" @selected($review->status === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="label" for="reply-{{ $review->id }}">Public reply</label>
                        <textarea id="reply-{{ $review->id }}" name="admin_reply" rows="3" class="textarea">{{ $review->admin_reply }}</textarea>
                    </div>
                    <button class="btn-primary w-full !py-2 text-xs">Save review</button>
                </form>
            </div>
            <div class="mt-3 flex justify-end border-t border-[#e3e9e5] pt-3">
                <form method="post" action="{{ route('admin.reviews.destroy', $review) }}" onsubmit="return confirm('Delete this review permanently?')">
                    @csrf
                    @method('DELETE')
                    <button class="text-[11px] font-bold text-rose-600">Delete review</button>
                </form>
            </div>
        </div>
    @empty
        <div class="card card-pad py-12 text-center text-sm text-[#8a9892]">No reviews in this view.</div>
    @endforelse
</div>

<div class="mt-5">{{ $reviews->links() }}</div>
@endsection
