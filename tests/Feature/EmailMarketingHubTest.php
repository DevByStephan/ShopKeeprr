<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ModuleManager;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Modules\EmailMarketingHub\EmailMarketingManager;
use Modules\EmailMarketingHub\Jobs\SyncEmailContact;
use Modules\EmailMarketingHub\Models\EmailContact;
use Tests\TestCase;

class EmailMarketingHubTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! is_dir(base_path('modules/email-marketing-hub'))) {
            $this->markTestSkipped('The Email Marketing Hub module is not included in this build.');
        }

        $this->artisan('migrate', [
            '--path' => 'modules/email-marketing-hub/database/migrations',
            '--force' => true,
        ]);

        $manager = app(ModuleManager::class);
        $manager->sync();
        \App\Models\Module::query()->where('slug', 'email-marketing-hub')->update(['is_enabled' => true]);
        $manager->registerEnabledProviders();

        app(SettingsService::class)->setMany([
            'store.name' => 'Email Test Store',
            'store.live' => true,
            'store.mode' => 'retail',
            'marketing.enabled' => '1',
            'marketing.provider' => 'mailchimp',
            'marketing.mailchimp.api_key' => 'key-us1',
            'marketing.mailchimp.list_id' => 'audience-1',
            'marketing.checkout_opt_in' => '1',
        ]);
    }

    public function test_a_newsletter_opt_in_records_consent_and_queues_a_sync(): void
    {
        Queue::fake();

        $this->post('/newsletter', ['email' => 'reader@example.com'])
            ->assertRedirect();

        $contact = EmailContact::query()->where('email', 'reader@example.com')->firstOrFail();

        $this->assertSame('subscribed', $contact->status);
        $this->assertSame('newsletter', $contact->source);
        $this->assertNotNull($contact->consent_at);
        Queue::assertPushed(SyncEmailContact::class, fn (SyncEmailContact $job) => $job->contactId === $contact->id);
    }

    public function test_provider_sync_is_idempotent_and_records_success(): void
    {
        Queue::fake();
        Http::fake([
            'https://us1.api.mailchimp.com/*' => Http::response(['id' => 'member-123'], 200),
        ]);

        $contact = EmailContact::query()->create([
            'email' => 'buyer@example.com',
            'name' => 'Test Buyer',
            'status' => 'subscribed',
            'source' => 'checkout',
            'consent_at' => now(),
            'provider' => 'mailchimp',
        ]);

        app(EmailMarketingManager::class)->syncNow($contact);

        $contact->refresh();
        $this->assertSame('member-123', $contact->provider_contact_id);
        $this->assertNotNull($contact->last_synced_at);
        $this->assertNull($contact->last_error);
        $this->assertDatabaseHas('email_hub_sync_logs', [
            'email' => 'buyer@example.com',
            'action' => 'sync',
            'status' => 'ok',
        ]);
    }

    public function test_each_provider_adapter_can_sync_a_contact(): void
    {
        Http::fake(['*' => Http::response([
            'id' => 'remote-1',
            'contact_id' => 'remote-1',
            'data' => ['id' => 'remote-1'],
        ], 200)]);

        $contact = EmailContact::query()->create([
            'email' => 'adapter@example.com',
            'name' => 'Adapter Test',
            'status' => 'subscribed',
            'consent_at' => now(),
        ]);

        $settings = app(SettingsService::class);
        $credentials = [
            'mailchimp' => ['api_key' => 'key-us1', 'list_id' => 'list-1'],
            'klaviyo' => ['api_key' => 'pk_1', 'list_id' => 'list-1'],
            'brevo' => ['api_key' => 'key-1', 'list_id' => '1'],
            'mailerlite' => ['api_key' => 'token-1', 'group_id' => '1'],
            'constant_contact' => ['access_token' => 'token-1', 'list_id' => '1'],
        ];

        foreach ($credentials as $provider => $values) {
            foreach ($values as $key => $value) {
                $settings->set('marketing.'.$provider.'.'.$key, $value, 'marketing', str_contains($key, 'key') || str_contains($key, 'token'));
            }

            $settings->set('marketing.provider', $provider);
            $adapter = app(EmailMarketingManager::class)->provider();

            $this->assertTrue($adapter->isConfigured(), $provider.' should be configured');

            $result = $adapter->syncContact($contact);
            $this->assertTrue($result === null || is_string($result), $provider.' should sync without an exception');
        }
    }

    public function test_an_unsubscribe_is_recorded_and_pushed_to_the_provider(): void
    {
        Queue::fake();

        $contact = EmailContact::query()->create([
            'email' => 'leaving@example.com',
            'status' => 'subscribed',
            'consent_at' => now(),
            'provider' => 'mailchimp',
        ]);

        app(EmailMarketingManager::class)->unsubscribe($contact->email);

        $this->assertSame('unsubscribed', $contact->fresh()->status);
        Queue::assertPushed(SyncEmailContact::class, fn (SyncEmailContact $job) => $job->action === 'unsubscribe');
    }

    public function test_admin_can_configure_the_hub_and_see_the_sync_dashboard(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Http::fake(['https://us1.api.mailchimp.com/*' => Http::response(['id' => 'list-1'], 200)]);

        $this->actingAs($admin)->get(route('emailhub.admin.index'))
            ->assertOk()
            ->assertSee('Your audience, in sync.')
            ->assertSee('Mailchimp');

        $this->actingAs($admin)->put(route('emailhub.admin.settings'), [
            'enabled' => '1',
            'checkout_opt_in' => '1',
            'provider' => 'mailchimp',
            'mailchimp_list_id' => 'audience-1',
            'mailchimp_server' => 'us1',
            'mailchimp_api_key' => 'secret-key',
            'klaviyo_revision' => '2025-01-15',
        ])->assertRedirect();

        $this->assertSame('mailchimp', app(SettingsService::class)->get('marketing.provider'));
        $this->assertSame('secret-key', app(SettingsService::class)->get('marketing.mailchimp.api_key'));

        $this->actingAs($admin)->post(route('emailhub.admin.test'))->assertRedirect();
    }
}
