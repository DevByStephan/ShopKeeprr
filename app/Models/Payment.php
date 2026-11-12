<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = [
        'order_id', 'subscription_id', 'gateway', 'gateway_reference', 'gateway_payment_id',
        'amount', 'currency', 'status', 'payload', 'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'float',
            'payload' => 'array',
            'paid_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }
}
