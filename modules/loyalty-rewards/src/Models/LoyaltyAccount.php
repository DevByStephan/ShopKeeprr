<?php

namespace Modules\Loyalty\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoyaltyAccount extends Model
{
    protected $table = 'loyalty_accounts';

    protected $fillable = ['user_id', 'points_balance', 'lifetime_points'];

    protected function casts(): array
    {
        return [
            'points_balance' => 'integer',
            'lifetime_points' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
