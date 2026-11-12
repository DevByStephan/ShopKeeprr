<?php

namespace App\Http\Controllers\Admin;

use App\Enums\StoreMode;
use App\Http\Controllers\Controller;
use App\Models\MarketplaceConnection;
use App\Services\SettingsService;
use App\Services\StoreModeService;
use App\Services\StoreModeSwitchException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function index(SettingsService $settings): View
    {
        return view('admin.settings.index', [
            'settings' => $settings->all(),
            'modes' => StoreMode::cases(),
            'marketplaceConnection' => MarketplaceConnection::current(),
        ]);
    }

    public function update(Request $request, SettingsService $settings): RedirectResponse
    {
        $section = $request->string('section')->toString();

        if ($section === 'payments' && nexus_demo()) {
            $this->saveInvoiceSettings($request, $settings);
            $settings->flushCache();

            return redirect()
                ->route('admin.settings.index', ['section' => $section])
                ->with('status', 'Invoice settings saved. Stripe and PayPal are locked in the '.nexus_demo_label().' build.');
        }

        match ($section) {
            'general' => $this->saveGeneral($request, $settings),
            'mode' => $this->saveMode($request, $settings, app(StoreModeService::class)),
            'checkout' => $this->saveCheckout($request, $settings),
            'payments' => $this->savePayments($request, $settings),
            'mail' => $this->saveMail($request, $settings),
            'b2b' => $this->saveB2b($request, $settings),
            'subscriptions' => $this->saveSubscriptions($request, $settings),
            'marketplace' => $this->saveMarketplace($request, $settings),
            default => null,
        };

        $settings->flushCache();
        $settings->applyMailConfiguration();

        return redirect()->route('admin.settings.index', ['section' => $section])->with('status', 'Settings saved.');
    }

    private function saveGeneral(Request $request, SettingsService $settings): void
    {
        $data = $request->validate([
            'store_name' => ['required', 'string', 'max:120'],
            'store_tagline' => ['nullable', 'string', 'max:200'],
            'store_email' => ['required', 'email', 'max:190'],
            'store_phone' => ['nullable', 'string', 'max:40'],
            'store_address' => ['nullable', 'string', 'max:500'],
            'currency' => ['required', 'in:USD,EUR,GBP,CAD,AUD'],
            'store_country' => ['required', 'string', 'size:2'],
            'announcement' => ['nullable', 'string', 'max:160'],
            'hero_eyebrow' => ['nullable', 'string', 'max:80'],
            'hero_title' => ['nullable', 'string', 'max:160'],
            'hero_subtitle' => ['nullable', 'string', 'max:300'],
            'footer_text' => ['nullable', 'string', 'max:300'],
            'social_links' => ['nullable', 'string', 'max:1000'],
            'logo' => ['nullable', 'image', 'max:4096'],
        ]);

        $symbols = ['USD' => '$', 'EUR' => '€', 'GBP' => '£', 'CAD' => 'CA$', 'AUD' => 'A$'];
        $social = [];
        foreach (preg_split('/\r\n|\r|\n/', (string) ($data['social_links'] ?? '')) as $line) {
            if (str_contains($line, '=')) {
                [$label, $url] = array_map('trim', explode('=', $line, 2));
                if ($label && $url) {
                    $social[$label] = $url;
                }
            }
        }

        $values = [
            'store.name' => $data['store_name'],
            'store.tagline' => $data['store_tagline'] ?? '',
            'store.email' => $data['store_email'],
            'store.phone' => $data['store_phone'] ?? '',
            'store.address' => $data['store_address'] ?? '',
            'store.currency' => $data['currency'],
            'store.currency_symbol' => $symbols[$data['currency']],
            'store.country' => strtoupper($data['store_country']),
            'store.announcement' => $data['announcement'] ?? '',
            'store.hero_eyebrow' => $data['hero_eyebrow'] ?? '',
            'store.hero_title' => $data['hero_title'] ?? '',
            'store.hero_subtitle' => $data['hero_subtitle'] ?? '',
            'store.footer_text' => $data['footer_text'] ?? '',
            'store.social_links' => $social,
        ];

        $file = $request->file('logo');
        if ($file && $file->isValid()) {
            $directory = public_path('uploads/branding');
            if (! is_dir($directory)) {
                @mkdir($directory, 0775, true);
            }
            $filename = 'logo-'.Str::lower(Str::random(10)).'.'.$file->extension();
            $file->move($directory, $filename);
            $values['store.logo'] = 'uploads/branding/'.$filename;
        }

        $settings->setMany($values);
    }

    private function saveMode(Request $request, SettingsService $settings, StoreModeService $modes): void
    {
        $data = $request->validate([
            'mode' => ['nullable', Rule::enum(StoreMode::class)],
        ]);

        $mode = $data['mode'] ? StoreMode::from($data['mode']) : null;
        $live = $request->boolean('live');

        if ($live && ! $mode) {
            throw ValidationException::withMessages([
                'mode' => 'Choose a store model before making the storefront live.',
            ]);
        }

        $current = $settings->mode();

        if ($mode && $current !== $mode) {
            try {
                $modes->switchTo(
                    $mode,
                    $request->user(),
                    confirmed: true,
                    live: $live,
                    note: 'Changed from Store settings.',
                    ip: $request->ip(),
                    userAgent: $request->userAgent(),
                );
            } catch (StoreModeSwitchException $exception) {
                throw ValidationException::withMessages([
                    'mode' => $exception->getMessage(),
                ]);
            }

            return;
        }

        $settings->setMode($mode);
        $settings->set('store.live', $live, 'store');
        $settings->set('store.setup_completed', true, 'store');

        if ($mode === null) {
            $settings->setModeRevision($settings->modeRevision());
        }
    }

    private function saveCheckout(Request $request, SettingsService $settings): void
    {
        $data = $request->validate([
            'min_order_total' => ['nullable', 'numeric', 'min:0'],
            'order_prefix' => ['required', 'string', 'max:10', 'alpha_num'],
            'abandoned_after_hours' => ['nullable', 'integer', 'min:1', 'max:720'],
            'abandoned_retention_days' => ['nullable', 'integer', 'min:1', 'max:365'],
        ]);

        $settings->setMany([
            'checkout.guest_checkout' => $request->boolean('guest_checkout'),
            'checkout.require_phone' => $request->boolean('require_phone'),
            'checkout.min_order_total' => (float) ($data['min_order_total'] ?? 0),
            'checkout.order_prefix' => strtoupper($data['order_prefix']),
            'orders.abandoned_after_hours' => (int) ($data['abandoned_after_hours'] ?? 24),
            'orders.abandoned_retention_days' => (int) ($data['abandoned_retention_days'] ?? 30),
            'tax.prices_include_tax' => $request->boolean('prices_include_tax'),
            'tax.shipping_taxable' => $request->boolean('shipping_taxable'),
        ]);
    }

    private function saveB2b(Request $request, SettingsService $settings): void
    {
        $data = $request->validate([
            'b2b_min_order_total' => ['nullable', 'numeric', 'min:0'],
            'b2b_default_tier' => ['nullable', 'string', 'max:80'],
        ]);

        $settings->setMany([
            'b2b.hide_prices_guests' => $request->boolean('hide_prices_guests'),
            'b2b.require_approval' => $request->boolean('require_approval'),
            'b2b.min_order_total' => (float) ($data['b2b_min_order_total'] ?? 0),
            'b2b.default_tier' => $data['b2b_default_tier'] ?? '',
        ]);
    }

    private function saveSubscriptions(Request $request, SettingsService $settings): void
    {
        $data = $request->validate([
            'grace_days' => ['required', 'integer', 'min:0', 'max:30'],
        ]);

        $settings->setMany([
            'subscriptions.grace_days' => (int) $data['grace_days'],
            'subscriptions.auto_renew' => $request->boolean('auto_renew'),
        ]);
    }

    private function saveMarketplace(Request $request, SettingsService $settings): void
    {
        $data = $request->validate([
            'commission_type' => ['required', 'in:percentage,fixed,combined'],
            'commission_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'commission_fixed' => ['required', 'numeric', 'min:0', 'max:100000'],
            'payout_hold_days' => ['required', 'integer', 'min:0', 'max:365'],
            'minimum_payout' => ['required', 'numeric', 'min:0', 'max:1000000'],
        ]);

        if ($data['commission_type'] === 'fixed') {
            $data['commission_percent'] = 0;
        }

        $settings->setMany([
            'marketplace.commission_type' => $data['commission_type'],
            'marketplace.commission_percent' => (float) $data['commission_percent'],
            'marketplace.commission_fixed' => (float) $data['commission_fixed'],
            'marketplace.payout_hold_days' => (int) $data['payout_hold_days'],
            'marketplace.minimum_payout' => (float) $data['minimum_payout'],
            'marketplace.auto_payout' => $request->boolean('auto_payout'),
        ]);
    }

    /**
     * Demo builds keep the gateway configuration read-only. Only the invoice
     * option is written, so a crafted request cannot replace gateway keys.
     */
    private function saveInvoiceSettings(Request $request, SettingsService $settings): void
    {
        $settings->setMany([
            'payments.manual.enabled' => $request->boolean('manual_enabled'),
            'payments.manual.instructions' => (string) $request->input('manual_instructions', ''),
        ]);
    }

    private function savePayments(Request $request, SettingsService $settings): void
    {
        $data = $request->validate([
            'stripe_mode' => ['required', 'in:test,live'],
            'paypal_mode' => ['required', 'in:sandbox,live'],
            'stripe_secret_key' => ['nullable', 'string', 'max:500'],
            'stripe_webhook_secret' => ['nullable', 'string', 'max:500'],
            'stripe_publishable_key' => ['nullable', 'string', 'max:500'],
            'paypal_client_id' => ['nullable', 'string', 'max:500'],
            'paypal_client_secret' => ['nullable', 'string', 'max:500'],
            'paypal_webhook_id' => ['nullable', 'string', 'max:500'],
        ]);

        $settings->setMany([
            'payments.stripe.enabled' => $request->boolean('stripe_enabled'),
            'payments.stripe.mode' => $data['stripe_mode'],
            'payments.stripe.publishable_key' => $data['stripe_publishable_key'] ?? '',
            'payments.paypal.enabled' => $request->boolean('paypal_enabled'),
            'payments.paypal.mode' => $data['paypal_mode'],
            'payments.paypal.client_id' => $data['paypal_client_id'] ?? '',
            'payments.manual.enabled' => $request->boolean('manual_enabled'),
            'payments.manual.instructions' => (string) $request->input('manual_instructions', ''),
        ]);

        foreach ([
            'payments.stripe.secret_key' => 'stripe_secret_key',
            'payments.stripe.webhook_secret' => 'stripe_webhook_secret',
            'payments.paypal.client_secret' => 'paypal_client_secret',
            'payments.paypal.webhook_id' => 'paypal_webhook_id',
        ] as $settingKey => $inputKey) {
            if (! empty($data[$inputKey])) {
                $settings->set($settingKey, $data[$inputKey], 'payments', true);
            }
        }
    }

    private function saveMail(Request $request, SettingsService $settings): void
    {
        $data = $request->validate([
            'mail_mailer' => ['required', 'in:log,smtp,sendmail'],
            'mail_host' => ['nullable', 'string', 'max:255'],
            'mail_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'mail_username' => ['nullable', 'string', 'max:255'],
            'mail_password' => ['nullable', 'string', 'max:500'],
            'mail_encryption' => ['required', 'in:tls,ssl,none'],
            'mail_from_address' => ['nullable', 'email', 'max:190'],
            'mail_from_name' => ['nullable', 'string', 'max:120'],
        ]);

        $settings->setMany([
            'mail.mailer' => $data['mail_mailer'],
            'mail.host' => $data['mail_host'] ?? '127.0.0.1',
            'mail.port' => (int) ($data['mail_port'] ?? 587),
            'mail.username' => $data['mail_username'] ?? '',
            'mail.encryption' => $data['mail_encryption'],
            'mail.from_address' => $data['mail_from_address'] ?? '',
            'mail.from_name' => $data['mail_from_name'] ?? '',
        ]);

        if (! empty($data['mail_password'])) {
            $settings->set('mail.password', $data['mail_password'], 'mail', true);
        }
    }
}
