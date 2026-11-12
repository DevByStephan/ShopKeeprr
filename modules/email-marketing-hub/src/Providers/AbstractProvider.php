<?php

namespace Modules\EmailMarketingHub\Providers;

use App\Services\SettingsService;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Modules\EmailMarketingHub\Models\EmailContact;
use RuntimeException;

abstract class AbstractProvider implements EmailMarketingProvider
{
    public function __construct(protected readonly SettingsService $settings)
    {
    }

    public function capabilities(): array
    {
        return [
            'contacts' => true,
            'unsubscribe' => true,
            'events' => false,
            'tags' => false,
        ];
    }

    protected function setting(string $key, string $default = ''): string
    {
        return trim((string) $this->settings->get('marketing.'.$this->key().'.'.$key, $default));
    }

    protected function requireSettings(array $keys): void
    {
        foreach ($keys as $key) {
            if ($this->setting($key) === '') {
                throw new RuntimeException($this->label().' is not configured. Add the missing credentials first.');
            }
        }
    }

    protected function request(): PendingRequest
    {
        return Http::acceptJson()->asJson()->timeout(20);
    }

    protected function nameParts(EmailContact $contact): array
    {
        $parts = preg_split('/\s+/', trim((string) $contact->name)) ?: [];

        return [
            'first' => $parts[0] ?? '',
            'last' => count($parts) > 1 ? implode(' ', array_slice($parts, 1)) : '',
        ];
    }

    public function trackEvent(string $event, array $data): void
    {
        // Providers that do not expose an event API simply skip this step.
    }
}
