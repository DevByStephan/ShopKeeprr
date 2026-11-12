<?php

namespace Tests\Feature;

use App\Models\MarketplaceSiteLink;
use App\Models\Product;
use App\Models\ProductRelease;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class MarketplaceSiteLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_site_can_start_link_approve_and_receive_a_token(): void
    {
        $user = User::factory()->create();
        $siteId = (string) Str::uuid();

        $start = $this->postJson('/api/v1/marketplace/link/start', [
            'site_id' => $siteId,
            'site_url' => 'https://test-store.example',
            'site_name' => 'Test Store',
            'platform_version' => '1.1.0',
        ])->assertCreated()->json('data');

        $this->assertSame('pending', $this->postJson('/api/v1/marketplace/link/poll', [
            'device_code' => $start['device_code'],
        ])->assertStatus(202)->json('data.status'));

        $this->actingAs($user)
            ->post('/marketplace/link', ['code' => $start['user_code']])
            ->assertOk();

        $poll = $this->postJson('/api/v1/marketplace/link/poll', [
            'device_code' => $start['device_code'],
        ])->assertOk()->json('data');

        $this->assertSame('connected', $poll['status']);
        $this->assertStringStartsWith('sk_site_', $poll['token']);
        $this->assertSame($user->email, $poll['account']['email']);

        $link = MarketplaceSiteLink::query()->firstOrFail();
        $this->assertSame(hash('sha256', $poll['token']), $link->token_hash);

        $this->withHeader('Authorization', 'Bearer '.$poll['token'])
            ->getJson('/api/v1/marketplace/account')
            ->assertOk()
            ->assertJsonPath('data.site.site_id', $siteId);
    }

    public function test_a_free_plugin_can_be_installed_through_the_site_token(): void
    {
        Storage::fake('releases');
        Storage::disk('releases')->put('free-plugin.zip', 'test package bytes');

        $user = User::factory()->create();
        $product = Product::query()->create([
            'type' => Product::TYPE_PLUGIN,
            'name' => 'Free Plugin',
            'slug' => 'free-plugin',
            'tagline' => 'A free test plugin',
            'description' => 'Test plugin',
            'price_cents' => 0,
            'currency' => 'USD',
            'version' => '1.0.0',
            'checkout_enabled' => true,
            'requires_license' => false,
            'is_published' => true,
        ]);

        $release = ProductRelease::query()->create([
            'product_id' => $product->id,
            'version' => '1.0.0',
            'file_path' => 'free-plugin.zip',
            'file_name' => 'free-plugin.zip',
            'file_size' => 18,
            'sha256' => hash('sha256', 'test package bytes'),
            'manifest' => ['slug' => 'free-plugin'],
            'scan_status' => ProductRelease::SCAN_CLEAN,
            'scan_score' => 0,
            'scan_findings' => [],
            'scan_engine' => 'test',
            'scanned_at' => now(),
            'is_current' => true,
            'published_at' => now(),
        ]);

        $token = $this->linkedToken($user);

        $install = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/marketplace/install', ['product' => 'free-plugin'])
            ->assertOk()
            ->json('data');

        $this->assertSame($release->sha256, $install['sha256']);
        $this->assertStringContainsString('/api/v1/marketplace/download/', $install['download_url']);

        $this->get($install['download_url'])
            ->assertOk()
            ->assertHeader('content-type', 'application/zip');

        $this->assertDatabaseHas('downloads', [
            'product_release_id' => $release->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_a_paid_plugin_requires_a_purchase(): void
    {
        Storage::fake('releases');
        Storage::disk('releases')->put('paid-plugin.zip', 'paid bytes');

        $user = User::factory()->create();
        $product = Product::query()->create([
            'type' => Product::TYPE_PLUGIN,
            'name' => 'Paid Plugin',
            'slug' => 'paid-plugin',
            'price_cents' => 4900,
            'currency' => 'USD',
            'version' => '1.0.0',
            'checkout_enabled' => true,
            'requires_license' => true,
            'is_published' => true,
        ]);

        ProductRelease::query()->create([
            'product_id' => $product->id,
            'version' => '1.0.0',
            'file_path' => 'paid-plugin.zip',
            'file_name' => 'paid-plugin.zip',
            'file_size' => 10,
            'sha256' => hash('sha256', 'paid bytes'),
            'manifest' => ['slug' => 'paid-plugin'],
            'scan_status' => ProductRelease::SCAN_CLEAN,
            'scan_score' => 0,
            'scan_findings' => [],
            'scan_engine' => 'test',
            'scanned_at' => now(),
            'is_current' => true,
            'published_at' => now(),
        ]);

        $token = $this->linkedToken($user);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/marketplace/install', ['product' => 'paid-plugin'])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'purchase_required');
    }

    private function linkedToken(User $user): string
    {
        $start = $this->postJson('/api/v1/marketplace/link/start', [
            'site_id' => (string) Str::uuid(),
            'site_url' => 'https://test-store.example',
            'site_name' => 'Test Store',
        ])->assertCreated()->json('data');

        $this->actingAs($user)
            ->post('/marketplace/link', ['code' => $start['user_code']])
            ->assertOk();

        return $this->postJson('/api/v1/marketplace/link/poll', [
            'device_code' => $start['device_code'],
        ])->assertOk()->json('data.token');
    }
}
