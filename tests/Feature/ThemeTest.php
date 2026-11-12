<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use App\Services\SettingsService;
use App\Services\ThemeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThemeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(SettingsService::class)->setMany([
            'store.name' => 'Test Store',
            'store.live' => true,
            'store.mode' => 'retail',
            'store.setup_completed' => true,
        ]);
    }

    public function test_every_theme_preset_renders_its_own_storefront(): void
    {
        $markers = [
            'editorial' => ['Search the shop', 'Shop the collection'],
            'marketplace' => ['All departments', "Today's deals", 'Start shopping'],
            'auction' => ['Search for anything', 'Featured listings'],
            'bazaar' => ['SuperDeals', 'Trending now'],
        ];

        foreach ($markers as $preset => $expected) {
            $theme = app(ThemeService::class)->preset($preset);
            $this->assertNotNull($theme, "Missing theme preset: {$preset}");

            app(SettingsService::class)->setMany([
                'theme.preset' => $preset,
                'theme.primary_color' => $theme['defaults']['primary_color'],
                'theme.accent_color' => $theme['defaults']['accent_color'],
                'theme.heading_font' => $theme['defaults']['heading_font'],
                'theme.body_font' => $theme['defaults']['body_font'],
            ]);

            $response = $this->get('/');

            $response->assertOk();
            $response->assertSee('data-theme="'.$preset.'"', false);
            $response->assertSee('--brand: '.$theme['defaults']['primary_color'], false);

            foreach ($expected as $marker) {
                $response->assertSee($marker, false);
            }
        }
    }

    public function test_an_admin_can_activate_a_theme_from_the_gallery(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->post(route('admin.theme.activate'), ['preset' => 'bazaar'])
            ->assertRedirect();

        app(SettingsService::class)->flushCache();
        $settings = app(SettingsService::class);

        $this->assertSame('bazaar', $settings->get('theme.preset'));
        $this->assertSame('#e62e04', $settings->get('theme.primary_color'));
        $this->assertSame('Inter', $settings->get('theme.heading_font'));
    }

    public function test_minimum_order_quantity_only_applies_to_b2b_mode(): void
    {
        $product = Product::query()->create([
            'name' => 'Wholesale Beans',
            'slug' => 'wholesale-beans',
            'type' => 'simple',
            'price' => 24,
            'manage_stock' => false,
            'stock_quantity' => 100,
            'min_order_quantity' => 6,
            'is_active' => true,
            'requires_shipping' => true,
        ]);

        $settings = app(SettingsService::class);

        $settings->set('store.mode', 'retail');
        $this->get(route('storefront.product', $product->slug))
            ->assertOk()
            ->assertDontSee('Minimum order quantity')
            ->assertSee('name="quantity" min="1"', false);

        $settings->set('store.mode', 'subscription');
        $this->get(route('storefront.product', $product->slug))
            ->assertOk()
            ->assertDontSee('Minimum order quantity');

        $settings->set('store.mode', 'b2b');
        $this->get(route('storefront.product', $product->slug))
            ->assertOk()
            ->assertSee('Minimum order quantity: 6 units.')
            ->assertSee('name="quantity" min="6"', false);
    }

    public function test_invalid_theme_keys_are_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->post(route('admin.theme.activate'), ['preset' => 'not-a-theme'])
            ->assertSessionHasErrors('preset');
    }
}
