<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Models\Module;
use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Services\ModuleManager;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Modules\AbandonedCart\Console\SendRemindersCommand;
use Modules\AbandonedCart\Models\Reminder;
use Modules\Bundles\Models\Bundle;
use Modules\Loyalty\Models\LoyaltyAccount;
use Modules\Reviews\Models\Review;
use Modules\Wishlist\Models\WishlistItem;
use Tests\TestCase;

class ModuleSuiteTest extends TestCase
{
    use DatabaseMigrations;

    private array $modules = [
        'product-reviews-ratings',
        'customer-wishlist',
        'product-bundles-upsells',
        'loyalty-rewards',
        'abandoned-cart-recovery',
    ];

    private array $availableModules = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->availableModules = array_values(array_filter(
            $this->modules,
            fn (string $module): bool => is_dir(base_path('modules/'.$module)),
        ));

        foreach ($this->availableModules as $module) {
            $status = Artisan::call('migrate', [
                '--path' => 'modules/'.$module.'/database/migrations',
                '--force' => true,
            ]);
            $this->assertSame(0, $status, Artisan::output());
        }

        app(ModuleManager::class)->sync();
        Module::query()->whereIn('slug', $this->availableModules)->update(['is_enabled' => true]);
        app(ModuleManager::class)->registerEnabledProviders();

        app(SettingsService::class)->setMany([
            'store.name' => 'Module Test Store',
            'store.live' => true,
            'store.mode' => 'retail',
            'store.setup_completed' => true,
            'store.currency' => 'USD',
            'store.currency_symbol' => '$',
            'store.email' => 'store@example.test',
        ]);

