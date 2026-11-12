<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ModuleTrustTest extends TestCase
{
    use RefreshDatabase;

    public function test_module_upload_requires_the_trust_confirmation(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->post(route('admin.plugins.upload'), [
                'module' => UploadedFile::fake()->create('example.zip', 12, 'application/zip'),
            ])
            ->assertSessionHasErrors('trust');

        $this->assertDatabaseCount('modules', 0);
    }

    public function test_the_plugins_screen_explains_that_modules_are_trusted_code(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('admin.plugins.index'))
            ->assertOk()
            ->assertSee('Modules are trusted code')
            ->assertSee('migrations bundled in the package run automatically', false);
    }
}
