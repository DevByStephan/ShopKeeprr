<?php

namespace Modules\EmailMarketingHub;

use App\Services\SettingsService;
use Illuminate\Support\Facades\Log;
use Modules\EmailMarketingHub\Jobs\SyncEmailContact;
use Modules\EmailMarketingHub\Models\EmailContact;
use Modules\EmailMarketingHub\Models\EmailSyncLog;
use Modules\EmailMarketingHub\Providers\BrevoProvider;
use Modules\EmailMarketingHub\Providers\ConstantContactProvider;
use Modules\EmailMarketingHub\Providers\EmailMarketingProvider;
use Modules\EmailMarketingHub\Providers\KlaviyoProvider;
use Modules\EmailMarketingHub\Providers\MailchimpProvider;
use Modules\EmailMarketingHub\Providers\MailerLiteProvider;
use Throwable;

class EmailMarketingManager
{
    public function __construct(private readonly SettingsService $settings)
    {
    }

    /**
     * @return array<string, EmailMarketingProvider>
     */
    public function providers(): array
    {
        return [
            'mailchimp' => app(MailchimpProvider::class),
            'klaviyo' => app(KlaviyoProvider::class),
            'brevo' => app(BrevoProvider::class),
            'mailerlite' => app(MailerLiteProvider::class),
            'constant_contact' => app(ConstantContactProvider::class),
        ];
    }

    public function provider(): ?EmailMarketingProvider
    {
        $key = (string) $this->settings->get('marketing.provider', 'mailchimp');

        return $this->providers()[$key] ?? null;
    }

    public function enabled(): bool
    {
        return $this->settings->get('marketing.enabled', false)
            && $this->provider()?->isConfigured() === true;
    }

    public function enabledForCheckout(): bool
    {
        return $this->enabled() && (bool) $this->settings->get('marketing.checkout_opt_in', true);
    }

    public function providerKey(): string
    {
        return (string) $this->settings->get('marketing.provider', 'mailchimp');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function recordConsent(array $data): ?EmailContact
    {
        $email = strtolower(trim((string) ($data['email'] ?? '')));

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        $tags = array_values(array_unique(array_filter(array_map(
            'strval',
            is_array($data['tags'] ?? null) ? $data['tags'] : []
        ))));

        $contact = EmailContact::query()->firstOrNew(['email' => $email]);
        $wasSubscribed = $contact->exists && $contact->status === 'subscribed';

        $contact->fill([
            'user_id' => $data['user_id'] ?? $contact->user_id,
            'name' => trim((string) ($data['name'] ?? $contact->name ?? '')) ?: null,
            'status' => 'subscribed',
            'source' => $data['source'] ?? 'storefront',
            'consent_at' => now(),
            'ip_address' => $data['ip'] ?? request()->ip(),
            'user_agent' => substr((string) ($data['user_agent'] ?? request()->userAgent()), 0, 500),
            'provider' => $this->providerKey(),
            'tags' => $tags ?: ($contact->tags ?? []),
            'metadata' => array_merge((array) ($contact->metadata ?? []), array_filter([
                'order_id' => $data['order_id'] ?? null,
                'consent_source' => $data['source'] ?? null,
            ], fn ($value) => $value !== null)),
        ]);

        $contact->save();

        if (! $wasSubscribed) {
            $this->log($email, 'consent', 'ok', 'Consent recorded from '.($data['source'] ?? 'storefront').'.');
        }

        if ($this->enabled()) {
            SyncEmailContact::dispatch($contact->id, 'sync');
        }

        return $contact;
    }

    public function unsubscribe(string $email): void
    {
        $email = strtolower(trim($email));
        $contact = EmailContact::query()->where('email', $email)->first();

        if (! $contact) {
            return;
        }

        $contact->forceFill(['status' => 'unsubscribed', 'last_error' => null])->save();
        $this->log($email, 'unsubscribe', 'ok', 'Marketing consent withdrawn.');

        if ($this->enabled()) {
            SyncEmailContact::dispatch($contact->id, 'unsubscribe');
        }
    }

    public function syncNow(EmailContact $contact, string $action = 'sync'): void
    {
        $provider = $this->provider();

        if (! $provider || ! $provider->isConfigured()) {
            throw new \RuntimeException('Choose and configure a mailing provider before syncing contacts.');
        }

        try {
            if ($action === 'unsubscribe' || $contact->status === 'unsubscribed') {
                $provider->unsubscribe($contact);
                $contact->forceFill(['last_synced_at' => now(), 'last_error' => null])->save();
                $this->log($contact->email, 'unsubscribe', 'ok', 'Removed at '.$provider->label().'.');

                return;
            }

            $remoteId = $provider->syncContact($contact);

            $contact->forceFill([
                'provider' => $provider->key(),
                'provider_contact_id' => $remoteId ?: $contact->provider_contact_id,
                'last_synced_at' => now(),
                'last_error' => null,
            ])->save();

            $this->log($contact->email, 'sync', 'ok', 'Synced to '.$provider->label().'.');
        } catch (Throwable $exception) {
            $contact->forceFill(['last_error' => mb_substr($exception->getMessage(), 0, 500)])->save();
            $this->log($contact->email, $action, 'failed', $exception->getMessage());

            throw $exception;
        }
    }

    public function trackEvent(string $event, array $data): void
    {
        $provider = $this->provider();

        if (! $provider || ! $provider->isConfigured() || ! $provider->capabilities()['events']) {
            return;
        }

        try {
            $provider->trackEvent($event, $data);
        } catch (Throwable $exception) {
            Log::warning('Marketing event could not be tracked.', ['event' => $event, 'error' => $exception->getMessage()]);
        }
    }

    public function retryAll(): int
    {
        if (! $this->enabled()) {
            return 0;
        }

        $count = 0;

        EmailContact::query()
            ->where('status', 'subscribed')
            ->orderBy('id')
            ->chunkById(100, function ($contacts) use (&$count): void {
                foreach ($contacts as $contact) {
                    SyncEmailContact::dispatch($contact->id, 'sync');
                    $count++;
                }
            });

        return $count;
    }

    /**
     * @return array<string, int>
     */
    public function stats(): array
    {
        return [
            'subscribed' => EmailContact::query()->where('status', 'subscribed')->count(),
            'unsubscribed' => EmailContact::query()->where('status', 'unsubscribed')->count(),
            'failed' => EmailContact::query()->whereNotNull('last_error')->count(),
            'synced' => EmailContact::query()->whereNotNull('last_synced_at')->count(),
        ];
    }

    private function log(string $email, string $action, string $status, string $message): void
    {
        EmailSyncLog::query()->create([
            'email' => $email,
            'action' => $action,
            'status' => $status,
            'provider' => $this->providerKey(),
            'message' => mb_substr($message, 0, 1000),
            'metadata' => null,
            'created_at' => now(),
        ]);
    }
}
