<?php

namespace Modules\EmailMarketingHub\Providers;

use Modules\EmailMarketingHub\Models\EmailContact;

interface EmailMarketingProvider
{
    public function key(): string;

    public function label(): string;

    /**
     * @return array<string, bool>
     */
    public function capabilities(): array;

    public function isConfigured(): bool;

    public function testConnection(): bool;

    public function syncContact(EmailContact $contact): ?string;

    public function unsubscribe(EmailContact $contact): void;

    /**
     * @param  array<string, mixed>  $data
     */
    public function trackEvent(string $event, array $data): void;
}
