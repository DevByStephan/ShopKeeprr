@php
    $starCount = 5;
    $fullStars = (int) round($averageRating ?? 0);
@endphp

<section id="reviews" class="mt-12 border-t border-[var(--line)] pt-10">
    <div class="grid gap-8 lg:grid-cols-[.8fr_1.2fr]">
        <div>
            <div class="text-[10px] font-bold uppercase tracking-[.18em] text-[var(--link)]">Customer reviews</div>
            <div class="mt-2 flex items-end gap-3">
                <div class="font-display text-6xl leading-none">{{ number_format((float) ($averageRating ?? 0), 1) }}</div>
                <div class="pb-1">
                    <div class="text-lg tracking-[.12em] text-amber-500">
                        @for($i = 1; $i <= $starCount; $i++){{ $i <= $fullStars ? '★' : '☆' }}@endfor
                    </div>
                    <div class="mt-0.5 text-[11px] text-[var(--muted)]">{{ $reviewCount ?? 0 }} {{ Str::plural('review', $reviewCount ?? 0) }}</div>
                </div>
            </div>
            <p class="mt-3 max-w-xs text-xs leading-6 text-[var(--muted)]">Every review is checked before it appears. The verified badge means we matched the review to a paid order.</p>

            @auth
                @if($existingReview)
                    <div class="mt-6 rounded-xl border border-[var(--line)] bg-[var(--surface-2)] p-4 text-xs leading-5 text-[var(--muted)]">
                        You reviewed this product on {{ $existingReview->created_at?->format('M j, Y') }}.
                        @if($existingReview->status === 'pending') It is waiting for moderation. @endif
                    </div>
                @else
                    <form method="post" action="{{ route('reviews.store', $product->slug) }}" enctype="multipart/form-data" class="mt-6 space-y-3 rounded-2xl border border-[var(--line)] bg-white p-5">
                        @csrf
                        <div>
                            <div class="label">Your rating</div>
                            <div class="flex gap-1.5">
                                @for($i = 1; $i <= 5; $i++)
                                    <label class="cursor-pointer">
                                        <input type="radio" name="rating" value="{{ $i }}" class="peer sr-only" @checked(old('rating') == $i) required>
                                        <span class="block text-2xl text-[#d5ddd8] transition peer-checked:text-amber-500 hover:text-amber-400">★</span>
                                    </label>
                                @endfor
                            </div>
                            @error('rating')<div class="mt-1 text-[11px] text-rose-600">{{ $message }}</div>@enderror
                        </div>
                        <div>
                            <label class="label" for="review-title">Headline <span class="normal-case tracking-normal text-[var(--muted)]">(optional)</span></label>
                            <input id="review-title" name="title" value="{{ old('title') }}" class="input" maxlength="140" placeholder="Sums it up in a few words">
                        </div>
                        <div>
                            <label class="label" for="review-body">Your review</label>
                            <textarea id="review-body" name="body" rows="4" class="textarea" required minlength="10" maxlength="2500" placeholder="What did you like or not like?">{{ old('body') }}</textarea>
                            @error('body')<div class="mt-1 text-[11px] text-rose-600">{{ $message }}</div>@enderror
                        </div>
                        <div>
                            <label class="label" for="review-photo">Add a photo <span class="normal-case tracking-normal text-[var(--muted)]">(optional)</span></label>
                            <input id="review-photo" type="file" name="photo" accept="image/*" class="block w-full text-xs file:mr-3 file:rounded-full file:border-0 file:bg-[var(--brand)] file:px-4 file:py-2 file:text-[11px] file:font-bold file:text-[var(--button-ink)]">
                        </div>
                        <button class="btn-primary w-full text-xs">Submit review</button>
                        @error('review')<div class="text-[11px] text-rose-600">{{ $message }}</div>@enderror
                    </form>
                @endif
            @else
                <div class="mt-6 rounded-xl border border-[var(--line)] bg-white p-4 text-xs text-[var(--muted)]">
                    <a href="{{ route('login') }}" class="font-bold text-[var(--link)] underline underline-offset-2">Sign in</a> to leave a review.
                </div>
            @endauth
        </div>

        <div>
            @forelse($reviews as $review)
                <article class="border-b border-[var(--line)] py-5 first:pt-0">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="text-sm tracking-[.1em] text-amber-500">@for($i = 1; $i <= 5; $i++){{ $i <= $review->rating ? '★' : '☆' }}@endfor</span>
                                @if($review->is_verified)
                                    <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-[9px] font-bold uppercase tracking-[.1em] text-emerald-700">Verified purchase</span>
                                @endif
                            </div>
                            @if($review->title)
                                <h3 class="mt-2 text-sm font-bold">{{ $review->title }}</h3>
                            @endif
                        </div>
                        <div class="text-[10px] text-[var(--muted)]">{{ $review->approved_at?->format('M j, Y') }}</div>
                    </div>
                    <p class="mt-2 text-sm leading-6 text-[var(--muted)]">{{ $review->body }}</p>
                    @if($review->photoUrl())
                        <img src="{{ $review->photoUrl() }}" alt="Customer photo" class="mt-3 h-24 w-24 rounded-lg object-cover" loading="lazy">
                    @endif
                    @if($review->admin_reply)
                        <div class="mt-3 rounded-lg border-l-2 border-[var(--brand)] bg-[var(--surface-2)] px-3 py-2 text-xs leading-5">
                            <span class="font-bold">Store reply:</span> {{ $review->admin_reply }}
                        </div>
                    @endif
                    <div class="mt-3 flex items-center justify-between text-[10px] text-[var(--muted)]">
                        <span>{{ $review->customerName() }}</span>
                        <form method="post" action="{{ route('reviews.helpful', $review) }}">
                            @csrf
                            <button class="font-semibold hover:text-[var(--link)]">Helpful ({{ $review->helpful_count }})</button>
                        </form>
                    </div>
                </article>
            @empty
                <div class="rounded-2xl border border-dashed border-[var(--line)] bg-white/60 p-8 text-center">
                    <div class="font-display text-2xl">No reviews yet.</div>
                    <p class="mt-1 text-xs text-[var(--muted)]">Be the first to share how this product worked for you.</p>
                </div>
            @endforelse
            @if(($reviewCount ?? 0) > $reviews->count())
                <p class="mt-4 text-[11px] text-[var(--muted)]">Showing the most recent {{ $reviews->count() }} of {{ $reviewCount }} reviews.</p>
            @endif
        </div>
    </div>
</section>
