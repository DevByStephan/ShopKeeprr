<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_storefront_shows_the_opening_soon_screen_until_the_store_is_live(): void
    {
        $this->get('/')
            ->assertStatus(503)
            ->assertSee('Opening soon')
            ->assertSee('Admin sign in');
    }
}
