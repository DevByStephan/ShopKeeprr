<?php

namespace Tests\Feature;

use App\Models\Module;
use App\Models\UpdateRun;
use App\Models\User;
use App\Services\SettingsService;
use App\Services\Updates\ModuleCompatibility;
use App\Services\Updates\UpdateManager;
use App\Support\Version;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class UpdateSystemTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(SettingsService::class)->setMany([
            'store.name' => 'Update Test Store',
            'store.live' => true,
            'store.mode' => 'retail',
            'store.setup_completed' => true,
        ]);

        Cache::flush();
    }

    protected function tearDown(): void
    {
        if (app()->isDownForMaintenance()) {
            Artisan::call('up');
        }

        Cache::flush();

        parent::tearDown();
    }

    public function test_an_admin_can_open_the_updates_screen(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('admin.updates.index'))
            ->assertOk()
            ->assertSee('Check for updates')
            ->assertSee(Version::current());
    }

    public function test_a_guest_is_sent_to_the_admin_login(): void
    {
        $this->get(route('admin.updates.index'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_a_signed_manifest_produces_an_installable_plan(): void
    {
        $this->configureTestManifest();

        Http::fake([
            'https://updates.test/*' => Http::response($this->manifest(), 200),
        ]);

        $plan = app(UpdateManager::class)->check(true);

        $this->assertNull($plan->error);
        $this->assertFalse($plan->upToDate());
        $this->assertCount(1, $plan->releases);
        $this->assertSame('1.1.0', $plan->targetVersion());
        $this->assertTrue($plan->releases[0]->signaturePayload() !== '');
    }

    public function test_a_tampered_release_is_not_installable(): void
    {
        $manifest = $this->manifest();
        $manifest['releases'][0]['size'] = 999999;
        $manifest['releases'][0]['sha256'] = str_repeat('c', 64);

        $this->configureTestManifest();

        Http::fake([
            'https://updates.test/*' => Http::response($manifest, 200),
        ]);

        $plan = app(UpdateManager::class)->check(true);

        $this->assertNotNull($plan->error);
        $this->assertTrue($plan->upToDate() === false);
    }

    public function test_manifest_requires_https_for_a_remote_host(): void
    {
        config([
            'nexuscart.updates.manifest_url' => 'http://updates.example.com/manifest.json',
            'nexuscart.updates.allow_http' => false,
        ]);

        $this->expectExceptionMessage('must use HTTPS');

        app(UpdateManager::class)->check(true);
    }

    public function test_enabled_modules_with_incompatible_requirements_are_blocking(): void
    {
        Module::query()->create([
            'name' => 'Legacy Payments',
            'slug' => 'legacy-payments',
            'version' => '1.0.0',
            'type' => 'payment',
            'provider' => 'Modules\\Legacy\\Provider',
            'path' => 'modules/legacy-payments',
            'description' => 'Test module',
            'is_enabled' => true,
            'installed_at' => now(),
            'meta' => [
                'requires' => [
                    'nexus' => '<1.0.0',
                    'module_api' => '0.9',
                ],
            ],
        ]);

        $warnings = app(ModuleCompatibility::class)->inspect('1.1.0', '1.0');

        $this->assertTrue(collect($warnings)->contains(
            fn (array $warning): bool => $warning['severity'] === 'blocking'
        ));
    }

    public function test_an_enabled_module_without_a_version_declaration_gets_a_warning(): void
    {
        Module::query()->create([
            'name' => 'Loose Module',
            'slug' => 'loose-module',
            'version' => '1.0.0',
            'type' => 'utility',
            'provider' => 'Modules\\Loose\\Provider',
            'path' => 'modules/loose-module',
            'description' => 'Test module',
            'is_enabled' => true,
            'installed_at' => now(),
            'meta' => [],
        ]);

        $warnings = app(ModuleCompatibility::class)->inspect('1.1.0', '1.0');

        $this->assertTrue(collect($warnings)->contains(
            fn (array $warning): bool => $warning['severity'] === 'warning'
                && str_contains($warning['message'], 'does not declare')
        ));
    }

    public function test_a_failed_rollback_can_be_retried_from_its_backup(): void
    {
        $run = UpdateRun::query()->create([
            'from_version' => '1.1.1',
            'to_version' => '1.1.2',
            'status' => 'rollback_failed',
            'channel' => 'stable',
            'backup_path' => 'storage/app/updates/backups/1/files.zip',
            'database_backup_path' => 'storage/app/updates/backups/1/database.sqlite',
            'database_backup_format' => 'sqlite',
            'started_at' => now(),
        ]);

        $this->assertTrue($run->isRollbackable());

        $run->update(['database_backup_path' => null]);

        $this->assertFalse($run->refresh()->isRollbackable());
    }

    public function test_maintenance_mode_blocks_the_storefront_but_not_the_update_screen(): void
    {
        Artisan::call('down', ['--retry' => 60]);

        $this->assertTrue(app()->isDownForMaintenance());

        $this->get('/')->assertStatus(503);

        $this->get(route('admin.updates.index'))->assertStatus(302);
        $this->get(route('admin.updates.index'))->assertDontSee('Service Unavailable');

        Artisan::call('up');

        $this->assertFalse(app()->isDownForMaintenance());
    }

    private function configureTestManifest(): void
    {
        $publicPem = (string) file_get_contents(base_path('tests/Fixtures/update-test-public.pem'));
        $der = base64_decode(preg_replace('/-----[^-]+-----|\s+/', '', $publicPem) ?: '', true);

        config([
            'nexuscart.version' => '1.0.0',
            'nexuscart.updates.enabled' => true,
            'nexuscart.updates.manifest_url' => 'https://updates.test/manifest.json',
            'nexuscart.updates.public_key' => base64_encode((string) $der),
            'nexuscart.updates.channel' => 'stable',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function manifest(): array
    {
        $size = 4096;
        $sha256 = hash('sha256', 'test-package');
        $payload = Version::signaturePayload('1.1.0', $sha256, $size);

        $private = openssl_pkey_get_private((string) file_get_contents(base_path('tests/Fixtures/update-test-private.pem')));

        $this->assertNotFalse($private);
        $this->assertTrue(openssl_sign($payload, $signature, $private, OPENSSL_ALGO_SHA256));

        return [
            'schema' => 1,
            'channel' => 'stable',
            'generated_at' => now()->toIso8601String(),
            'releases' => [
                [
                    'version' => '1.1.0',
                    'released_at' => now()->toIso8601String(),
                    'download_url' => 'https://updates.test/NexusCart-1.1.0-update.zip',
                    'sha256' => $sha256,
                    'size' => $size,
                    'signature_algorithm' => 'rsa-sha256',
                    'signature' => base64_encode($signature),
                    'channel' => 'stable',
                    'min_php' => '8.3.0',
                    'module_api' => '1.0',
                    'requires' => [
                        'php' => '>=8.3',
                        'from' => '>=1.0.0 <1.1.0',
                    ],
                    'highlights' => ['Test release'],
                    'notes' => 'Test release notes.',
                    'files' => [
                        'config/nexuscart.php' => hash('sha256', 'config'),
                    ],
                    'removed_files' => [],
                ],
            ],
        ];
    }
}
