<?php

namespace Modules\EmailMarketingHub\Providers;

use Modules\EmailMarketingHub\Models\EmailContact;
use RuntimeException;

class BrevoProvider extends AbstractProvider
{
    public function key(): string
    {
        return 'brevo';
    }

    public function label(): string
    {
        return 'Brevo';
    }

    public function capabilities(): array
    {
        return [
            'contacts' => true,
            'unsubscribe' => true,
            'events' => true,
            'tags' => true,
        ];
    }

    public function isConfigured(): bool
    {
        return $this->setting('api_key') !== '' && $this->setting('list_id') !== '';
    }

    public function testConnection(): bool
    {
        $this->requireSettings(['api_key', 'list_id']);

        $response = $this->request()
            ->withHeaders(['api-key' => $this->setting('api_key')])
            ->get('https://api.brevo.com/v3/contacts/lists/'.$this->setting('list_id'));

        if (! $response->successful()) {
            throw new RuntimeException('Brevo rejected the list check (HTTP '.$response->status().').');
        }

        return true;
    }

    public function syncContact(EmailContact $contact): ?string
    {
        $this->requireSettings(['api_key', 'list_id']);
        $name = $this->nameParts($contact);

        $response = $this->request()
            ->withHeaders(['api-key' => $this->setting('api_key')])
            ->post('https://api.brevo.com/v3/contacts', [
                'email' => $contact->email,
                'attributes' => array_filter([
                    'FIRSTNAME' => $name['first'],
                    'LASTNAME' => $name['last'],
                    'SOURCE' => 'ShopKeeprr',
                ], fn ($value) => $value !== ''),
                'listIds' => [(int) $this->setting('list_id')],
                'updateEnabled' => true,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Brevo could not save this contact (HTTP '.$response->status().').');
        }

        return (string) ($response->json('id') ?? '');
    }

    public function unsubscribe(EmailContact $contact): void
    {
        $this->requireSettings(['api_key']);

        $this->request()
            ->withHeaders(['api-key' => $this->setting('api_key')])
            ->put('https://api.brevo.com/v3/contacts/'.rawurlencode($contact->email), [
                'emailBlacklisted' => true,
                'listIds' => [],
            ])
            ->throw();
    }

    public function trackEvent(string $event, array $data): void
    {
        $email = strtolower(trim((string) ($data['email'] ?? '')));

        if ($email === '') {
            return;
        }

        $this->request()
            ->withHeaders(['api-key' => $this->setting('api_key')])
            ->post('https://api.brevo.com/v3/events', [
                'event_name' => $event,
                'identifiers' => ['email_id' => $email],
                'event_date' => now()->toIso8601String(),
                'properties' => $data,
            ]);
    }
}
