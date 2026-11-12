<?php

namespace Modules\EmailMarketingHub\Models;

use Illuminate\Database\Eloquent\Model;

class EmailContact extends Model
{
    protected $table = 'email_hub_contacts';

    protected $fillable = [
        'user_id',
        'email',
        'name',
        'status',
        'source',
        'consent_at',
        'ip_address',
        'user_agent',
        'provider',
        'provider_contact_id',
        'last_synced_at',
        'last_error',
        'tags',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'consent_at' => 'datetime',
            'last_synced_at' => 'datetime',
            'tags' => 'array',
            'metadata' => 'array',
        ];
    }

    public function scopeSubscribed($query)
    {
        return $query->where('status', 'subscribed');
    }
}
