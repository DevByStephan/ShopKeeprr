<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UpdateRun extends Model
{
    protected $fillable = [
        'from_version', 'to_version', 'status', 'step', 'channel', 'user_id',
        'package_url', 'package_sha256', 'signature_verified', 'notes',
        'backup_path', 'database_backup_path', 'database_backup_format',
        'backup_meta', 'files_changed', 'error', 'started_at', 'finished_at',
        'rolled_back_at',
    ];

    protected function casts(): array
    {
        return [
            'signature_verified' => 'boolean',
            'backup_meta' => 'array',
            'files_changed' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'rolled_back_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isRollbackable(): bool
    {
        return in_array($this->status, ['completed', 'failed', 'rollback_failed'], true)
            && filled($this->backup_path)
            && filled($this->database_backup_path);
    }

    public function isFinished(): bool
    {
        return in_array($this->status, ['completed', 'failed', 'rolled_back', 'rollback_failed'], true);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'completed' => 'Installed',
            'rolled_back' => 'Rolled back',
            'rollback_failed' => 'Rollback failed',
            'failed' => 'Failed',
            'pending' => 'Pending',
            'downloading' => 'Downloading',
            'verifying' => 'Verifying',
            'backing_up' => 'Backing up',
            'applying' => 'Applying files',
            'migrating' => 'Running migrations',
            'rolling_back' => 'Rolling back',
            default => ucfirst(str_replace('_', ' ', $this->status)),
        };
    }

    public function statusTone(): string
    {
        return match ($this->status) {
            'completed' => 'emerald',
            'rolled_back' => 'amber',
            'failed', 'rollback_failed' => 'rose',
            default => 'blue',
        };
    }

    public function duration(): ?string
    {
        if (! $this->started_at || ! $this->finished_at) {
            return null;
        }

        $seconds = max(0, $this->started_at->diffInSeconds($this->finished_at));

        return $seconds < 60
            ? $seconds.'s'
            : floor($seconds / 60).'m '.($seconds % 60).'s';
    }
}
