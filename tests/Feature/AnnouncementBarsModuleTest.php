<?php

namespace Tests\Feature;

use App\Models\Module;
use App\Models\User;
use App\Services\ModuleManager;
use App\Services\SettingsService;
use App\Services\Storefront\BlockRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnnouncementBarsModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $manager = app(ModuleManager::class);
        $manager->sync();
        Module::query()->where('slug', 'announcement-bars')->update(['is_enabled' => true]);
        $manager->registerEnabledProviders();

        app(SettingsService::class)->setMany([
            'store.name' => 'Promo Store',
            'store.live' => true,
            'store.mode' => 'retail',
            'store.setup_completed' => true,
            'theme.preset' => 'editorial',
        ]);
    }

    public function test_the_module_registers_its_builder_blocks_and_views(): void
    {
        $blocks = app(BlockRegistry::class)->all();

        $this->assertArrayHasKey('promo-bar', $blocks);
        $this->assertArrayHasKey('promo-countdown', $blocks);
        $this->assertTrue(view()->exists('announcement-bars::blocks.bar'));
        $this->assertTrue(view()->exists('announcement-bars::blocks.countdown'));
    }

    public function test_a_published_announcement_and_countdown_render_on_the_storefront(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->postJson(route('admin.builder.publish'), [
            'blocks' => [
                [
                    'id' => 'blk-promobar',
                    'type' => 'promo-bar',
                    'settings' => [
                        'message' => 'Free delivery this week only',
                        'link_label' => 'Shop the edit',
                        'link_url' => '/shop',
                        'tone' => 'ink',
                        'dismissible' => true,
                    ],
                    'style' => ['background' => 'none', 'padding' => 'none', 'width' => 'wide', 'divider' => false],
                ],
                [
                    'id' => 'blk-promocount',
                    'type' => 'promo-countdown',
                    'settings' => [
                        'eyebrow' => 'Launch week',
                        'heading' => 'The opening offer ends in',
                        'copy' => 'Take the launch price while it lasts.',
                        'ends_at' => now()->addDays(3)->format('Y-m-d\TH:i'),
                        'button_label' => 'Shop now',
                        'button_url' => '/shop',
                    ],
                    'style' => ['background' => 'soft', 'padding' => 'md', 'width' => 'wide', 'divider' => false],
                ],
            ],
        ])->assertOk();

        $this->get('/')
            ->assertOk()
            ->assertSee('Free delivery this week only')
            ->assertSee('The opening offer ends in')
            ->assertSee('data-countdown=', false)
            ->assertSee('Dismiss announcement');
    }
}
