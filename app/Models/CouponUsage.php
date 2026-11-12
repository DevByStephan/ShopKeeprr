<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CouponUsage extends Model
{
    protected $fillable = ['coupon_id', 'user_id', 'order_id', 'email', 'amount'];

    protected function casts(): array
    {
        return ['amount' => 'float'];
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }
}
