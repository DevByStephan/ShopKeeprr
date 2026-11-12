<?php

namespace Modules\EmailMarketingHub\Listeners;

use Modules\EmailMarketingHub\EmailMarketingManager;

class RecordMarketingConsent
{
    public function handle(array $payload = []): void
    {
        if (empty($payload['email']) || empty($payload['consent'])) {
            return;
        }

        app(EmailMarketingManager::class)->recordConsent($payload);
    }
}
