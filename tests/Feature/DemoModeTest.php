<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoModeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(SettingsService::class)->setMany([
            'store.name' => 'Demo Store',
            'store.email' => 'demo@example.test',
            'store.live' => true,
            'store.mode' => 'retail',
            'store.setup_completed' => true,
        ]);
    }

    public function test_demo_payments_screen_shows_locked_gateway_fields(): void
    {
        config([
            'nexuscart.demo.enabled' => true,
            'nexuscart.demo.label' => 'v1.1 Demo',
        ]);

        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)
            ->get(route('admin.settings.index', ['section' => 'payments']));

        $response->assertOk();
        $response->assertSee('v1.1 Demo');
        $response->assertSee('payment gateways are read-only');
        $response->assertSee('Save invoice settings');
        $response->assertSee('Webhook endpoints');
        $response->assertSee(url('/webhooks/stripe'));
        $response->assertSee(url('/webhooks/paypal'));

        $html = (string) $response->getContent();

        foreach ([
            'stripe_enabled',
            'stripe_mode',
            'stripe_publishable_key',
            'stripe_secret_key',
            'stripe_webhook_secret',
            'paypal_enabled',
            'paypal_mode',
            'paypal_client_id',
            'paypal_client_secret',
            'paypal_webhook_id',
        ] as $field) {
            $this->assertMatchesRegularExpression(
                '/<(?:input|select)[^>]*name="'.preg_quote($field, '/').'"[^>]*\bdisabled\b[^>]*>/',
                $html,
                "Expected the {$field} control to be disabled in the demo build."
            );
        }
    }

    public function test_demo_mode_ignores_gateway_changes_but_saves_invoice_settings(): void
    {
        config([
            'nexuscart.demo.enabled' => true,
            'nexuscart.demo.label' => 'v1.1 Demo',
        ]);

        $settings = app(SettingsService::class);

        $settings->setMany([
            'payments.stripe.enabled' => false,
            'payments.stripe.mode' => 'test',
            'payments.stripe.publishable_key' => 'pk_demo_original',
            'payments.paypal.enabled' => false,
            'payments.paypal.mode' => 'sandbox',
            'payments.paypal.client_id' => 'demo-client-original',
            'payments.manual.enabled' => false,
            'payments.manual.instructions' => 'Original invoice note.',
        ]);

        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->patch(route('admin.settings.update'), [
            'section' => 'payments',
            'stripe_enabled' => '1',
            'stripe_mode' => 'live',
            'stripe_publishable_key' => 'pk_attacker',
            'stripe_secret_key' => 'sk_attacker',
            'stripe_webhook_secret' => 'whsec_attacker',
            'paypal_enabled' => '1',
            'paypal_mode' => 'live',
            'paypal_client_id' => 'attacker-client',
            'paypal_client_secret' => 'attacker-secret',
            'paypal_webhook_id' => 'attacker-webhook',
            'manual_enabled' => '1',
            'manual_instructions' => 'Pay by bank transfer to DEMO-123.',
        ]);

        $response->assertRedirect(route('admin.settings.index', ['section' => 'payments']));
        $response->assertSessionHas('status', fn (string $status): bool => str_contains($status, 'locked'));

        $settings->flushCache();

        $this->assertFalse((bool) $settings->get('payments.stripe.enabled'));
        $this->assertSame('test', $settings->get('payments.stripe.mode'));
        $this->assertSame('pk_demo_original', $settings->get('payments.stripe.publishable_key'));
        $this->assertSame('', $settings->get('payments.stripe.secret_key'));
        $this->assertSame('', $settings->get('payments.stripe.webhook_secret'));

        $this->assertFalse((bool) $settings->get('payments.paypal.enabled'));
        $this->assertSame('sandbox', $settings->get('payments.paypal.mode'));
        $this->assertSame('demo-client-original', $settings->get('payments.paypal.client_id'));
        $this->assertSame('', $settings->get('payments.paypal.client_secret'));
        $this->assertSame('', $settings->get('payments.paypal.webhook_id'));

        $this->assertTrue((bool) $settings->get('payments.manual.enabled'));
        $this->assertSame('Pay by bank transfer to DEMO-123.', $settings->get('payments.manual.instructions'));
    }

    public function test_gateway_settings_are_editable_without_demo_mode(): void
    {
        config(['nexuscart.demo.enabled' => false]);

        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->patch(route('admin.settings.update'), [
            'section' => 'payments',
            'stripe_enabled' => '1',
            'stripe_mode' => 'live',
            'stripe_publishable_key' => 'pk_live_new',
            'stripe_secret_key' => 'sk_live_new',
            'stripe_webhook_secret' => 'whsec_live_new',
            'paypal_enabled' => '1',
            'paypal_mode' => 'live',
            'paypal_client_id' => 'live-client',
            'paypal_client_secret' => 'live-secret',
            'paypal_webhook_id' => 'live-webhook',
            'manual_enabled' => '0',
            'manual_instructions' => '',
        ]);

        $response->assertRedirect(route('admin.settings.index', ['section' => 'payments']));
        $response->assertSessionHas('status', 'Settings saved.');

        $settings = app(SettingsService::class);
        $settings->flushCache();

        $this->assertTrue((bool) $settings->get('payments.stripe.enabled'));
        $this->assertSame('live', $settings->get('payments.stripe.mode'));
        $this->assertSame('pk_live_new', $settings->get('payments.stripe.publishable_key'));
        $this->assertSame('sk_live_new', $settings->get('payments.stripe.secret_key'));
        $this->assertSame('whsec_live_new', $settings->get('payments.stripe.webhook_secret'));

        $this->assertTrue((bool) $settings->get('payments.paypal.enabled'));
        $this->assertSame('live', $settings->get('payments.paypal.mode'));
        $this->assertSame('live-client', $settings->get('payments.paypal.client_id'));
        $this->assertSame('live-secret', $settings->get('payments.paypal.client_secret'));
        $this->assertSame('live-webhook', $settings->get('payments.paypal.webhook_id'));
    }
}
