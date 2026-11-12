<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class InstallWizardTrackingTest extends TestCase
{
    use RefreshDatabase;

    private string $trackingFile;

    private string $lockPath;

    private ?string $lockBackup = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->trackingFile = storage_path('app/test-install-wizard-'.uniqid().'.json');
        @unlink($this->trackingFile);

        $this->lockPath = storage_path('app/installed.lock');
        $this->lockBackup = is_file($this->lockPath) ? (string) file_get_contents($this->lockPath) : null;
        @unlink($this->lockPath);

        config([
            'app.url' => 'https://tracked.example',
            'tracking.enabled' => true,
            'tracking.endpoint' => 'https://store.test/api/v1/installs',
            'tracking.file' => $this->trackingFile,
        ]);

        Http::fake(['https://store.test/*' => Http::response(['ok' => true], 200)]);
    }

    protected function tearDown(): void
    {
        @unlink($this->trackingFile);

        if ($this->lockBackup !== null) {
            file_put_contents($this->lockPath, $this->lockBackup);
        } else {
            @unlink($this->lockPath);
        }

        parent::tearDown();
    }

    public function test_the_installer_creates_the_tracking_file_and_registers_the_site(): void
    {
        $payload = [
            'store' => [
                'name' => 'Tracked Install',
                'email' => 'owner@tracked.test',
                'mode' => 'retail',
                'live' => false,
            ],
            'admin' => [
                'name' => 'Store Owner',
                'email' => 'owner@tracked.test',
                'password' => 'StrongPassword!2026',
            ],
            'demo' => false,
        ];

        $path = tempnam(sys_get_temp_dir(), 'nexus-install-').'.json';
        file_put_contents($path, json_encode($payload));

        $this->artisan('nexus:install', ['--data' => $path, '--force' => true])
            ->assertSuccessful();

        $this->assertFileExists($this->trackingFile);

        $tracking = json_decode((string) file_get_contents($this->trackingFile), true);

        $this->assertNotEmpty($tracking['install_id']);
        $this->assertSame('owner@tracked.test', $tracking['admin_email']);
        $this->assertSame('sent', $tracking['last_status']);
        $this->assertSame('Tracked Install', app(\App\Services\SettingsService::class)->get('store.name'));

        Http::assertSent(function ($request) use ($tracking) {
            return $request->url() === 'https://store.test/api/v1/installs/register'
                && $request['install_id'] === $tracking['install_id']
                && $request['domain'] === 'tracked.example'
                && $request['admin_email'] === 'owner@tracked.test'
                && $request['store_mode'] === 'retail';
        });
    }

    public function test_a_fresh_install_can_start_in_multi_vendor_mode_with_demo_sellers(): void
    {
        $payload = [
            'store' => [
                'name' => 'Marketplace Demo',
                'email' => 'owner@market.test',
                'mode' => 'multivendor',
                'live' => true,
            ],
            'admin' => [
                'name' => 'Store Owner',
                'email' => 'owner@market.test',
                'password' => 'StrongPassword!2026',
            ],
            'demo' => true,
        ];

        $path = tempnam(sys_get_temp_dir(), 'nexus-install-').'.json';
        file_put_contents($path, json_encode($payload));

        $this->artisan('nexus:install', ['--data' => $path, '--force' => true])
            ->assertSuccessful();

        $this->assertSame('multivendor', app(\App\Services\SettingsService::class)->get('store.mode'));
        $this->assertSame(3, \App\Models\Vendor::query()->count());
        $this->assertGreaterThan(0, \App\Models\Product::query()->whereNotNull('vendor_id')->count());
    }
}
