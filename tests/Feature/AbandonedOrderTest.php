<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AbandonedOrderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(SettingsService::class)->setMany([
            'store.name' => 'Abandoned Test Store',
            'store.email' => 'owner@example.test',
            'store.live' => true,
            'store.mode' => 'retail',
            'store.setup_completed' => true,
            'orders.abandoned_after_hours' => 24,
        ]);
    }

    public function test_stale_unpaid_orders_are_cancelled_and_stock_is_restored(): void
    {
        $product = Product::query()->create([
            'name' => 'Abandoned Item',
            'slug' => 'abandoned-item',
            'type' => 'simple',
            'price' => 20,
            'manage_stock' => true,
            'stock_quantity' => 9,
            'is_active' => true,
            'requires_shipping' => true,
        ]);

        $order = $this->order($product, 'NC-ABANDON-OLD', 30);
        $young = $this->order($product, 'NC-ABANDON-YOUNG', 0);

        $this->artisan('nexus:orders:prune-abandoned')->assertSuccessful();

        $order->refresh();
        $young->refresh();

        $this->assertSame(OrderStatus::Cancelled, $order->status);
        $this->assertSame(OrderStatus::Pending, $young->status);
        $this->assertSame(10, (int) $product->fresh()->stock_quantity);
        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id,
            'to_status' => 'cancelled',
            'note' => 'Abandoned after 24 hours without payment.',
        ]);
    }

    public function test_a_dry_run_does_not_cancel_anything(): void
    {
        $product = Product::query()->create([
            'name' => 'Dry Run Item',
            'slug' => 'dry-run-item',
            'type' => 'simple',
            'price' => 20,
            'manage_stock' => true,
            'stock_quantity' => 9,
            'is_active' => true,
            'requires_shipping' => true,
        ]);

        $order = $this->order($product, 'NC-DRY-RUN', 30);

        $this->artisan('nexus:orders:prune-abandoned', ['--dry-run' => true])->assertSuccessful();

        $this->assertSame(OrderStatus::Pending, $order->fresh()->status);
        $this->assertSame(9, (int) $product->fresh()->stock_quantity);
    }

    public function test_abandoned_orders_leave_the_admin_list_after_the_retention_window(): void
    {
        $product = Product::query()->create([
            'name' => 'Hidden Item',
            'slug' => 'hidden-item',
            'type' => 'simple',
            'price' => 20,
            'manage_stock' => false,
            'stock_quantity' => 0,
            'is_active' => true,
            'requires_shipping' => true,
        ]);

        // Default retention is 30 days; 31 days old falls outside it.
        $old = $this->order($product, 'NC-HIDDEN-OLD', 24 * 31);
        $old->update(['status' => OrderStatus::Cancelled]);
        $old->forceFill(['created_at' => now()->subDays(31), 'updated_at' => now()->subDays(31)])->saveQuietly();

        // Still inside the window, so it stays visible even after cancellation.
        $recent = $this->order($product, 'NC-VISIBLE-RECENT', 30);
        $recent->update(['status' => OrderStatus::Cancelled]);

        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('admin.orders.index'))
            ->assertOk()
            ->assertDontSee($old->number)
            ->assertSee($recent->number);

        $this->actingAs($admin)
            ->get(route('admin.orders.index', ['show_abandoned' => 1]))
            ->assertOk()
            ->assertSee($old->number)
            ->assertSee($recent->number);

        // An explicit status filter is an intentional look at history, so the
        // retention window must not silently hide matching records.
        $this->actingAs($admin)
            ->get(route('admin.orders.index', ['status' => OrderStatus::Cancelled->value]))
            ->assertOk()
            ->assertSee($old->number)
            ->assertSee($recent->number);
    }

    public function test_the_retention_window_is_configurable_and_never_hides_paid_orders(): void
    {
        $product = Product::query()->create([
            'name' => 'Retention Item',
            'slug' => 'retention-item',
            'type' => 'simple',
            'price' => 20,
            'manage_stock' => false,
            'stock_quantity' => 0,
            'is_active' => true,
            'requires_shipping' => true,
        ]);

        app(SettingsService::class)->set('orders.abandoned_retention_days', 7, 'orders');
        app(SettingsService::class)->flushCache();

        $unpaid = $this->order($product, 'NC-RETENTION-UNPAID', 24 * 8);
        $paid = $this->order($product, 'NC-RETENTION-PAID', 24 * 400);
        $paid->update([
            'status' => OrderStatus::Completed,
            'payment_status' => PaymentStatus::Paid,
            'paid_total' => 20,
        ]);

        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('admin.orders.index'))
            ->assertOk()
            ->assertDontSee($unpaid->number)
            ->assertSee($paid->number);
    }

    private function order(Product $product, string $number, int $ageHours): Order
    {
        $placedAt = now()->subHours($ageHours);

        $order = Order::query()->create([
            'number' => $number,
            'access_token' => Str::random(48),
            'status' => OrderStatus::Pending,
            'payment_status' => PaymentStatus::Pending,
            'fulfillment_status' => 'unfulfilled',
            'currency' => 'USD',
            'subtotal' => 20,
            'grand_total' => 20,
            'paid_total' => 0,
            'refunded_total' => 0,
            'customer_email' => Str::lower($number).'@example.test',
            'customer_name' => 'Abandoned Buyer',
            'shipping_address' => [
                'line1' => '1 Test Street',
                'city' => 'Austin',
                'state' => 'TX',
                'postcode' => '73301',
                'country' => 'US',
            ],
            'payment_method' => 'manual',
            'mode' => 'retail',
            'placed_at' => $placedAt,
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 1,
            'unit_price' => 20,
            'line_subtotal' => 20,
            'line_total' => 20,
        ]);

        $order->forceFill(['created_at' => $placedAt, 'updated_at' => $placedAt])->saveQuietly();

        return $order;
    }
}
