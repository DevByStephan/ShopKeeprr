<?php

namespace Modules\AbandonedCart\Models;

use App\Models\Order;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reminder extends Model
{
    protected $table = 'abandoned_cart_reminders';

    protected $fillable = ['order_id', 'email', 'sent_at', 'clicked_at'];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'clicked_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function recovered(): bool
    {
        return (bool) $this->order?->isPaid();
    }
}
