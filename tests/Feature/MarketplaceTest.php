<?php

namespace Tests\Feature;

use App\Models\MarketplaceConnection;
use App\Models\Module;
use App\Models\User;
use App\Services\Marketplace\MarketplaceException;
use App\Services\Marketplace\MarketplaceManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;
use ZipArchive;

class MarketplaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'marketplace.store_url' => 'https://store.shopkeeprr.com',
            'marketplace.allow_http' => false,
        ]);

        Cache::flush();
        @mkdir(base_path('modules'), 0775, true);
    }

    protected function tearDown(): void
    {
        $this->deleteDirectory(base_path('modules/marketplace-test'));

        parent::tearDown();
    }

    public function test_admin_can_start_a_store_link(): void
    {
        Http::fake([
            'https://store.shopkeeprr.com/api/v1/marketplace/link/start' => Http::response([
                'success' => true,
                'data' => [
                    'device_code' => 'device-code',
                    'user_code' => 'ABCD1234',
                    'verification_url' => 'https://store.shopkeeprr.com/marketplace/link?code=ABCD1234',
                    'expires_at' => now()->addMinutes(15)->toIso8601String(),
                ],
            ], 201),
        ]);

        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->post(route('admin.marketplace.connect'))
            ->assertRedirect(route('admin.marketplace.link'));

        $this->assertSame('device-code', session('marketplace.link.device_code'));
        $this->assertSame('ABCD1234', session('marketplace.link.user_code'));
    }

    public function test_approved_link_is_persisted_with_an_encrypted_token(): void
    {
        $siteId = (string) Str::uuid();

        Http::fake([
            'https://store.shopkeeprr.com/api/v1/marketplace/link/poll' => Http::response([
                'success' => true,
                'data' => [
                    'status' => 'connected',
                    'token' => 'sk_site_test-token-1234',
                    'account' => ['name' => 'Store Owner', 'email' => 'owner@example.com'],
                    'site' => ['site_id' => $siteId, 'site_name' => 'Test Store', 'site_url' => 'https://test.example'],
                ],
            ]),
        ]);

        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->withSession(['marketplace.link' => [
                'device_code' => 'device-code',
                'site' => [
                    'site_id' => $siteId,
                    'site_url' => 'https://test.example',
                    'site_name' => 'Test Store',
                ],
            ]])
            ->getJson(route('admin.marketplace.status'))
            ->assertOk()
            ->assertJson(['status' => 'connected']);

        $connection = MarketplaceConnection::query()->firstOrFail();
        $this->assertSame('sk_site_test-token-1234', $connection->access_token);
        $this->assertNotSame('sk_site_test-token-1234', $connection->getRawOriginal('access_token'));
        $this->assertSame('1234', $connection->token_last_four);
    }

    public function test_one_click_install_verifies_the_checksum_and_installs_disabled(): void
    {
        $zipPath = $this->makeModuleZip();
        $zipBytes = (string) file_get_contents($zipPath);
        $hash = hash('sha256', $zipBytes);

        MarketplaceConnection::query()->create([
            'store_url' => 'https://store.shopkeeprr.com',
            'site_id' => (string) Str::uuid(),
            'site_name' => 'Test Store',
            'account_name' => 'Store Owner',
            'account_email' => 'owner@example.com',
            'access_token' => 'sk_site_test-token-1234',
            'token_last_four' => '1234',
            'connected_at' => now(),
        ]);

        Http::fake([
            'https://store.shopkeeprr.com/api/v1/marketplace/install' => Http::response([
                'success' => true,
                'data' => [
                    'product' => 'marketplace-test',
                    'version' => '1.0.0',
                    'file_name' => 'marketplace-test-1.0.0.zip',
                    'file_size' => strlen($zipBytes),
                    'sha256' => $hash,
                    'manifest' => ['slug' => 'marketplace-test'],
                    'download_url' => 'https://store.shopkeeprr.com/download/marketplace-test.zip',
                ],
            ]),
            'https://store.shopkeeprr.com/download/marketplace-test.zip' => Http::response($zipBytes, 200),
        ]);

        $module = app(MarketplaceManager::class)->install('marketplace-test');

        $this->assertSame('marketplace-test', $module->slug);
        $this->assertFalse($module->is_enabled);
        $this->assertDatabaseHas('modules', ['slug' => 'marketplace-test', 'is_enabled' => false]);
        $this->assertDirectoryExists(base_path('modules/marketplace-test'));

        @unlink($zipPath);
    }

    public function test_marketplace_listing_opens_details_in_page_and_exposes_install_action(): void
    {
        $this->connection();

        Http::fake([
            'https://store.shopkeeprr.com/api/v1/marketplace/catalog' => Http::response([
                'data' => [[
                    'type' => 'plugin',
                    'name' => 'Paid Example',
                    'slug' => 'paid-example',
                    'tagline' => 'Adds a useful checkout extra.',
                    'description' => 'A longer description for the details dialog.',
                    'features' => ['Adds a widget', 'Keeps a log'],
                    'price_cents' => 2900,
                    'currency' => 'USD',
                    'cover_url' => null,
                    'version' => '1.2.0',
                    'purchase_url' => 'https://store.shopkeeprr.com/products/paid-example',
                    'developer' => 'ShopKeeprr',
                    'release' => [
                        'version' => '1.2.0',
                        'scan_status' => 'clean',
                        'file_size' => 2048,
                        'published_at' => now()->toIso8601String(),
                    ],
                ]],
            ]),
            'https://store.shopkeeprr.com/api/v1/marketplace/library' => Http::response([
                'success' => true,
                'data' => [],
            ]),
        ]);

        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('admin.marketplace.index'))
            ->assertOk()
            ->assertSee('data-marketplace-details', false)
            ->assertSee('marketplace-detail', false)
            ->assertSee('data-detail-install', false)
            ->assertSee('Check purchase', false)
            ->assertSee('A longer description for the details dialog.', false);
    }

    public function test_admin_can_check_purchase_ownership_without_reloading_the_marketplace(): void
    {
        $this->connection();

        Http::fake([
            'https://store.shopkeeprr.com/api/v1/marketplace/library' => Http::response([
                'success' => true,
                'data' => [
                    ['slug' => 'paid-example'],
                ],
            ]),
        ]);

        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->postJson(route('admin.marketplace.ownership'))
            ->assertOk()
            ->assertExactJson(['owned' => ['paid-example']]);
    }

    public function test_a_checksum_mismatch_stops_installation(): void
    {
        $zipPath = $this->makeModuleZip();
        $zipBytes = (string) file_get_contents($zipPath);

        MarketplaceConnection::query()->create([
            'store_url' => 'https://store.shopkeeprr.com',
            'site_id' => (string) Str::uuid(),
            'access_token' => 'sk_site_test-token-1234',
            'token_last_four' => '1234',
            'connected_at' => now(),
        ]);

        Http::fake([
            'https://store.shopkeeprr.com/api/v1/marketplace/install' => Http::response([
                'success' => true,
                'data' => [
                    'product' => 'marketplace-test',
                    'version' => '1.0.0',
                    'file_name' => 'marketplace-test-1.0.0.zip',
                    'sha256' => str_repeat('0', 64),
                    'manifest' => ['slug' => 'marketplace-test'],
                    'download_url' => 'https://store.shopkeeprr.com/download/marketplace-test.zip',
                ],
            ]),
            'https://store.shopkeeprr.com/download/marketplace-test.zip' => Http::response($zipBytes, 200),
        ]);

        $this->expectException(MarketplaceException::class);
        $this->expectExceptionMessage('checksum');

        try {
            app(MarketplaceManager::class)->install('marketplace-test');
        } finally {
            @unlink($zipPath);
        }
    }

    private function connection(): MarketplaceConnection
    {
        return MarketplaceConnection::query()->create([
            'store_url' => 'https://store.shopkeeprr.com',
            'site_id' => (string) Str::uuid(),
            'site_name' => 'Test Store',
            'account_name' => 'Store Owner',
            'account_email' => 'owner@example.com',
            'access_token' => 'sk_site_test-token-1234',
            'token_last_four' => '1234',
            'connected_at' => now(),
        ]);
    }

    private function makeModuleZip(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'marketplace-test-').'.zip';
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE));

        $zip->addFromString('module.json', json_encode([
            'name' => 'Marketplace Test',
            'slug' => 'marketplace-test',
            'version' => '1.0.0',
            'type' => 'plugin',
            'namespace' => 'Modules\\MarketplaceTest\\',
            'source' => 'src',
            'provider' => 'Modules\\MarketplaceTest\\MarketplaceTestServiceProvider',
            'description' => 'Test module',
        ], JSON_PRETTY_PRINT));

        $zip->addFromString('src/MarketplaceTestServiceProvider.php', '<?php

namespace Modules\\MarketplaceTest;

use Illuminate\\Support\\ServiceProvider;

class MarketplaceTestServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }
}
');
        $zip->close();

        return $path;
    }

    private function deleteDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }

        @rmdir($directory);
    }
}
