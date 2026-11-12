<?php

namespace Modules\Reviews\Models;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Review extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_HIDDEN = 'hidden';

    protected $table = 'product_reviews';

    protected $fillable = [
        'product_id', 'user_id', 'order_id', 'rating', 'title', 'body', 'photo_path',
        'status', 'is_verified', 'helpful_count', 'admin_reply', 'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'order_id' => 'integer',
            'is_verified' => 'boolean',
            'helpful_count' => 'integer',
            'approved_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function photoUrl(): ?string
    {
        return $this->photo_path ? asset($this->photo_path) : null;
    }

    public function customerName(): string
    {
        $name = trim((string) $this->user?->name) ?: 'Verified customer';
        $parts = preg_split('/\s+/', $name) ?: [$name];

        return $parts[0].(isset($parts[1]) ? ' '.mb_substr($parts[1], 0, 1).'.' : '');
    }
}
