<?php

namespace Modules\Reviews\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Reviews\Models\Review;

class ReviewController extends Controller
{
    public function index(Request $request): View
    {
        $query = Review::query()->with(['product', 'user'])->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        return view('reviews::admin.index', [
            'reviews' => $query->paginate(20)->withQueryString(),
            'counts' => [
                'pending' => Review::query()->where('status', Review::STATUS_PENDING)->count(),
                'approved' => Review::query()->where('status', Review::STATUS_APPROVED)->count(),
                'hidden' => Review::query()->where('status', Review::STATUS_HIDDEN)->count(),
            ],
        ]);
    }

    public function update(Request $request, Review $review): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in([Review::STATUS_PENDING, Review::STATUS_APPROVED, Review::STATUS_HIDDEN])],
            'admin_reply' => ['nullable', 'string', 'max:2000'],
        ]);

        $review->update([
            'status' => $data['status'],
            'admin_reply' => $data['admin_reply'] ?? null,
            'approved_at' => $data['status'] === Review::STATUS_APPROVED ? ($review->approved_at ?? now()) : null,
        ]);

        return back()->with('status', 'Review updated.');
    }

    public function destroy(Review $review): RedirectResponse
    {
        if ($review->photo_path && str_starts_with($review->photo_path, 'uploads/reviews/')) {
            $full = public_path($review->photo_path);
            if (is_file($full)) {
                @unlink($full);
            }
        }

        $review->delete();

        return back()->with('status', 'Review deleted.');
    }
}
