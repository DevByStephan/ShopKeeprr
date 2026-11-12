<?php

namespace Modules\EmailMarketingHub\Providers;

use Modules\EmailMarketingHub\Models\EmailContact;
use RuntimeException;
use Throwable;

class MailchimpProvider extends AbstractProvider
{
    public function key(): string
    {
        return 'mailchimp';
    }

    public function label(): string
    {
        return 'Mailchimp';
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
        return $this->setting('api_key') !== '' && $this->setting('list_id') !== '';
    }

    public function testConnection(): bool
    {
        $this->requireSettings(['api_key', 'list_id']);

        $response = $this->request()
            ->withBasicAuth('shopkeeprr', $this->setting('api_key'))
            ->get($this->baseUrl().'/lists/'.$this->setting('list_id'));

        if (! $response->successful()) {
            throw new RuntimeException('Mailchimp rejected the audience check (HTTP '.$response->status().').');
        }

        return true;
    }

    public function syncContact(EmailContact $contact): ?string
    {
        $this->requireSettings(['api_key', 'list_id']);
        $hash = md5(strtolower($contact->email));
        $name = $this->nameParts($contact);

        $response = $this->request()
            ->withBasicAuth('shopkeeprr', $this->setting('api_key'))
            ->put($this->baseUrl().'/lists/'.$this->setting('list_id').'/members/'.$hash, [
                'email_address' => $contact->email,
                'status_if_new' => 'subscribed',
                'status' => 'subscribed',
                'merge_fields' => array_filter([
                    'FNAME' => $name['first'],
                    'LNAME' => $name['last'],
                ], fn ($value) => $value !== ''),
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Mailchimp could not save this contact (HTTP '.$response->status().').');
        }

        if (! empty($contact->tags)) {
            try {
                $this->request()
                    ->withBasicAuth('shopkeeprr', $this->setting('api_key'))
                    ->post($this->baseUrl().'/lists/'.$this->setting('list_id').'/members/'.$hash.'/tags', [
                        'tags' => collect($contact->tags)->map(fn ($tag) => ['name' => (string) $tag, 'status' => 'active'])->values()->all(),
                    ]);
            } catch (Throwable) {
                // A tag failure must not discard a successful contact sync.
            }
        }

        return (string) ($response->json('id') ?? '');
    }

    public function unsubscribe(EmailContact $contact): void
    {
        $this->requireSettings(['api_key', 'list_id']);
        $hash = md5(strtolower($contact->email));

        $this->request()
            ->withBasicAuth('shopkeeprr', $this->setting('api_key'))
            ->patch($this->baseUrl().'/lists/'.$this->setting('list_id').'/members/'.$hash, [
                'status' => 'unsubscribed',
            ])
            ->throw();
    }

    private function baseUrl(): string
    {
        $server = $this->setting('server');

        if ($server === '') {
            $parts = explode('-', $this->setting('api_key'));
            $server = $parts[1] ?? '';
        }

        $server = preg_replace('/[^a-z0-9]/i', '', $server) ?: 'us1';

        return 'https://'.$server.'.api.mailchimp.com/3.0';
    }
}
