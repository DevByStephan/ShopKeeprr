<?php

namespace Tests\Feature;

use App\Models\StorefrontPage;
use App\Models\User;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontBuilderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(SettingsService::class)->setMany([
            'store.name' => 'Builder Store',
            'store.live' => true,
            'store.mode' => 'retail',
            'store.setup_completed' => true,
            'theme.preset' => 'editorial',
        ]);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function heroBlock(array $overrides = []): array
    {
        return array_merge([
            'id' => 'blk-hero01',
            'type' => 'hero',
            'settings' => [
                'eyebrow' => 'Handmade',
                'heading' => 'Built by hand, kept for years',
                'subheading' => 'A homepage assembled in the builder.',
                'primary_label' => 'Shop now',
                'primary_url' => '/shop',
            ],
            'style' => ['background' => 'soft', 'padding' => 'lg', 'width' => 'wide', 'divider' => false],
        ], $overrides);
    }

    public function test_the_builder_is_restricted_to_administrators(): void
    {
        $this->get(route('admin.builder.index'))->assertRedirect(route('admin.login'));

        $customer = User::factory()->create(['role' => 'customer']);

        $this->actingAs($customer)->get(route('admin.builder.index'))->assertForbidden();
    }

    public function test_the_builder_workspace_loads_for_an_administrator(): void
    {
        $this->actingAs($this->admin())->get(route('admin.builder.index'))
            ->assertOk()
            ->assertSee('Storefront builder')
            ->assertSee('Page status')
            ->assertSee('Use theme homepage')
            ->assertSee('Discard draft')
            ->assertSee('builder-frame', false);
    }

    public function test_the_theme_homepage_stays_live_until_the_builder_is_published(): void
    {
        $this->get('/')->assertOk()->assertSee('Shop the collection');

        $this->actingAs($this->admin())->putJson(route('admin.builder.draft'), [
            'blocks' => [$this->heroBlock()],
        ])->assertOk()->assertJson(['ok' => true]);

        $this->get('/')->assertOk()->assertSee('Shop the collection');
        $this->get('/')->assertDontSee('Built by hand, kept for years');
    }

    public function test_publishing_renders_the_builder_on_the_public_homepage(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->postJson(route('admin.builder.publish'), [
            'blocks' => [$this->heroBlock()],
        ])->assertOk()->assertJson(['ok' => true, 'dirty' => false, 'is_enabled' => true]);

        $page = StorefrontPage::query()->where('key', 'home')->firstOrFail();
        $this->assertTrue($page->isPublished());
        $this->assertCount(1, $page->published_blocks);

        $response = $this->get('/');
        $response->assertOk();
        $response->assertSee('Built by hand, kept for years');
        $response->assertSee('data-block-id="blk-hero01"', false);
        $response->assertDontSee('Shop the collection');
    }

    public function test_a_draft_can_be_discarded_and_the_builder_switched_off(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->postJson(route('admin.builder.publish'), [
            'blocks' => [$this->heroBlock()],
        ])->assertOk();

        $this->actingAs($admin)->putJson(route('admin.builder.draft'), [
            'blocks' => [$this->heroBlock([
                'id' => 'blk-hero02',
                'settings' => ['heading' => 'A private draft'],
            ])],
        ])->assertOk()->assertJson(['dirty' => true]);

        $this->get('/')->assertSee('Built by hand, kept for years')->assertDontSee('A private draft');

        $this->actingAs($admin)->postJson(route('admin.builder.discard'))
            ->assertOk()
            ->assertJson(['dirty' => false]);

        $page = StorefrontPage::query()->where('key', 'home')->firstOrFail();
        $this->assertSame('blk-hero01', $page->blocks[0]['id']);

        $this->actingAs($admin)->postJson(route('admin.builder.disable'))
            ->assertOk()
            ->assertJson(['is_enabled' => false]);

        $this->get('/')->assertSee('Shop the collection')->assertDontSee('Built by hand, kept for years');
    }

    public function test_publishing_requires_at_least_one_section(): void
    {
        $this->actingAs($this->admin())
            ->postJson(route('admin.builder.publish'), ['blocks' => []])
            ->assertStatus(422)
            ->assertJsonValidationErrors('blocks');
    }

    public function test_saved_blocks_are_sanitized_before_they_are_stored(): void
    {
        $this->actingAs($this->admin())->putJson(route('admin.builder.draft'), [
            'blocks' => [
                [
                    'id' => 'blk-safe001',
                    'type' => 'hero',
                    'settings' => [
                        'heading' => 'Safe heading',
                        'primary_url' => 'javascript:alert(1)',
                        'image' => 'https://cdn.example.com/hero.jpg',
                    ],
                    'style' => ['background' => 'neon', 'padding' => 'huge'],
                ],
                [
                    'id' => 'blk-safe001',
                    'type' => 'hero',
                    'settings' => ['heading' => 'Duplicate id'],
                ],
                [
                    'id' => 'blk-unknown',
                    'type' => 'not-a-real-block',
                    'settings' => [],
                ],
            ],
        ])->assertOk();

        $page = StorefrontPage::query()->where('key', 'home')->firstOrFail();
        $blocks = $page->blocks;

        $this->assertCount(2, $blocks);
        $this->assertSame('blk-safe001', $blocks[0]['id']);
        $this->assertNotSame('blk-safe001', $blocks[1]['id']);
        $this->assertSame('', $blocks[0]['settings']['primary_url']);
        $this->assertSame('https://cdn.example.com/hero.jpg', $blocks[0]['settings']['image']);
        $this->assertSame('none', $blocks[0]['style']['background']);
        $this->assertSame('none', $blocks[0]['style']['padding']);
    }

    public function test_the_builder_preview_is_private_and_marked_noindex(): void
    {
        $response = $this->actingAs($this->admin())->get(route('admin.builder.preview'));

        $response->assertOk();
        $response->assertSee('nexuscart-preview-mode', false);
        $response->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }
}
