<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\User;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegalPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(SettingsService::class)->setMany([
            'store.name' => 'Test Store',
            'store.email' => 'support@example.test',
            'store.live' => true,
            'store.mode' => 'retail',
            'store.setup_completed' => true,
            'theme.preset' => 'editorial',
            'theme.primary_color' => '#0f766e',
            'theme.accent_color' => '#b45309',
            'theme.heading_font' => 'Fraunces',
            'theme.body_font' => 'Inter',
        ]);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_terms_and_privacy_can_be_restored_linked_and_edited(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.pages.legal.restore'))
            ->assertRedirect(route('admin.pages.index'));

        $terms = Page::query()->where('slug', 'terms')->firstOrFail();
        $privacy = Page::query()->where('slug', 'privacy')->firstOrFail();

        $this->assertTrue($terms->is_published);
        $this->assertTrue($privacy->is_published);

        $this->get('/pages/terms')->assertOk()->assertSee('Terms of Service');
        $this->get('/pages/privacy')->assertOk()->assertSee('Privacy Policy');

        $this->actingAs($this->admin())
            ->put(route('admin.pages.update', $terms), [
                'title' => 'Terms of Service',
                'slug' => 'terms',
                'content' => "## Our terms\n\nThese are the merchant's own edited terms.",
                'is_published' => '1',
            ])
            ->assertRedirect(route('admin.pages.index'));

        $this->get('/pages/terms')
            ->assertOk()
            ->assertSee('Terms of Service')
            ->assertSee("These are the merchant's own edited terms.");
    }
}
