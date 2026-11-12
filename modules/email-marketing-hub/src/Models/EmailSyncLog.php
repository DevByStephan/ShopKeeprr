<?php

namespace Modules\EmailMarketingHub\Models;

use Illuminate\Database\Eloquent\Model;

class EmailSyncLog extends Model
{
    protected $table = 'email_hub_sync_logs';

    public $timestamps = false;

    protected $fillable = [
        'email',
        'action',
        'status',
        'provider',
        'message',
        'metadata',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
