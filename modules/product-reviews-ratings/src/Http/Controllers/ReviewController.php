<?php

namespace Modules\Reviews\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Modules\Reviews\Models\Review;

class ReviewController extends Controller
{
    public function store(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'title' => ['nullable', 'string', 'max:140'],
            'body' => ['required', 'string', 'min:10', 'max:2500'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);

        if (Review::query()->where('product_id', $product->id)->where('user_id', $request->user()->id)->exists()) {
            return back()->withErrors(['review' => 'You have already reviewed this product.']);
        }

        $order = Order::query()
            ->where('user_id', $request->user()->id)
            ->whereIn('payment_status', ['paid', 'partially_refunded'])
            ->whereHas('items', fn ($query) => $query->where('product_id', $product->id))
            ->latest('paid_at')
            ->first();

        $photo = null;

        if ($file = $request->file('photo')) {
            $directory = public_path('uploads/reviews');
            if (! is_dir($directory)) {
                @mkdir($directory, 0775, true);
            }

            $name = Str::slug($product->name).'-'.Str::lower(Str::random(8)).'.'.$file->extension();
            $file->move($directory, $name);
            $photo = 'uploads/reviews/'.$name;
        }

        Review::query()->create([
            'product_id' => $product->id,
            'user_id' => $request->user()->id,
            'order_id' => $order?->id,
            'rating' => (int) $data['rating'],
            'title' => $data['title'] ?? null,
            'body' => $data['body'],
            'photo_path' => $photo,
            'status' => Review::STATUS_PENDING,
            'is_verified' => $order !== null,
        ]);

        return back()->with('status', 'Thank you — your review is waiting for moderation.');
    }

    public function helpful(Request $request, Review $review): RedirectResponse
    {
        abort_unless($review->status === Review::STATUS_APPROVED, 404);

        $key = 'review_helpful_'.$review->id;

        if (! $request->session()->has($key)) {
            $review->increment('helpful_count');
            $request->session()->put($key, true);
        }

        return back();
    }
}
