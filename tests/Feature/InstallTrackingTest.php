<?php

namespace Tests\Feature;

use App\Models\Store;
use App\Models\User;
use App\Services\SettingsService;
use App\Services\Tracking\InstallTracker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class InstallTrackingTest extends TestCase
{
    use RefreshDatabase;

    private string $trackingFile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->trackingFile = storage_path('app/test-install-tracking-'.uniqid().'.json');
        @unlink($this->trackingFile);

        config([
            'tracking.enabled' => true,
            'tracking.endpoint' => 'https://store.test/api/v1/installs',
            'tracking.file' => $this->trackingFile,
        ]);

        app(SettingsService::class)->setMany([
            'store.name' => 'Tracked Store',
            'store.mode' => 'retail',
            'theme.preset' => 'marketplace',
        ]);
    }

    protected function tearDown(): void
    {
        @unlink($this->trackingFile);

        parent::tearDown();
    }

    public function test_registration_writes_the_tracking_file_and_sends_install_details(): void
    {
        Http::fake(['https://store.test/*' => Http::response(['ok' => true], 200)]);

        $admin = User::factory()->create(['role' => 'admin', 'email' => 'owner@tracked.test']);
        $store = Store::query()->create([
            'name' => 'Tracked Store',
            'slug' => 'tracked-store',
            'email' => 'owner@tracked.test',
            'currency' => 'USD',
            'country' => 'US',
            'is_default' => true,
        ]);

        $result = app(InstallTracker::class)->register($store, $admin);

        $this->assertTrue($result['ok']);
        $this->assertFileExists($this->trackingFile);

        $file = json_decode((string) file_get_contents($this->trackingFile), true);
        $this->assertNotEmpty($file['install_id']);
        $this->assertSame('owner@tracked.test', $file['admin_email']);
        $this->assertSame('sent', $file['last_status']);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://store.test/api/v1/installs/register'
                && $request['install_id'] === json_decode((string) file_get_contents($this->trackingFile), true)['install_id']
                && $request['admin_email'] === 'owner@tracked.test'
                && $request['store_mode'] === 'retail'
                && $request['theme'] === 'marketplace'
                && $request['app_version'] === config('app.version');
        });
    }

    public function test_a_heartbeat_reuses_the_install_id(): void
    {
        Http::fake(['https://store.test/*' => Http::response(['ok' => true], 200)]);
        $admin = User::factory()->create(['role' => 'admin', 'email' => 'owner@tracked.test']);

        app(InstallTracker::class)->register(Store::default(), $admin);
        $first = json_decode((string) file_get_contents($this->trackingFile), true);

        app(InstallTracker::class)->heartbeat();
        $second = json_decode((string) file_get_contents($this->trackingFile), true);

        $this->assertSame($first['install_id'], $second['install_id']);
        Http::assertSentCount(2);
    }

    public function test_tracking_can_be_disabled_without_writing_a_file(): void
    {
        config(['tracking.enabled' => false]);

        $result = app(InstallTracker::class)->register();

        $this->assertFalse($result['ok']);
        $this->assertSame('disabled', $result['status']);
        $this->assertFileDoesNotExist($this->trackingFile);
    }
}
