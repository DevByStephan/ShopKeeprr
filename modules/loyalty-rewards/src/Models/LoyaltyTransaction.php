<?php

namespace Modules\Loyalty\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoyaltyTransaction extends Model
{
    protected $table = 'loyalty_transactions';

    protected $fillable = ['user_id', 'points', 'type', 'description', 'order_id', 'coupon_id'];

    protected function casts(): array
    {
        return [
            'points' => 'integer',
            'order_id' => 'integer',
            'coupon_id' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
