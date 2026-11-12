<?php

namespace Modules\EmailMarketingHub\Providers;

use Modules\EmailMarketingHub\Models\EmailContact;
use RuntimeException;

class KlaviyoProvider extends AbstractProvider
{
    public function key(): string
    {
        return 'klaviyo';
    }

    public function label(): string
    {
        return 'Klaviyo';
    }

    public function capabilities(): array
    {
        return [
            'contacts' => true,
            'unsubscribe' => true,
            'events' => true,
            'tags' => false,
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
            ->withHeaders($this->headers())
            ->get('https://a.klaviyo.com/api/lists/'.$this->setting('list_id'));

        if (! $response->successful()) {
            throw new RuntimeException('Klaviyo rejected the list check (HTTP '.$response->status().').');
        }

        return true;
    }

    public function syncContact(EmailContact $contact): ?string
    {
        $this->requireSettings(['api_key', 'list_id']);

        $response = $this->subscriptionJob($contact, 'SUBSCRIBED');

        if (! $response->successful()) {
            throw new RuntimeException('Klaviyo could not subscribe this contact (HTTP '.$response->status().').');
        }

        return null;
    }

    public function unsubscribe(EmailContact $contact): void
    {
        $this->requireSettings(['api_key', 'list_id']);

        $response = $this->subscriptionJob($contact, 'UNSUBSCRIBED');

        if (! $response->successful()) {
            throw new RuntimeException('Klaviyo could not unsubscribe this contact (HTTP '.$response->status().').');
        }
    }

    public function trackEvent(string $event, array $data): void
    {
        $email = strtolower(trim((string) ($data['email'] ?? '')));

        if ($email === '') {
            return;
        }

        $this->request()
            ->withHeaders($this->headers())
            ->post('https://a.klaviyo.com/api/events/', [
                'data' => [
                    'type' => 'event',
                    'attributes' => [
                        'properties' => $data,
                        'metric' => ['data' => ['type' => 'metric', 'attributes' => ['name' => $event]]],
                        'profile' => ['data' => ['type' => 'profile', 'attributes' => ['email' => $email]]],
                    ],
                ],
            ]);
    }

    private function subscriptionJob(EmailContact $contact, string $consent): \Illuminate\Http\Client\Response
    {
        return $this->request()
            ->withHeaders($this->headers())
            ->post('https://a.klaviyo.com/api/profile-subscription-bulk-create-jobs/', [
                'data' => [
                    'type' => 'profile-subscription-bulk-create-job',
                    'attributes' => [
                        'custom_source' => 'ShopKeeprr',
                        'profiles' => [[
                            'email' => $contact->email,
                            'subscriptions' => [
                                'email' => ['marketing' => ['consent' => $consent]],
                            ],
                        ]],
                    ],
                    'relationships' => [
                        'list' => ['data' => ['type' => 'list', 'id' => $this->setting('list_id')]],
                    ],
                ],
            ]);
    }

    private function headers(): array
    {
        return [
            'Authorization' => 'Klaviyo-API-Key '.$this->setting('api_key'),
            'revision' => $this->setting('revision', '2025-01-15'),
        ];
    }
}
