<?php

namespace Modules\EmailMarketingHub\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\EmailMarketingHub\EmailMarketingManager;
use Modules\EmailMarketingHub\Models\EmailContact;
use Modules\EmailMarketingHub\Models\EmailSyncLog;
use Throwable;

class SyncEmailContact implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [60, 300, 900, 3600, 7200];

    public function __construct(
        public readonly int $contactId,
        public readonly string $action = 'sync',
    ) {
    }

    public function handle(EmailMarketingManager $manager): void
    {
        $contact = EmailContact::query()->find($this->contactId);

        if (! $contact) {
            return;
        }

        $manager->syncNow($contact, $this->action);
    }

    public function failed(Throwable $exception): void
    {
        EmailSyncLog::query()->create([
            'email' => EmailContact::query()->find($this->contactId)?->email ?? 'unknown',
            'action' => $this->action,
            'status' => 'failed',
            'provider' => null,
            'message' => 'Gave up after '.$this->tries.' attempts: '.$exception->getMessage(),
            'metadata' => null,
            'created_at' => now(),
        ]);
    }
}
