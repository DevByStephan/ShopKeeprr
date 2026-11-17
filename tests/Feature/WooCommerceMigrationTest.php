<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\TaxRate;
use App\Models\User;
use App\Models\WooCommerceConnection;
use App\Models\WooCommerceMigration;
use App\Services\Import\WooCommerceImporter;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;
use ZipArchive;

class WooCommerceMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_zip_upload_detects_and_previews_woocommerce_files(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $zip = $this->zip([
            'products.csv' => $this->csv([
                ['Type', 'SKU', 'Name', 'Regular price'],
                ['simple', 'P-1', 'Imported product', '9.50'],
            ]),
            'customers.csv' => $this->csv([
                ['customer_id', 'email', 'first_name', 'last_name'],
                ['C-1', 'customer@example.com', 'Jane', 'Doe'],
            ]),
            'orders.csv' => $this->csv([
                ['order_id', 'order_date', 'status', 'order_total'],
                ['9001', '2025-02-03 12:00:00', 'wc-completed', '9.50'],
            ]),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.migrations.woocommerce.index'))
            ->assertOk()
            ->assertSee('WooCommerce migration');

        $this->actingAs($admin)
            ->post(route('admin.migrations.woocommerce.store'), [
                'archive' => new UploadedFile($zip, 'woocommerce.zip', 'application/zip', null, true),
            ])
            ->assertRedirect();

        $migration = WooCommerceMigration::query()->firstOrFail();

        $this->assertSame('analyzed', $migration->status, json_encode($migration->stats));
        $this->assertSame(1, $migration->typeCount('products'));
        $this->assertSame(1, $migration->typeCount('customers'));
        $this->assertSame(1, $migration->typeCount('orders'));

        $this->actingAs($admin)
            ->get(route('admin.migrations.woocommerce.show', $migration))
            ->assertOk()
            ->assertSee('Imported product')
            ->assertSee('customer@example.com')
            ->assertSee('9001');
    }

    public function test_dry_run_and_apply_import_products_variations_customers_and_orders_idempotently(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $products = $this->csv([
            [
                'Type', 'SKU', 'Name', 'Published', 'Is featured?', 'Short description', 'Description',
                'Regular price', 'Sale price', 'Categories', 'Images', 'Stock', 'In stock?',
                'Weight (kg)', 'Attribute 1 name', 'Attribute 1 value(s)', 'Parent',
            ],
            [
                'variable', 'PARENT', 'Parent Tee', '1', '0', 'A short tee', '<p>A long <strong>description</strong>.</p>',
                '20.00', '15.00', 'Clothing > Tops', 'https://example.com/parent.jpg', '10', '1',
                '0.30', '', '', '',
            ],
            [
                'variation', 'VAR-BLUE', 'Parent Tee - Blue', '1', '0', '', '',
                '18.00', '', '', 'https://example.com/blue.jpg', '4', '1',
                '0.30', 'Colour', 'Blue', 'PARENT',
            ],
        ]);

        $customers = $this->csv([
            [
                'customer_id', 'email', 'first_name', 'last_name', 'display_name', 'billing_phone',
                'billing_company', 'billing_address_1', 'billing_address_2', 'billing_city',
                'billing_state', 'billing_postcode', 'billing_country', 'shipping_address_1',
                'shipping_city', 'shipping_postcode', 'shipping_country', 'date_created',
            ],
            [
                'C-1', 'customer@example.com', 'Jane', 'Doe', 'Jane Doe', '+123',
                'Acme', '1 Main St', 'Suite 2', 'Springfield',
                'IL', '62701', 'US', '2 Ship St',
                'Springfield', '62702', 'US', '2025-01-02 10:00:00',
            ],
        ]);

        $orders = $this->csv([
            [
                'order_id', 'order_number', 'order_date', 'status', 'order_currency', 'order_subtotal',
                'shipping_total', 'tax_total', 'order_total', 'refund_total', 'payment_method',
                'transaction_id', 'billing_email', 'billing_first_name', 'billing_last_name',
                'billing_address_1', 'billing_city', 'billing_postcode', 'billing_country',
                'shipping_address_1', 'shipping_city', 'shipping_postcode', 'shipping_country',
                'line_items',
            ],
            [
                '9001', '9001', '2025-02-03 12:00:00', 'wc-completed', 'USD', '15.00',
                '5.00', '2.00', '22.00', '0', 'stripe',
                'ch_123', 'customer@example.com', 'Jane', 'Doe',
                '1 Main St', 'Springfield', '62701', 'US',
                '2 Ship St', 'Springfield', '62702', 'US',
                json_encode([[
                    'name' => 'Parent Tee - Blue',
                    'product_id' => 'PARENT',
                    'variation_id' => 'VAR-BLUE',
                    'quantity' => 1,
                    'subtotal' => 15,
                    'total' => 15,
                    'tax' => 2,
                    'sku' => 'VAR-BLUE',
                ]], JSON_THROW_ON_ERROR),
            ],
        ]);

        $zip = $this->zip([
            'products.csv' => $products,
            'customers.csv' => $customers,
            'orders.csv' => $orders,
        ]);

        $this->actingAs($admin)->post(route('admin.migrations.woocommerce.store'), [
            'archive' => new UploadedFile($zip, 'woocommerce.zip', 'application/zip', null, true),
        ])->assertRedirect();

        $migration = WooCommerceMigration::query()->firstOrFail();

        $this->actingAs($admin)->post(route('admin.migrations.woocommerce.run', $migration), [
            'mode' => 'dry_run',
            'update_existing' => '1',
            'create_categories' => '1',
            'continue_on_errors' => '1',
        ])->assertRedirect();

        $migration->refresh();
        $this->assertSame('previewed', $migration->status);
        $this->assertSame(0, Product::query()->count());
        $this->assertSame(0, ProductVariant::query()->count());
        $this->assertSame(1, User::query()->count());
        $this->assertSame(0, Order::query()->count());
        $this->assertSame(1, $migration->stats['orders']['items'], json_encode($migration->stats));

        $this->actingAs($admin)->post(route('admin.migrations.woocommerce.run', $migration), [
            'mode' => 'apply',
            'update_existing' => '1',
            'create_categories' => '1',
            'continue_on_errors' => '1',
            'confirm_import' => '1',
        ])->assertRedirect();

        $migration->refresh();
        $this->assertSame('completed', $migration->status);
        $this->assertSame([], $migration->stats['errors']);

        $product = Product::query()->where('sku', 'PARENT')->firstOrFail();
        $this->assertSame('Parent Tee', $product->name);
        $this->assertSame('variable', $product->type);
        $this->assertSame(15.0, $product->price);
        $this->assertSame(20.0, $product->compare_at_price);
        $this->assertSame('A long description.', $product->description);
        $this->assertDatabaseHas('categories', ['name' => 'Clothing']);
        $this->assertDatabaseHas('categories', ['name' => 'Tops']);

        $variant = ProductVariant::query()->where('sku', 'VAR-BLUE')->firstOrFail();
        $this->assertSame($product->id, $variant->product_id);
        $this->assertSame(18.0, $variant->price);
        $this->assertSame(['Colour' => 'Blue'], $variant->options);
        $this->assertStringStartsWith('https://', (string) $variant->imageUrl());
        $this->assertSame(2, ProductImage::query()->where('product_id', $product->id)->count());

        $customer = User::query()->where('email', 'customer@example.com')->firstOrFail();
        $this->assertSame('customer', $customer->role);
        $this->assertSame('1 Main St', $customer->billing_address['line1']);
        $this->assertSame('2 Ship St', $customer->shipping_address['line1']);
        $this->assertNotEmpty($customer->password);
        $this->assertNotNull($customer->email_verified_at);

        $order = Order::query()->where('number', 'WC-9001')->firstOrFail();
        $this->assertSame($customer->id, $order->user_id);
        $this->assertSame(OrderStatus::Completed, $order->status);
        $this->assertSame(22.0, $order->grand_total);
        $this->assertSame('2025-02-03 12:00:00', $order->placed_at?->format('Y-m-d H:i:s'));
        $this->assertSame(1, $order->items()->count());
        $this->assertSame('VAR-BLUE', $order->items()->first()->sku);
        $this->assertSame($variant->id, $order->items()->first()->variant_id);
        $this->assertSame(1, Payment::query()->where('order_id', $order->id)->count());
        $this->assertSame(1, OrderStatusHistory::query()->where('order_id', $order->id)->count());

        $this->actingAs($admin)->post(route('admin.migrations.woocommerce.run', $migration), [
            'mode' => 'apply',
            'update_existing' => '1',
            'create_categories' => '1',
            'continue_on_errors' => '1',
            'confirm_import' => '1',
        ])->assertRedirect();

        $migration->refresh();
        $this->assertSame(1, Product::query()->count());
        $this->assertSame(1, ProductVariant::query()->count());
        $this->assertSame(2, User::query()->count());
        $this->assertSame(1, Order::query()->count());
        $this->assertSame(1, $order->items()->count());
        $this->assertSame(1, Payment::query()->where('order_id', $order->id)->count());
        $this->assertSame(2, ProductImage::query()->where('product_id', $product->id)->count());
        $this->assertSame(1, $migration->stats['orders']['updated']);
    }

    public function test_single_csv_with_one_row_per_order_item_imports_every_line(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $csv = $this->csv([
            [
                'order_id', 'order_date', 'status', 'order_total', 'billing_email',
                'billing_first_name', 'billing_last_name', 'order_item_name', 'product_id',
                'variation_id', 'qty', 'line_subtotal', 'line_total', 'line_tax', 'item_sku',
            ],
            [
                '9101', '2025-03-04 09:00:00', 'wc-processing', '30.00', 'row@example.com',
                'Row', 'Buyer', 'First item', 'P-1', '', '2', '20.00', '20.00', '0', 'P-1',
            ],
            [
                '9101', '2025-03-04 09:00:00', 'wc-processing', '30.00', 'row@example.com',
                'Row', 'Buyer', 'Second item', 'P-2', '', '1', '10.00', '10.00', '0', 'P-2',
            ],
        ]);

        $this->actingAs($admin)->post(route('admin.migrations.woocommerce.store'), [
            'archive' => new UploadedFile($csv, 'orders.csv', 'text/csv', null, true),
        ])->assertRedirect();

        $migration = WooCommerceMigration::query()->firstOrFail();
        $this->assertSame(1, $migration->typeCount('orders'));

        $this->actingAs($admin)->post(route('admin.migrations.woocommerce.run', $migration), [
            'mode' => 'apply',
            'update_existing' => '1',
            'create_categories' => '1',
            'continue_on_errors' => '1',
            'confirm_import' => '1',
        ])->assertRedirect();

        $order = Order::query()->where('number', 'WC-9101')->firstOrFail();
        $this->assertSame(2, $order->items()->count());
        $this->assertSame(['First item', 'Second item'], $order->items()->orderBy('id')->pluck('product_name')->all());
        $this->assertSame(2, $order->items()->orderBy('id')->first()->quantity);
        $this->assertSame(30.0, $order->grand_total);
    }

    public function test_apply_requires_confirmation_and_unknown_csv_is_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $zip = $this->zip([
            'unknown.csv' => $this->csv([
                ['id', 'foo'],
                ['1', 'bar'],
            ]),
        ]);

        $this->actingAs($admin)->post(route('admin.migrations.woocommerce.store'), [
            'archive' => new UploadedFile($zip, 'unknown.zip', 'application/zip', null, true),
        ])->assertRedirect();

        $migration = WooCommerceMigration::query()->firstOrFail();
        $this->assertSame('failed', $migration->status);
        $this->assertStringContainsString('headers', (string) data_get($migration->stats, 'errors.0.message'));

        $migration->update([
            'status' => 'analyzed',
            'files' => [[
                'name' => 'products.csv',
                'path' => $this->csv([
                    ['Type', 'SKU', 'Name', 'Regular price'],
                    ['simple', 'P-2', 'Unconfirmed product', '2.00'],
                ]),
                'type' => 'products',
                'encoding' => 'UTF-8',
                'delimiter' => ',',
                'rows' => 1,
                'size' => 10,
            ]],
        ]);

        $this->actingAs($admin)->post(route('admin.migrations.woocommerce.run', $migration), [
            'mode' => 'apply',
            'update_existing' => '1',
            'create_categories' => '1',
            'continue_on_errors' => '1',
        ])->assertSessionHasErrors('confirm_import');

        $this->assertSame(0, Product::query()->count());
    }

    public function test_api_connection_is_encrypted_tested_and_returns_remote_totals(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Http::fake([
            'https://example.com/wp-json/wc/v3/products*' => Http::response([], 200, ['X-WP-Total' => '7', 'X-WP-TotalPages' => '1']),
            'https://example.com/wp-json/wc/v3/customers*' => Http::response([], 200, ['X-WP-Total' => '3', 'X-WP-TotalPages' => '1']),
            'https://example.com/wp-json/wc/v3/orders*' => Http::response([], 200, ['X-WP-Total' => '11', 'X-WP-TotalPages' => '1']),
            'https://example.com/wp-json/wc/v3/taxes*' => Http::response([], 200, ['X-WP-Total' => '2', 'X-WP-TotalPages' => '1']),
        ]);

        $this->actingAs($admin)->post(route('admin.migrations.woocommerce.connections.store'), [
            'name' => 'Source store',
            'base_url' => 'https://example.com',
            'consumer_key' => 'ck_test_key',
            'consumer_secret' => 'cs_test_secret',
        ])->assertRedirect(route('admin.migrations.woocommerce.index'));

        $connection = WooCommerceConnection::query()->firstOrFail();
        $this->assertSame('connected', $connection->status);
        $this->assertSame(['products' => 7, 'customers' => 3, 'orders' => 11, 'taxes' => 2], $connection->counts());
        $this->assertSame('ck_test_key', $connection->consumer_key);
        $this->assertSame('cs_test_secret', $connection->consumer_secret);

        $raw = DB::table('woocommerce_connections')->where('id', $connection->id)->first();
        $this->assertNotSame('ck_test_key', $raw->consumer_key);
        $this->assertNotSame('cs_test_secret', $raw->consumer_secret);

        $this->actingAs($admin)->post(route('admin.migrations.woocommerce.connections.start', $connection))
            ->assertRedirect();

        $this->assertDatabaseHas('woocommerce_migrations', [
            'source' => 'api',
            'connection_id' => $connection->id,
            'status' => 'ready',
        ]);
    }

    public function test_api_connection_failure_is_reported_without_saving_a_connected_status(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Http::fake([
            'https://example.com/wp-json/wc/v3/products*' => Http::response(['message' => 'Sorry, you cannot list resources.'], 401),
        ]);

        $this->actingAs($admin)->post(route('admin.migrations.woocommerce.connections.store'), [
            'name' => 'Locked store',
            'base_url' => 'https://example.com',
            'consumer_key' => 'ck_bad',
            'consumer_secret' => 'cs_bad',
        ])->assertSessionHasErrors('connection');

        $connection = WooCommerceConnection::query()->firstOrFail();
        $this->assertSame('failed', $connection->status);
        $this->assertStringContainsString('rejected', (string) $connection->last_error);
        Http::assertSentCount(1);
    }

    public function test_api_dry_run_and_apply_use_pagination_and_import_normalised_records(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $connection = $this->apiConnection($admin);
        $migration = $this->apiMigration($admin, $connection);
        $fake = $this->apiFake();
        Http::fake($fake);

        $this->actingAs($admin)->post(route('admin.migrations.woocommerce.api.run', $migration), [
            'mode' => 'dry_run',
            'update_existing' => '1',
            'create_categories' => '1',
            'continue_on_errors' => '1',
        ])->assertRedirect();

        $this->actingAs($admin)->get(route('admin.migrations.woocommerce.show', $migration))
            ->assertOk()
            ->assertSee('Import progress');

        $this->drainApi($admin, $migration);

        $migration->refresh();
        $this->assertSame('previewed', $migration->status);
        $this->assertSame('done', data_get($migration->remote_state, 'phase'));
        $this->assertSame(100, data_get($migration->progress, 'percent'));
        $this->assertSame(1, $migration->stats['products']['created']);
        $this->assertSame(1, $migration->stats['variations']['created']);
        $this->assertSame(1, $migration->stats['customers']['created']);
        $this->assertSame(1, $migration->stats['orders']['created']);
        $this->assertSame(1, $migration->stats['taxes']['created']);
        $this->assertSame(2, $migration->stats['taxes']['settings']);
        $this->assertStringContainsString('Additional tax classes are not applied', implode(' ', array_column($migration->stats['warnings'], 'message')));
        $this->assertSame(0, Product::query()->count());
        $this->assertSame(0, User::query()->where('role', 'customer')->count());
        $this->assertSame(0, Order::query()->count());
        $this->assertSame(0, TaxRate::query()->count());
        $this->assertFalse((bool) app(SettingsService::class)->get('tax.prices_include_tax'));
        Http::assertSent(fn (ClientRequest $request) => str_contains($request->url(), 'page=2') && str_contains($request->url(), '/products'));
        Http::assertSent(fn (ClientRequest $request) => str_contains($request->url(), '/products/101/variations'));

        Http::fake($fake);

        $this->actingAs($admin)->post(route('admin.migrations.woocommerce.api.run', $migration), [
            'mode' => 'apply',
            'update_existing' => '1',
            'create_categories' => '1',
            'continue_on_errors' => '1',
            'confirm_import' => '1',
        ])->assertRedirect();

        $this->drainApi($admin, $migration);

        $migration->refresh();
        $this->assertSame('completed', $migration->status);

        $product = Product::query()->where('sku', 'API-TEE')->firstOrFail();
        $this->assertSame('API Tee', $product->name);
        $this->assertSame('variable', $product->type);
        $this->assertSame(15.0, $product->price);
        $this->assertSame(20.0, $product->compare_at_price);

        $variant = ProductVariant::query()->where('sku', 'API-TEE-BLUE')->firstOrFail();
        $this->assertSame($product->id, $variant->product_id);
        $this->assertSame(18.0, $variant->price);
        $this->assertSame(['Colour' => 'Blue'], $variant->options);

        $customer = User::query()->where('email', 'api@example.com')->firstOrFail();
        $this->assertSame('customer', $customer->role);
        $this->assertSame('1 API Way', $customer->billing_address['line1']);

        $order = Order::query()->where('number', 'WC-9001')->firstOrFail();
        $this->assertSame($customer->id, $order->user_id);
        $this->assertSame(18.0, $order->grand_total);
        $this->assertSame($variant->id, $order->items()->firstOrFail()->variant_id);
        $this->assertSame(1, Payment::query()->where('order_id', $order->id)->count());

        $tax = TaxRate::query()->firstOrFail();
        $this->assertSame('US', $tax->country);
        $this->assertSame('CA', $tax->state);
        $this->assertSame(7.25, $tax->rate);
        $this->assertSame(999, $tax->priority);

        $settings = app(SettingsService::class);
        $settings->flushCache();
        $this->assertTrue((bool) $settings->get('tax.prices_include_tax'));
        $this->assertTrue((bool) $settings->get('tax.shipping_taxable'));
    }

    public function test_tax_rate_csv_is_detected_and_unsupported_scopes_are_skipped(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $taxes = $this->csv([
            ['Country code', 'State code', 'Postcode', 'City', 'Rate', 'Tax name', 'Priority', 'Compound', 'Shipping', 'Tax class'],
            ['US', 'CA', '*', '*', '7.2500', 'California tax', '1', '0', '1', 'standard'],
            ['US', 'CA', '90001', '', '1.0000', 'Local district tax', '2', '0', '1', 'standard'],
            ['CA', 'ON', '', '', '5.0000', 'Reduced rate', '1', '0', '1', 'reduced-rate'],
            ['US', 'NY', '', '', '4.0000', 'Compound tax', '1', '1', '1', 'standard'],
        ]);
        $zip = $this->zip(['tax_rates.csv' => $taxes]);

        $this->actingAs($admin)->post(route('admin.migrations.woocommerce.store'), [
            'archive' => new UploadedFile($zip, 'taxes.zip', 'application/zip', null, true),
        ])->assertRedirect();

        $migration = WooCommerceMigration::query()->firstOrFail();
        $this->assertSame(1, $migration->typeCount('taxes'));

        $this->actingAs($admin)->post(route('admin.migrations.woocommerce.run', $migration), [
            'mode' => 'dry_run',
            'update_existing' => '1',
            'continue_on_errors' => '1',
        ])->assertRedirect();

        $migration->refresh();
        $this->assertSame('previewed', $migration->status);
        $this->assertSame(4, $migration->stats['taxes']['total']);
        $this->assertSame(1, $migration->stats['taxes']['created']);
        $this->assertSame(3, $migration->stats['taxes']['skipped']);
        $this->assertSame(0, TaxRate::query()->count());

        $this->actingAs($admin)->post(route('admin.migrations.woocommerce.run', $migration), [
            'mode' => 'apply',
            'update_existing' => '1',
            'continue_on_errors' => '1',
            'confirm_import' => '1',
        ])->assertRedirect();

        $tax = TaxRate::query()->firstOrFail();
        $this->assertSame('California tax', $tax->name);
        $this->assertSame('US', $tax->country);
        $this->assertSame('CA', $tax->state);
        $this->assertSame(7.25, $tax->rate);
        $this->assertSame(999, $tax->priority);
        $this->assertTrue($tax->is_active);

        $this->actingAs($admin)->post(route('admin.migrations.woocommerce.run', $migration), [
            'mode' => 'apply',
            'update_existing' => '1',
            'continue_on_errors' => '1',
            'confirm_import' => '1',
        ])->assertRedirect();

        $this->assertSame(1, TaxRate::query()->count());
        $this->assertSame(1, $migration->fresh()->stats['taxes']['updated']);
    }

    public function test_api_migration_resumes_from_the_last_successful_page_after_a_failure(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $connection = $this->apiConnection($admin);
        $migration = $this->apiMigration($admin, $connection);
        $pageTwoAttempts = 0;
        $product = [
            'id' => 202,
            'name' => 'Resume product',
            'type' => 'simple',
            'sku' => 'RESUME-1',
            'status' => 'publish',
            'regular_price' => '5.00',
            'stock_quantity' => 2,
            'stock_status' => 'instock',
        ];

        $fake = function (ClientRequest $request) use (&$pageTwoAttempts, $product) {
            $path = (string) parse_url($request->url(), PHP_URL_PATH);
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            if (str_ends_with($path, '/taxes')) {
                return Http::response([], 200, ['X-WP-Total' => '0', 'X-WP-TotalPages' => '0']);
            }

            if (str_ends_with($path, '/settings/tax')) {
                return Http::response([], 200);
            }

            if (str_ends_with($path, '/products')) {
                if ((int) ($query['page'] ?? 1) === 1) {
                    return Http::response([$product], 200, ['X-WP-Total' => '2', 'X-WP-TotalPages' => '2']);
                }

                $pageTwoAttempts++;

                if ($pageTwoAttempts <= 3) {
                    return Http::response(['message' => 'Temporary upstream failure.'], 500);
                }

                return Http::response([], 200, ['X-WP-Total' => '1', 'X-WP-TotalPages' => '2']);
            }

            if (str_ends_with($path, '/customers')) {
                return Http::response([], 200, ['X-WP-Total' => '0', 'X-WP-TotalPages' => '0']);
            }

            return Http::response([], 200, ['X-WP-Total' => '0', 'X-WP-TotalPages' => '0']);
        };

        Http::fake($fake);

        $this->actingAs($admin)->post(route('admin.migrations.woocommerce.api.run', $migration), [
            'mode' => 'dry_run',
            'update_existing' => '1',
            'create_categories' => '1',
            'continue_on_errors' => '1',
        ])->assertRedirect();

        $taxPhase = $this->actingAs($admin)->postJson(route('admin.migrations.woocommerce.api.batch', $migration));
        $taxPhase->assertOk()->assertJsonPath('done', false);

        $first = $this->actingAs($admin)->postJson(route('admin.migrations.woocommerce.api.batch', $migration));
        $first->assertOk()->assertJsonPath('done', false);

        $second = $this->actingAs($admin)->postJson(route('admin.migrations.woocommerce.api.batch', $migration));
        $second->assertStatus(500);

        $migration->refresh();
        $this->assertSame('failed', $migration->status);
        $this->assertSame('products', data_get($migration->remote_state, 'phase'));
        $this->assertSame(1, data_get($migration->progress, 'counts.products'));

        $this->actingAs($admin)->get(route('admin.migrations.woocommerce.show', $migration))
            ->assertOk()
            ->assertSee('Resume from last page');

        $this->drainApi($admin, $migration);

        $migration->refresh();
        $this->assertSame('previewed', $migration->status);
        $this->assertSame('done', data_get($migration->remote_state, 'phase'));
        $this->assertSame(0, Product::query()->count());
    }

    private function apiConnection(User $admin): WooCommerceConnection
    {
        return WooCommerceConnection::query()->create([
            'user_id' => $admin->id,
            'name' => 'Source store',
            'base_url' => 'https://example.com',
            'consumer_key' => 'ck_test_key',
            'consumer_secret' => 'cs_test_secret',
            'status' => 'connected',
            'last_tested_at' => now(),
            'metadata' => ['counts' => ['products' => 1, 'customers' => 1, 'orders' => 1]],
        ]);
    }

    private function apiMigration(User $admin, WooCommerceConnection $connection): WooCommerceMigration
    {
        return WooCommerceMigration::query()->create([
            'user_id' => $admin->id,
            'connection_id' => $connection->id,
            'source' => 'api',
            'original_filename' => 'API: Source store',
            'status' => 'ready',
            'options' => [
                'update_existing' => true,
                'create_categories' => true,
                'download_images' => false,
                'continue_on_errors' => true,
            ],
            'stats' => app(WooCommerceImporter::class)->initialStats(),
        ]);
    }

    /**
     * @return \Closure(ClientRequest): Response
     */
    private function apiFake(): \Closure
    {
        $product = [
            'id' => 101,
            'name' => 'API Tee',
            'type' => 'variable',
            'sku' => 'API-TEE',
            'status' => 'publish',
            'short_description' => 'API short description',
            'description' => '<p>API description</p>',
            'regular_price' => '20.00',
            'sale_price' => '15.00',
            'stock_quantity' => 5,
            'stock_status' => 'instock',
            'weight' => '0.30',
            'categories' => [['id' => 3, 'name' => 'API Category']],
            'images' => [['src' => 'https://example.com/api-tee.jpg']],
            'attributes' => [['name' => 'Colour', 'options' => ['Blue']]],
            'variations' => [501],
        ];
        $variation = [
            'id' => 501,
            'sku' => 'API-TEE-BLUE',
            'regular_price' => '18.00',
            'stock_quantity' => 2,
            'stock_status' => 'instock',
            'weight' => '0.30',
            'attributes' => [['name' => 'Colour', 'option' => 'Blue']],
            'images' => [],
        ];
        $customer = [
            'id' => 7,
            'email' => 'api@example.com',
            'first_name' => 'Api',
            'last_name' => 'Buyer',
            'username' => 'apibuyer',
            'billing' => [
                'first_name' => 'Api', 'last_name' => 'Buyer', 'company' => 'API Co',
                'address_1' => '1 API Way', 'city' => 'Testville', 'state' => 'CA',
                'postcode' => '12345', 'country' => 'US', 'phone' => '+1 555 0100',
                'email' => 'api@example.com', 'vat' => 'VAT-1',
            ],
            'shipping' => ['first_name' => 'Api', 'last_name' => 'Buyer', 'address_1' => '2 API Way', 'city' => 'Testville', 'postcode' => '12345', 'country' => 'US'],
            'date_created' => '2025-03-01T09:00:00',
        ];
        $tax = [
            'id' => 41,
            'country' => 'US',
            'state' => 'CA',
            'rate' => '7.2500',
            'name' => 'California tax',
            'priority' => 1,
            'compound' => false,
            'shipping' => true,
            'class' => 'standard',
        ];
        $taxSettings = [
            ['id' => 'woocommerce_prices_include_tax', 'value' => 'yes'],
            ['id' => 'woocommerce_shipping_tax_class', 'value' => 'inherit'],
            ['id' => 'woocommerce_tax_based_on', 'value' => 'shipping'],
        ];
        $taxClasses = [
            ['slug' => 'standard', 'name' => 'Standard rate'],
            ['slug' => 'reduced-rate', 'name' => 'Reduced rate'],
        ];
        $order = [
            'id' => 9001,
            'number' => '9001',
            'date_created' => '2025-04-01T10:00:00',
            'status' => 'completed',
            'currency' => 'USD',
            'discount_total' => '0.00',
            'shipping_total' => '0.00',
            'total_tax' => '0.00',
            'total' => '18.00',
            'billing' => ['first_name' => 'Api', 'last_name' => 'Buyer', 'address_1' => '1 API Way', 'city' => 'Testville', 'postcode' => '12345', 'country' => 'US', 'email' => 'api@example.com'],
            'shipping' => ['first_name' => 'Api', 'last_name' => 'Buyer', 'address_1' => '2 API Way', 'city' => 'Testville', 'postcode' => '12345', 'country' => 'US'],
            'line_items' => [[
                'name' => 'API Tee - Blue', 'product_id' => 101, 'variation_id' => 501,
                'quantity' => 1, 'subtotal' => '18.00', 'total' => '18.00', 'total_tax' => '0.00',
                'sku' => 'API-TEE-BLUE',
            ]],
            'refunds' => [],
            'shipping_lines' => [],
            'coupon_lines' => [],
        ];

        return function (ClientRequest $request) use ($product, $variation, $customer, $order, $tax, $taxSettings, $taxClasses) {
            $path = (string) parse_url($request->url(), PHP_URL_PATH);
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
            $page = (int) ($query['page'] ?? 1);

            if (str_ends_with($path, '/taxes/classes')) {
                return Http::response($taxClasses, 200);
            }

            if (str_ends_with($path, '/settings/tax')) {
                return Http::response($taxSettings, 200);
            }

            if (str_ends_with($path, '/taxes')) {
                return Http::response([$tax], 200, ['X-WP-Total' => '1', 'X-WP-TotalPages' => '1']);
            }

            if (str_contains($path, '/products/101/variations')) {
                return Http::response([$variation], 200, ['X-WP-Total' => '1', 'X-WP-TotalPages' => '1']);
            }

            if (str_ends_with($path, '/products')) {
                return Http::response($page === 1 ? [$product] : [], 200, ['X-WP-Total' => '1', 'X-WP-TotalPages' => '2']);
            }

            if (str_ends_with($path, '/customers')) {
                return Http::response([$customer], 200, ['X-WP-Total' => '1', 'X-WP-TotalPages' => '1']);
            }

            if (str_ends_with($path, '/orders')) {
                return Http::response([$order], 200, ['X-WP-Total' => '1', 'X-WP-TotalPages' => '1']);
            }

            return Http::response([], 404);
        };
    }

    private function drainApi(User $admin, WooCommerceMigration $migration): void
    {
        for ($attempt = 0; $attempt < 20; $attempt++) {
            $response = $this->actingAs($admin)->postJson(route('admin.migrations.woocommerce.api.batch', $migration));
            $response->assertOk();

            if ($response->json('done') === true) {
                return;
            }
        }

        $this->fail('The API migration did not complete within 20 batches.');
    }

    /**
     * @param  array<string, string>  $files
     */
    private function zip(array $files): string
    {
        $directory = storage_path('app/testing');
        File::ensureDirectoryExists($directory);
        $path = $directory.'/woocommerce-'.Str::random(10).'.zip';
        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            $this->fail('The test ZIP could not be created.');
        }

        foreach ($files as $name => $contents) {
            $zip->addFromString($name, is_file($contents) ? (string) file_get_contents($contents) : $contents);
        }

        $zip->close();

        return $path;
    }

    /**
     * @param  list<list<string>>  $rows
     */
    private function csv(array $rows): string
    {
        $directory = storage_path('app/testing');
        File::ensureDirectoryExists($directory);
        $path = $directory.'/woocommerce-'.Str::random(10).'.csv';
        $handle = fopen($path, 'wb');

        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }

        fclose($handle);

        return $path;
    }
}
