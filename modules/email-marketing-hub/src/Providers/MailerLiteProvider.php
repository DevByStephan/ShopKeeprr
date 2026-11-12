<?php

namespace Modules\EmailMarketingHub\Providers;

use Modules\EmailMarketingHub\Models\EmailContact;
use RuntimeException;

class MailerLiteProvider extends AbstractProvider
{
    public function key(): string
    {
        return 'mailerlite';
    }

    public function label(): string
    {
        return 'MailerLite';
    }

    public function capabilities(): array
    {
        return [
            'contacts' => true,
            'unsubscribe' => true,
            'events' => false,
            'tags' => true,
        ];
    }

    public function isConfigured(): bool
    {
        return $this->setting('api_key') !== '' && $this->setting('group_id') !== '';
    }

    public function testConnection(): bool
    {
        $this->requireSettings(['api_key', 'group_id']);

        $response = $this->client()->get('https://connect.mailerlite.com/api/groups/'.$this->setting('group_id'));

        if (! $response->successful()) {
            throw new RuntimeException('MailerLite rejected the group check (HTTP '.$response->status().').');
        }

        return true;
    }

    public function syncContact(EmailContact $contact): ?string
    {
        $this->requireSettings(['api_key', 'group_id']);
        $name = $this->nameParts($contact);

        $response = $this->client()->post('https://connect.mailerlite.com/api/subscribers', [
            'email' => $contact->email,
            'fields' => array_filter([
                'name' => $contact->name,
                'last_name' => $name['last'],
            ], fn ($value) => $value !== ''),
            'groups' => [$this->setting('group_id')],
            'status' => 'active',
        ]);

        if (! $response->successful()) {
            throw new RuntimeException('MailerLite could not save this contact (HTTP '.$response->status().').');
        }

        return (string) ($response->json('data.id') ?? '');
    }

    public function unsubscribe(EmailContact $contact): void
    {
        $this->requireSettings(['api_key']);

        $this->client()
            ->put('https://connect.mailerlite.com/api/subscribers/'.rawurlencode($contact->email), [
                'status' => 'unsubscribed',
            ])
            ->throw();
    }

    private function client(): \Illuminate\Http\Client\PendingRequest
    {
        return $this->request()->withToken($this->setting('api_key'));
    }
}
