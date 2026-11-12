<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoreModeSwitch extends Model
{
    protected $fillable = [
        'from_mode', 'to_mode', 'mode_revision', 'user_id', 'status', 'note', 'affected',
    ];

    protected function casts(): array
    {
        return [
            'affected' => 'array',
            'mode_revision' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
