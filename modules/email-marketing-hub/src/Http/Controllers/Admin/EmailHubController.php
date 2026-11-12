<?php

namespace Modules\EmailMarketingHub\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\EmailMarketingHub\EmailMarketingManager;
use Modules\EmailMarketingHub\Models\EmailContact;
use Modules\EmailMarketingHub\Models\EmailSyncLog;
use Throwable;

class EmailHubController extends Controller
{
    public function index(EmailMarketingManager $manager, SettingsService $settings): View
    {
        return view('emailhub::admin.index', [
            'providers' => $manager->providers(),
            'providerKey' => $manager->providerKey(),
            'enabled' => (bool) $settings->get('marketing.enabled', false),
            'checkoutOptIn' => (bool) $settings->get('marketing.checkout_opt_in', true),
            'stats' => $manager->stats(),
            'contacts' => EmailContact::query()->latest('consent_at')->paginate(20),
            'logs' => EmailSyncLog::query()->latest('created_at')->take(30)->get(),
            'configured' => collect($manager->providers())->map(fn ($provider) => $provider->isConfigured()),
            'settings' => $settings,
        ]);
    }

    public function save(Request $request, SettingsService $settings): RedirectResponse
    {
        $data = $request->validate([
            'enabled' => ['nullable', 'boolean'],
            'checkout_opt_in' => ['nullable', 'boolean'],
            'provider' => ['required', 'in:mailchimp,klaviyo,brevo,mailerlite,constant_contact'],
            'mailchimp_list_id' => ['nullable', 'string', 'max:100'],
            'mailchimp_server' => ['nullable', 'string', 'max:20'],
            'mailchimp_api_key' => ['nullable', 'string', 'max:500'],
            'klaviyo_list_id' => ['nullable', 'string', 'max:100'],
            'klaviyo_revision' => ['nullable', 'string', 'max:30'],
            'klaviyo_api_key' => ['nullable', 'string', 'max:500'],
            'brevo_list_id' => ['nullable', 'string', 'max:100'],
            'brevo_api_key' => ['nullable', 'string', 'max:500'],
            'mailerlite_group_id' => ['nullable', 'string', 'max:100'],
            'mailerlite_api_key' => ['nullable', 'string', 'max:500'],
            'constant_contact_list_id' => ['nullable', 'string', 'max:100'],
            'constant_contact_access_token' => ['nullable', 'string', 'max:500'],
            'clear_provider_credentials' => ['nullable', 'boolean'],
        ]);

        $settings->setMany([
            'marketing.enabled' => $request->boolean('enabled') ? '1' : '0',
            'marketing.checkout_opt_in' => $request->boolean('checkout_opt_in') ? '1' : '0',
            'marketing.provider' => $data['provider'],
            'marketing.mailchimp.list_id' => trim((string) ($data['mailchimp_list_id'] ?? '')),
            'marketing.mailchimp.server' => trim((string) ($data['mailchimp_server'] ?? '')),
            'marketing.klaviyo.list_id' => trim((string) ($data['klaviyo_list_id'] ?? '')),
            'marketing.klaviyo.revision' => trim((string) ($data['klaviyo_revision'] ?? '')) ?: '2025-01-15',
            'marketing.brevo.list_id' => trim((string) ($data['brevo_list_id'] ?? '')),
            'marketing.mailerlite.group_id' => trim((string) ($data['mailerlite_group_id'] ?? '')),
            'marketing.constant_contact.list_id' => trim((string) ($data['constant_contact_list_id'] ?? '')),
        ]);

        foreach ([
            'mailchimp.api_key' => 'mailchimp_api_key',
            'klaviyo.api_key' => 'klaviyo_api_key',
            'brevo.api_key' => 'brevo_api_key',
            'mailerlite.api_key' => 'mailerlite_api_key',
            'constant_contact.access_token' => 'constant_contact_access_token',
        ] as $setting => $field) {
            if (! empty($data[$field])) {
                $settings->set('marketing.'.$setting, trim($data[$field]), 'marketing', true);
            }
        }

        if ($request->boolean('clear_provider_credentials')) {
            foreach (['api_key', 'access_token'] as $field) {
                $settings->set('marketing.'.$data['provider'].'.'.$field, '', 'marketing', true);
            }
        }

        return back()->with('status', 'Email marketing settings saved.');
    }

    public function test(EmailMarketingManager $manager): RedirectResponse
    {
        $provider = $manager->provider();

        if (! $provider) {
            return back()->withErrors(['marketing' => 'Choose a mailing provider first.']);
        }

        try {
            $provider->testConnection();

            return back()->with('status', $provider->label().' connected. The audience and credentials look valid.');
        } catch (Throwable $exception) {
            return back()->withErrors(['marketing' => $exception->getMessage()]);
        }
    }

    public function sync(EmailMarketingManager $manager): RedirectResponse
    {
        $count = $manager->retryAll();

        if ($count === 0) {
            return back()->withErrors(['marketing' => 'Configure a provider first, then sync.']);
        }

        return back()->with('status', $count.' contact'.($count === 1 ? '' : 's').' queued for sync. Failed contacts will retry automatically.');
    }

    public function retry(EmailContact $contact, EmailMarketingManager $manager): RedirectResponse
    {
        if (! $manager->enabled()) {
            return back()->withErrors(['marketing' => 'Configure and enable a provider before retrying.']);
        }

        \Modules\EmailMarketingHub\Jobs\SyncEmailContact::dispatch($contact->id, $contact->status === 'unsubscribed' ? 'unsubscribe' : 'sync');

        return back()->with('status', $contact->email.' queued for another sync attempt.');
    }

    public function unsubscribe(EmailContact $contact, EmailMarketingManager $manager): RedirectResponse
    {
        $manager->unsubscribe($contact->email);

        return back()->with('status', $contact->email.' marked unsubscribed and queued for the provider.');
    }
}