        Store::query()->create([
            'name' => 'Module Test Store',
            'slug' => 'module-test-store',
            'email' => 'store@example.test',
            'currency' => 'USD',
            'currency_symbol' => '$',
            'country' => 'US',
            'is_default' => true,
        ]);
    }

    public function test_a_verified_review_is_moderated_before_it_appears(): void
    {
        $this->requireModules(['product-reviews-ratings']);

        $customer = User::factory()->create(['name' => 'Riley Customer']);
        $product = $this->product('Trail Mug', 'trail-mug', 18);

        $this->paidOrder($customer, $product, 18);

        $this->actingAs($customer)->post(route('reviews.store', $product->slug), [
            'rating' => 5,
            'title' => 'Brilliant little mug',
            'body' => 'Keeps coffee hot for hours and the lid seals properly.',
        ])->assertRedirect();

        $review = Review::query()->firstOrFail();
        $this->assertTrue($review->is_verified);
        $this->assertSame(Review::STATUS_PENDING, $review->status);

        $this->get(route('storefront.product', $product->slug))->assertDontSee('Brilliant little mug');

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->patch(route('admin.reviews.update', $review), [
            'status' => Review::STATUS_APPROVED,
            'admin_reply' => 'Thanks Riley!',
        ])->assertRedirect();

        $this->get(route('storefront.product', $product->slug))
            ->assertOk()
            ->assertSee('Brilliant little mug')
            ->assertSee('Verified purchase')
            ->assertSee('Thanks Riley!');
    }

    public function test_wishlist_and_bundle_purchase_work_on_the_storefront(): void
    {
        $this->requireModules(['customer-wishlist', 'product-bundles-upsells']);

        $customer = User::factory()->create();
        $first = $this->product('Pour Over Kit', 'pour-over-kit', 40);
        $second = $this->product('Filter Papers', 'filter-papers', 10);

        $this->actingAs($customer)->post(route('wishlist.toggle', $first->slug))->assertRedirect();
        $this->assertDatabaseHas('wishlist_items', ['user_id' => $customer->id, 'product_id' => $first->id]);
        $this->get(route('wishlist.index'))->assertOk()->assertSee('Pour Over Kit');

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->post(route('admin.bundles.store'), [
            'name' => 'Brew starter set',
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'is_active' => '1',
            'items' => [
                ['product_id' => $first->id, 'quantity' => 1],
                ['product_id' => $second->id, 'quantity' => 1],
            ],
        ])->assertRedirect();

        $bundle = Bundle::query()->with('products')->firstOrFail();
        $this->assertSame(45.0, $bundle->bundlePrice());

        $this->get(route('storefront.product', $first->slug))
            ->assertOk()
            ->assertSee('Frequently bought together')
            ->assertSee('Brew starter set');

        $this->actingAs($customer)->post(route('bundles.add', $bundle->slug))->assertRedirect(route('storefront.cart'));
        $this->assertSame(2, \App\Models\CartItem::query()->count());
        $this->assertSame(45.0, round((float) \App\Models\CartItem::query()->sum(\Illuminate\Support\Facades\DB::raw('quantity * unit_price')), 2));
    }

    public function test_loyalty_points_are_awarded_on_payment_and_redeemable(): void
    {
        $this->requireModules(['loyalty-rewards']);

        $customer = User::factory()->create();
        $product = $this->product('Roast Club', 'roast-club', 120);

        $this->paidOrder($customer, $product, 120);

        $account = LoyaltyAccount::query()->where('user_id', $customer->id)->firstOrFail();
        $this->assertSame(120, $account->points_balance);

        $this->actingAs($customer)->get(route('loyalty.index'))->assertOk()->assertSee('120');
        $this->actingAs($customer)->post(route('loyalty.redeem'))->assertRedirect();

        $account->refresh();
        $this->assertSame(20, $account->points_balance);
        $this->assertDatabaseHas('coupons', ['type' => 'fixed', 'value' => 5, 'usage_limit' => 1]);
    }

    public function test_abandoned_checkout_reminder_is_sent_once(): void
    {
        $this->requireModules(['abandoned-cart-recovery']);

        Mail::fake();

        app(SettingsService::class)->setMany([
            'abandoned.enabled' => true,
            'abandoned.delay_hours' => 1,
            'abandoned.max_age_days' => 30,
        ]);

        $order = $this->pendingOrder(User::factory()->create(), $this->product('Grinder', 'grinder', 80), 80);
        $order->forceFill(['created_at' => now()->subHours(3)])->save();

        $command = app(SendRemindersCommand::class);
        $command->setOutput(new \Illuminate\Console\OutputStyle(
            new \Symfony\Component\Console\Input\ArrayInput([]),
            new \Symfony\Component\Console\Output\BufferedOutput,
        ));
        $command->handle(app(SettingsService::class));
        $command->handle(app(SettingsService::class));

        $this->assertSame(1, Reminder::query()->count());
        Mail::assertSent(\Modules\AbandonedCart\Mail\AbandonedCheckoutReminder::class, function ($mail) {
            return str_contains($mail->render(), 'Return to my order')
                && str_contains($mail->render(), 'Grinder');
        });
    }

    public function test_every_module_admin_screen_renders(): void
    {
        $this->requireModules(['product-bundles-upsells', 'loyalty-rewards', 'abandoned-cart-recovery']);

        $admin = User::factory()->create(['role' => 'admin']);

        $bundle = Bundle::query()->create([
            'name' => 'Test set',
            'slug' => 'test-set',
            'discount_type' => 'percentage',
            'discount_value' => 5,
            'is_active' => true,
        ]);
        $bundle->products()->attach($this->product('One', 'one', 10)->id, ['quantity' => 1]);
        $bundle->products()->attach($this->product('Two', 'two', 20)->id, ['quantity' => 1]);

        $routes = [
            route('admin.reviews.index'),
            route('admin.bundles.index'),
            route('admin.bundles.create'),
            route('admin.bundles.edit', $bundle),
            route('admin.loyalty.index'),
            route('admin.abandoned.index'),
        ];

        foreach ($routes as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }

    private function requireModules(array $modules): void
    {
        $missing = array_diff($modules, $this->availableModules);

        if ($missing !== []) {
            $this->markTestSkipped('Optional module(s) not included in this build: '.implode(', ', $missing));
        }
    }

    private function product(string $name, string $slug, float $price): Product
    {
        return Product::query()->create([
            'name' => $name,
            'slug' => $slug,
            'type' => 'simple',
            'price' => $price,
            'manage_stock' => true,
            'stock_quantity' => 50,
            'is_active' => true,
            'requires_shipping' => true,
            'published_at' => now(),
        ]);
    }

    private function paidOrder(User $user, Product $product, float $total): Order
    {
        $order = Order::query()->create([
            'user_id' => $user->id,
            'number' => 'ORD-'.strtoupper(\Illuminate\Support\Str::random(8)),
            'status' => 'processing',
            'payment_status' => PaymentStatus::Paid->value,
            'currency' => 'USD',
            'subtotal' => $total,
            'grand_total' => $total,
            'paid_total' => $total,
            'customer_email' => $user->email,
            'customer_name' => $user->name,
            'shipping_address' => ['line1' => '1 Test Street', 'city' => 'Test', 'postcode' => '12345', 'country' => 'US'],
            'paid_at' => now(),
            'placed_at' => now(),
            'mode' => 'retail',
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'unit_price' => $total,
            'quantity' => 1,
            'line_subtotal' => $total,
            'line_total' => $total,
        ]);

        return $order;
    }

    private function pendingOrder(User $user, Product $product, float $total): Order
    {
        $order = Order::query()->create([
            'user_id' => $user->id,
            'number' => 'ORD-'.strtoupper(\Illuminate\Support\Str::random(8)),
            'status' => 'pending',
            'payment_status' => PaymentStatus::Pending->value,
            'currency' => 'USD',
            'subtotal' => $total,
            'grand_total' => $total,
            'customer_email' => $user->email,
            'customer_name' => $user->name,
            'shipping_address' => ['line1' => '1 Test Street', 'city' => 'Test', 'postcode' => '12345', 'country' => 'US'],
            'placed_at' => now(),
            'mode' => 'retail',
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'unit_price' => $total,
            'quantity' => 1,
            'line_subtotal' => $total,
            'line_total' => $total,
        ]);

        return $order;
    }
}
