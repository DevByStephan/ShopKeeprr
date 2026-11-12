<?php

namespace Database\Seeders;

use App\Enums\StoreMode;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\CustomerTier;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\TaxRate;
use App\Models\TierPrice;
use App\Models\User;
use App\Models\Vendor;
use App\Services\SettingsService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DemoSeeder extends Seeder
{
    /**
     * One random password per seeding run, never a fixed default.
     */
    private static ?string $demoPassword = null;

    public function run(): void
    {
        $store = Store::query()->where('is_default', true)->first();

        if ($store) {
            $this->seedFor($store, $store->settings['mode'] ?? null ? StoreMode::tryFrom($store->settings['mode']) : null);
        }
    }

    public function seedFor(Store $store, ?StoreMode $mode = null): void
    {
        if (Product::query()->where('store_id', $store->id)->exists()) {
            return;
        }

        $categories = collect([
            ['Home', 'home', 'Objects that make a room feel finished.', 'loom-throw.svg'],
            ['Desk', 'desk', 'Tools for clearer, calmer work.', 'arc-lamp.svg'],
            ['Kitchen', 'kitchen', 'Everyday pieces worth leaving on the counter.', 'copper-kettle.svg'],
            ['Travel', 'travel', 'Carry the useful things, beautifully.', 'canvas-tote.svg'],
        ])->mapWithKeys(function (array $row) use ($store) {
            $category = Category::query()->create([
                'store_id' => $store->id,
                'name' => $row[0],
                'slug' => $row[1],
                'description' => $row[2],
                'image' => 'uploads/demo/'.$row[3],
                'is_active' => true,
            ]);

            return [$row[1] => $category];
        });

        $wholesale = CustomerTier::query()->where('slug', 'wholesale')->first();
        $preferred = CustomerTier::query()->where('slug', 'preferred-trade')->first();

        $products = [
            [
                'name' => 'Ridge Ceramic Mug',
                'slug' => 'ridge-ceramic-mug',
                'category' => 'kitchen',
                'description' => 'A hand-thrown stoneware mug with a soft matte glaze and a generous handle. Holds 12 oz.',
                'short' => 'Hand-thrown stoneware, made for slow mornings.',
                'price' => 28,
                'compare' => 34,
                'stock' => 120,
                'featured' => true,
                'image' => 'ridge-mug.svg',
                'variants' => [['Sand', 'RID-MUG-SAND', 28], ['Charcoal', 'RID-MUG-CHAR', 28]],
            ],
            [
                'name' => 'Loom Linen Throw',
                'slug' => 'loom-linen-throw',
                'category' => 'home',
                'description' => 'A weighty, stonewashed linen throw woven in small batches. 50" x 70".',
                'short' => 'Stonewashed linen with a lived-in softness.',
                'price' => 128,
                'stock' => 42,
                'featured' => true,
                'image' => 'loom-throw.svg',
                'variants' => [['Oat', 'LOOM-OAT', 128], ['Sage', 'LOOM-SAGE', 128]],
            ],
            [
                'name' => 'Arc Desk Lamp',
                'slug' => 'arc-desk-lamp',
                'category' => 'desk',
                'description' => 'A low-profile task lamp with a warm, dimmable LED and a powder-coated steel shade.',
                'short' => 'Warm, focused light with a sculptural silhouette.',
                'price' => 189,
                'stock' => 18,
                'featured' => true,
                'image' => 'arc-lamp.svg',
                'variants' => [],
            ],
            [
                'name' => 'Field Notes Notebook',
                'slug' => 'field-notes-notebook',
                'category' => 'desk',
                'description' => 'Lay-flat binding, 160 pages of 100gsm cream paper, and a cloth cover that wears in beautifully.',
                'short' => 'A lay-flat notebook for the ideas that matter.',
                'price' => 18,
                'stock' => 300,
                'image' => 'field-notes.svg',
                'variants' => [['Rust', 'FIELD-RUST', 18], ['Ink', 'FIELD-INK', 18]],
            ],
            [
                'name' => 'Everyday Canvas Tote',
                'slug' => 'everyday-canvas-tote',
                'category' => 'travel',
                'description' => 'Heavyweight cotton canvas with an interior pocket and reinforced handles. Fits a 15" laptop.',
                'short' => 'A carry-everything tote that gets better with age.',
                'price' => 42,
                'stock' => 76,
                'featured' => true,
                'image' => 'canvas-tote.svg',
                'variants' => [['Natural', 'TOTE-NAT', 42], ['Black', 'TOTE-BLK', 42]],
            ],
            [
                'name' => 'Slow Burn Candle',
                'slug' => 'slow-burn-candle',
                'category' => 'home',
                'description' => 'Cedar, fig leaf, and a trace of smoke. Hand-poured coconut wax with a 60-hour burn time.',
                'short' => 'Cedar and fig, hand-poured in small batches.',
                'price' => 36,
                'stock' => 88,
                'image' => 'slow-candle.svg',
                'variants' => [],
            ],
            [
                'name' => 'Copper Pour-Over Kettle',
                'slug' => 'copper-pour-over-kettle',
                'category' => 'kitchen',
                'description' => 'A balanced gooseneck kettle with a copper finish and a heat-resistant handle. 1 liter.',
                'short' => 'A precise pour, every morning.',
                'price' => 96,
                'stock' => 24,
                'image' => 'copper-kettle.svg',
                'variants' => [],
            ],
            [
                'name' => 'Stoneware Planter',
                'slug' => 'stoneware-planter',
                'category' => 'home',
                'description' => 'A sculptural planter with a drainage hole and matching saucer. Fits a 6" nursery pot.',
                'short' => 'A quiet home for your favorite green thing.',
                'price' => 54,
                'stock' => 31,
                'image' => 'stoneware-planter.svg',
                'variants' => [],
            ],
            [
                'name' => "Roaster's Choice Coffee",
                'slug' => 'roasters-choice-coffee',
                'category' => 'kitchen',
                'description' => 'A rotating single-origin roast, ground to order. Choose a recurring delivery and never run out.',
                'short' => 'A new single origin, delivered on your schedule.',
                'price' => 24,
                'stock' => 200,
                'image' => 'roasters-coffee.svg',
                'variants' => [],
                'subscription_plans' => [
                    ['Monthly', 'month', 1, 24, 0],
                    ['Every 2 weeks', 'week', 2, 22, 0],
                    ['3-month prepay', 'month', 3, 66, 0],
                ],
            ],
            [
                'name' => 'Seasonal Pantry Box',
                'slug' => 'seasonal-pantry-box',
                'category' => 'kitchen',
                'description' => 'A quarterly edit of small-batch pantry staples: oil, salt, preserves, and something unexpected.',
                'short' => 'A seasonal box of our favorite pantry finds.',
                'price' => 68,
                'stock' => 60,
                'image' => 'pantry-box.svg',
                'variants' => [],
                'subscription_plans' => [
                    ['Quarterly box', 'month', 3, 68, 0],
                ],
            ],
        ];

        $created = collect();

        foreach ($products as $row) {
            $product = Product::query()->create([
                'store_id' => $store->id,
                'category_id' => $categories[$row['category']]->id,
                'name' => $row['name'],
                'slug' => $row['slug'],
                'sku' => strtoupper(Str::slug($row['slug'], '-')),
                'type' => empty($row['variants']) ? 'simple' : 'variable',
                'short_description' => $row['short'],
                'description' => '<p>'.$row['description'].'</p>',
                'price' => $row['price'],
                'compare_at_price' => $row['compare'] ?? null,
                'manage_stock' => true,
                'stock_quantity' => $row['stock'],
                'min_order_quantity' => $mode === StoreMode::B2B ? 6 : 1,
                'is_active' => true,
                'is_featured' => (bool) ($row['featured'] ?? false),
                'requires_shipping' => true,
                'published_at' => now()->subDays(random_int(2, 40)),
            ]);

            ProductImage::query()->create([
                'product_id' => $product->id,
                'path' => 'uploads/demo/'.$row['image'],
                'alt' => $row['name'],
                'sort_order' => 0,
            ]);

            foreach ($row['variants'] as $index => $variantRow) {
                $variant = ProductVariant::query()->create([
                    'product_id' => $product->id,
                    'name' => $variantRow[0],
                    'sku' => $variantRow[1],
                    'price' => $variantRow[2],
                    'stock_quantity' => max(4, (int) floor($row['stock'] / max(1, count($row['variants'])))),
                    'options' => ['Color' => $variantRow[0]],
                    'is_active' => true,
                    'sort_order' => $index,
                ]);

                $this->seedTierPrices($product, $wholesale, $preferred, $variant);
            }

            if (empty($row['variants'])) {
                $this->seedTierPrices($product, $wholesale, $preferred);
            }

            foreach ($row['subscription_plans'] ?? [] as $index => $planRow) {
                SubscriptionPlan::query()->create([
                    'product_id' => $product->id,
                    'name' => $planRow[0],
                    'interval' => $planRow[1],
                    'interval_count' => $planRow[2],
                    'price' => $planRow[3],
                    'trial_days' => $planRow[4],
                    'is_active' => true,
                    'sort_order' => $index,
                ]);
            }

            $created->put($product->slug, $product);
        }

        if ($mode === StoreMode::MultiVendor) {
            $this->seedVendors($store, $created);
        }

        $this->seedCustomers($store);
        $this->seedCoupons($store);
        $this->seedTax($store);
        $this->seedOrders($store, $created);

        if ($mode === StoreMode::Subscription) {
            $this->seedSubscriptions($store, $created);
        }

        $store->update(['settings' => array_merge($store->settings ?? [], [
            'hero_eyebrow' => 'The everyday edit',
            'hero_title' => 'Good design, made to last.',
            'hero_subtitle' => 'Considered pieces for your home, your desk and your everyday rituals.',
            'announcement' => 'Free shipping on orders over $75',
            'footer_text' => 'Small-batch goods chosen with care.',
        ])]);

        app(SettingsService::class)->setMany([
            'store.hero_eyebrow' => 'The everyday edit',
            'store.hero_title' => 'Good design, made to last.',
            'store.hero_subtitle' => 'Considered pieces for your home, your desk and your everyday rituals.',
            'store.announcement' => 'Free shipping on orders over $75',
            'store.footer_text' => 'Small-batch goods chosen with care.',
            'theme.primary_color' => '#0f766e',
            'theme.accent_color' => '#f59e0b',
        ]);
    }

    /**
     * A marketplace demo with an empty seller directory is not useful. These
     * three sellers own the existing catalog items so the directory, seller
     * pages and product attribution all have real data on day one.
     *
     * @param  \Illuminate\Support\Collection<string, Product>  $products
     */
    private function seedVendors(Store $store, $products): void
    {
        $sellers = [
            ['Ridge Studio', 'ridge-studio', 'Stoneware and linen from a small Portland workshop. Every piece is made in short runs.', ['ridge-ceramic-mug', 'loom-linen-throw']],
            ['Arc Workshop', 'arc-workshop', 'Lighting and desk tools designed for long, quiet afternoons of work.', ['arc-desk-lamp', 'field-notes-notebook']],
            ['Field Supply', 'field-supply', 'Useful things for leaving the house: canvas, a planter and a candle for the return.', ['everyday-canvas-tote', 'stoneware-planter', 'slow-burn-candle']],
        ];

        foreach ($sellers as [$name, $slug, $description, $productSlugs]) {
            $vendor = Vendor::query()->create([
                'store_id' => $store->id,
                'name' => $name,
                'slug' => $slug,
                'email' => $slug.'@example.test',
                'country' => $store->country ?: 'US',
                'description' => $description,
                'status' => Vendor::STATUS_APPROVED,
                'payout_method' => 'manual',
                'approved_at' => now(),
            ]);

            Product::query()
                ->where('store_id', $store->id)
                ->whereIn('slug', $productSlugs)
                ->where('is_wholesale_only', false)
                ->whereDoesntHave('activeSubscriptionPlans')
                ->update(['vendor_id' => $vendor->id]);
        }
    }

    private function seedTierPrices(Product $product, ?CustomerTier $wholesale, ?CustomerTier $preferred, ?ProductVariant $variant = null): void
    {
        foreach ([[$wholesale, 0.15], [$preferred, 0.25]] as [$tier, $discount]) {
            if (! $tier) {
                continue;
            }

            TierPrice::query()->create([
                'product_id' => $product->id,
                'variant_id' => $variant?->id,
                'tier_id' => $tier->id,
                'price' => round($product->price * (1 - $discount), 2),
                'min_quantity' => 1,
            ]);

            TierPrice::query()->create([
                'product_id' => $product->id,
                'variant_id' => $variant?->id,
                'tier_id' => $tier->id,
                'price' => round($product->price * (1 - $discount - 0.05), 2),
                'min_quantity' => 12,
            ]);
        }
    }

    private function seedCustomers(Store $store): void
    {
        $wholesale = CustomerTier::query()->where('slug', 'wholesale')->first();
        $preferred = CustomerTier::query()->where('slug', 'preferred-trade')->first();

        $customers = [
            ['Maya Chen', 'maya@example.com', 'approved', $preferred, 'Northstar Studio'],
            ['Elliot Brooks', 'elliot@example.com', 'approved', $wholesale, 'Brooks & Co.'],
            ['Avery Patel', 'avery@example.com', 'pending', null, 'Avery Goods'],
            ['Nora Williams', 'nora@example.com', 'none', null, null],
            ['Theo Martin', 'theo@example.com', 'none', null, null],
            ['Iris Kim', 'iris@example.com', 'approved', $wholesale, 'Iris Interiors'],
        ];

        foreach ($customers as $index => [$name, $email, $status, $tier, $company]) {
            User::query()->create([
                'store_id' => $store->id,
                'name' => $name,
                'email' => $email,
                'password' => $this->demoPassword(),
                'role' => 'customer',
                'tier_id' => $tier?->id,
                'wholesale_status' => $status,
                'company_name' => $company,
                'phone' => '+1 555 010'.($index + 1),
                'email_verified_at' => now(),
            ]);
        }

        $this->writeDemoCredentials(array_column($customers, 1));
    }

    /**
     * Demo accounts must never share a guessable password. A random secret is
     * generated per install and written to storage/app (never the web root).
     * Set SHOPKEEPRR_DEMO_PASSWORD before seeding to choose your own value.
     */
    private function demoPassword(): string
    {
        $configured = trim((string) shopkeeprr_env('DEMO_PASSWORD', ''));

        if ($configured !== '') {
            return $configured;
        }

        return self::$demoPassword ??= Str::password(20);
    }

    /**
     * @param  array<int, string>  $emails
     */
    private function writeDemoCredentials(array $emails): void
    {
        if (trim((string) shopkeeprr_env('DEMO_PASSWORD', '')) !== '') {
            return;
        }

        $path = storage_path('app/demo-credentials.txt');

        $contents = implode(PHP_EOL, [
            'ShopKeeprr demo customer accounts',
            'Generated '.now()->toDateTimeString(),
            '',
            'Password for every demo customer: '.$this->demoPassword(),
            '',
            ...array_map(fn (string $email) => '  '.$email, $emails),
            '',
            'These accounts exist for demonstration only. Change or delete them',
            'before you go live, then delete this file.',
        ]);

        if (@file_put_contents($path, $contents.PHP_EOL) !== false) {
            @chmod($path, 0600);
        }

        if (isset($this->command)) {
            $this->command->warn('Demo customer password: '.$this->demoPassword());
            $this->command->line('Saved to storage/app/demo-credentials.txt');
        }
    }

    private function seedCoupons(Store $store): void
    {
        Coupon::query()->create([
            'store_id' => $store->id,
            'code' => 'WELCOME10',
            'type' => 'percentage',
            'value' => 10,
            'minimum_subtotal' => 40,
            'usage_limit' => 100,
            'usage_limit_per_customer' => 1,
            'is_active' => true,
            'expires_at' => now()->addMonths(3),
        ]);

        Coupon::query()->create([
            'store_id' => $store->id,
            'code' => 'FREESHIP',
            'type' => 'free_shipping',
            'value' => 0,
            'minimum_subtotal' => 60,
            'is_active' => true,
        ]);

        Coupon::query()->create([
            'store_id' => $store->id,
            'code' => 'TRADE15',
            'type' => 'percentage',
            'value' => 15,
            'minimum_subtotal' => 250,
            'usage_limit_per_customer' => 3,
            'is_active' => true,
        ]);
    }

    private function seedTax(Store $store): void
    {
        TaxRate::query()->create([
            'store_id' => $store->id,
            'name' => 'Sales tax (demo)',
            'country' => 'US',
            'state' => null,
            'rate' => 8.25,
            'is_active' => true,
            'priority' => 0,
        ]);
    }

    private function seedOrders(Store $store, $products): void
    {
        $customers = User::query()->where('store_id', $store->id)->where('role', 'customer')->get();
        $mug = $products->get('ridge-ceramic-mug');
        $throw = $products->get('loom-linen-throw');
        $lamp = $products->get('arc-desk-lamp');
        $tote = $products->get('everyday-canvas-tote');
        $candle = $products->get('slow-burn-candle');
        $coffee = $products->get('roasters-choice-coffee');

        $orders = [
            [0, 'paid', 'completed', [$throw, 1]],
            [1, 'paid', 'processing', [$mug, 12]],
            [2, 'pending', 'pending', [$lamp, 1]],
            [3, 'paid', 'completed', [$tote, 1], [$candle, 1]],
            [4, 'paid', 'completed', [$coffee, 2]],
            [5, 'failed', 'failed', [$mug, 4]],
            [1, 'paid', 'completed', [$tote, 3]],
        ];

        foreach ($orders as $index => $row) {
            [$customerIndex, $paymentStatus, $status, $lineOne] = $row;
            $customer = $customers[$customerIndex] ?? $customers->first();
            $lines = array_slice($row, 3);
            $subtotal = 0;
            $items = [];

            foreach ($lines as [$product, $qty]) {
                $unit = $product->price;
                $subtotal += $unit * $qty;
                $items[] = [
                    'product' => $product,
                    'quantity' => $qty,
                    'unit' => $unit,
                    'line' => $unit * $qty,
                ];
            }

            $shipping = $subtotal >= 75 ? 0 : 8;
            $tax = round($subtotal * 0.0825, 2);
            $total = round($subtotal + $shipping + $tax, 2);
            $placed = now()->subDays(18 - $index)->setTime(10 + $index, 15);

            $order = Order::query()->create([
                'store_id' => $store->id,
                'user_id' => $customer->id,
                'number' => 'NC-DEMO-'.(1001 + $index),
                'status' => $status,
                'payment_status' => $paymentStatus,
                'fulfillment_status' => $status === 'completed' ? 'fulfilled' : 'unfulfilled',
                'currency' => 'USD',
                'subtotal' => $subtotal,
                'shipping_total' => $shipping,
                'tax_total' => $tax,
                'grand_total' => $total,
                'paid_total' => $paymentStatus === 'paid' ? $total : 0,
                'customer_email' => $customer->email,
                'customer_name' => $customer->name,
                'customer_phone' => $customer->phone,
                'shipping_address' => [
                    'name' => $customer->name,
                    'line1' => '18 Alder Street',
                    'city' => 'Portland',
                    'state' => 'OR',
                    'postcode' => '97205',
                    'country' => 'US',
                ],
                'billing_address' => [
                    'name' => $customer->name,
                    'line1' => '18 Alder Street',
                    'city' => 'Portland',
                    'state' => 'OR',
                    'postcode' => '97205',
                    'country' => 'US',
                ],
                'shipping_method' => 'standard',
                'shipping_method_title' => 'Standard shipping',
                'payment_method' => $paymentStatus === 'failed' ? 'stripe' : 'stripe',
                'transaction_id' => $paymentStatus === 'paid' ? 'pi_demo_'.Str::lower(Str::random(14)) : null,
                'mode' => nexus_mode()?->value ?? ($store->settings['mode'] ?? 'retail'),
                'placed_at' => $placed,
                'paid_at' => $paymentStatus === 'paid' ? $placed->copy()->addMinutes(4) : null,
            ]);

            $order->created_at = $placed;
            $order->updated_at = $placed;
            $order->saveQuietly();

            foreach ($items as $item) {
                OrderItem::query()->create([
                    'order_id' => $order->id,
                    'product_id' => $item['product']->id,
                    'product_name' => $item['product']->name,
                    'sku' => $item['product']->sku,
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit'],
                    'line_subtotal' => $item['line'],
                    'line_total' => $item['line'],
                ]);
            }

            if ($paymentStatus === 'paid') {
                Payment::query()->create([
                    'order_id' => $order->id,
                    'gateway' => 'stripe',
                    'gateway_payment_id' => $order->transaction_id,
                    'amount' => $total,
                    'currency' => 'USD',
                    'status' => 'succeeded',
                    'paid_at' => $order->paid_at,
                ]);
            }
        }
    }

    private function seedSubscriptions(Store $store, $products): void
    {
        $coffee = $products->get('roasters-choice-coffee');
        $pantry = $products->get('seasonal-pantry-box');
        $customer = User::query()->where('email', 'nora@example.com')->first();
        $trade = User::query()->where('email', 'maya@example.com')->first();

        if (! $coffee || ! $customer) {
            return;
        }

        $monthly = $coffee->subscriptionPlans()->first();
        $quarterly = $pantry?->subscriptionPlans()->first();

        if ($monthly) {
            Subscription::query()->create([
                'store_id' => $store->id,
                'user_id' => $customer->id,
                'product_id' => $coffee->id,
                'subscription_plan_id' => $monthly->id,
                'gateway' => 'stripe',
                'gateway_subscription_id' => 'sub_demo_'.Str::lower(Str::random(12)),
                'status' => 'active',
                'quantity' => 1,
                'price' => $monthly->price,
                'currency' => 'USD',
                'interval' => $monthly->interval,
                'interval_count' => $monthly->interval_count,
                'current_period_start' => now()->subDays(12),
                'current_period_end' => now()->addDays(18),
                'next_billing_at' => now()->addDays(18),
            ]);
        }

        if ($quarterly && $trade) {
            Subscription::query()->create([
                'store_id' => $store->id,
                'user_id' => $trade->id,
                'product_id' => $pantry->id,
                'subscription_plan_id' => $quarterly->id,
                'gateway' => 'stripe',
                'gateway_subscription_id' => 'sub_demo_'.Str::lower(Str::random(12)),
                'status' => 'past_due',
                'quantity' => 1,
                'price' => $quarterly->price,
                'currency' => 'USD',
                'interval' => $quarterly->interval,
                'interval_count' => $quarterly->interval_count,
                'current_period_start' => now()->subDays(96),
                'current_period_end' => now()->subDays(6),
                'next_billing_at' => now()->subDays(6),
                'failed_payments_count' => 1,
            ]);
        }
    }
}
