<?php

namespace Modules\EmailMarketingHub\Providers;

use Modules\EmailMarketingHub\Models\EmailContact;
use RuntimeException;

class ConstantContactProvider extends AbstractProvider
{
    public function key(): string
    {
        return 'constant_contact';
    }

    public function label(): string
    {
        return 'Constant Contact';
    }

    public function isConfigured(): bool
    {
        return $this->setting('access_token') !== '' && $this->setting('list_id') !== '';
    }

    public function testConnection(): bool
    {
        $this->requireSettings(['access_token', 'list_id']);

        $response = $this->client()->get('https://api.cc.email/v3/account/summary');

        if (! $response->successful()) {
            throw new RuntimeException('Constant Contact rejected the account check (HTTP '.$response->status().').');
        }

        return true;
    }

    public function syncContact(EmailContact $contact): ?string
    {
        $this->requireSettings(['access_token', 'list_id']);

        $response = $this->client()->post('https://api.cc.email/v3/contacts', [
            'email_address' => [
                'address' => $contact->email,
                'permission_to_send' => 'implicit',
            ],
            'first_name' => $this->nameParts($contact)['first'],
            'last_name' => $this->nameParts($contact)['last'],
            'list_memberships' => [$this->setting('list_id')],
            'create_source' => 'Contact',
        ]);

        if (! $response->successful()) {
            throw new RuntimeException('Constant Contact could not save this contact (HTTP '.$response->status().').');
        }

        return (string) ($response->json('contact_id') ?? '');
    }

    public function unsubscribe(EmailContact $contact): void
    {
        $this->requireSettings(['access_token']);

        if (empty($contact->provider_contact_id)) {
            throw new RuntimeException('Constant Contact needs the stored contact ID before it can unsubscribe this address.');
        }

        $this->client()
            ->put('https://api.cc.email/v3/contacts/'.$contact->provider_contact_id, [
                'email_address' => [
                    'address' => $contact->email,
                    'permission_to_send' => 'unsubscribed',
                ],
            ])
            ->throw();
    }

    private function client(): \Illuminate\Http\Client\PendingRequest
    {
        return $this->request()->withToken($this->setting('access_token'));
    }
}
