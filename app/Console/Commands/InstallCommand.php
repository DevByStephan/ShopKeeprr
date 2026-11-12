<?php

namespace App\Console\Commands;

use App\Enums\StoreMode;
use App\Models\CustomerTier;
use App\Models\Page;
use App\Models\Store;
use App\Models\User;
use App\Services\SettingsService;
use App\Services\Tracking\InstallTracker;
use App\Support\LegalPages;
use Database\Seeders\DemoSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Throwable;

class InstallCommand extends Command
{
    protected $aliases = ['nexus:install'];

    protected $signature = 'shopkeeprr:install
        {--data= : Path to the installer JSON payload written by the browser installer}
        {--store-name= : Store name (command-line install)}
        {--store-email= : Store contact email}
        {--admin-name= : Administrator name}
        {--admin-email= : Administrator email}
        {--admin-password= : Administrator password (at least 10 characters)}
        {--mode=retail : retail, b2b, subscription or multivendor}
        {--live : Open the storefront immediately}
        {--demo : Seed demo products, orders and customers}
        {--force : Install even if a lock file exists}';

    protected $description = 'Finish a ShopKeeprr installation from the web installer payload or command-line options.';

    public function handle(SettingsService $settings): int
    {
        $path = (string) $this->option('data');

        if (nexus_installed() && ! $this->option('force')) {
            $this->error('ShopKeeprr is already installed. Remove storage/app/installed.lock to reinstall.');

            return self::FAILURE;
        }

        if ($path !== '') {
            if (! is_file($path)) {
                $this->error('The installer payload file was not found: '.$path);
                $this->line('Run without --data to install from command-line options instead.');

                return self::FAILURE;
            }

            $payload = json_decode((string) file_get_contents($path), true);

            if (! is_array($payload)) {
                $this->error('The installer payload is not valid JSON.');

                return self::FAILURE;
            }
        } else {
            $payload = $this->payloadFromOptions();

            if ($payload === null) {
                return self::FAILURE;
            }
        }

        $storeData = $payload['store'] ?? [];
        $adminData = $payload['admin'] ?? [];
        $mode = isset($storeData['mode']) && $storeData['mode'] ? StoreMode::tryFrom((string) $storeData['mode']) : null;

        if (empty($storeData['name']) || empty($adminData['email']) || empty($adminData['password'])) {
            $this->error('Store name, administrator email, and password are required.');

            return self::FAILURE;
        }

        try {
            DB::transaction(function () use ($storeData, $adminData, $mode, $settings, $payload) {
                $store = Store::query()->create([
                    'name' => $storeData['name'],
                    'slug' => Str::slug($storeData['name']).'-'.Str::lower(Str::random(4)),
                    'domain' => $storeData['domain'] ?? null,
                    'email' => $storeData['email'] ?? $adminData['email'],
                    'phone' => $storeData['phone'] ?? null,
                    'currency' => $storeData['currency'] ?? 'USD',
                    'currency_symbol' => $storeData['currency_symbol'] ?? '$',
                    'country' => $storeData['country'] ?? 'US',
                    'is_default' => true,
                ]);

                $admin = User::query()->updateOrCreate(
                    ['email' => $adminData['email']],
                    [
                        'store_id' => $store->id,
                        'name' => $adminData['name'] ?: 'Store Administrator',
                        'password' => Hash::make((string) $adminData['password']),
                        'role' => 'admin',
                        'wholesale_status' => 'none',
                        'email_verified_at' => now(),
                    ]
                );

                $settings->setMany([
                    'store.name' => $store->name,
                    'store.email' => $store->email,
                    'store.phone' => $store->phone,
                    'store.currency' => $store->currency,
                    'store.currency_symbol' => $store->currency_symbol,
                    'store.country' => $store->country,
                    'store.mode' => $mode?->value,
                    'store.live' => $mode !== null && ! empty($storeData['live']),
                    'store.setup_completed' => true,
                    'mail.from_address' => $store->email,
                    'mail.from_name' => $store->name,
                    'theme.heading_font' => 'Fraunces',
                    'theme.body_font' => 'Inter',
                ]);

                $this->seedTiers($store);
                $this->seedShipping($store);
                $this->seedPages($store);

                if (! empty($payload['demo'])) {
                    (new DemoSeeder)->seedFor($store, $mode);
                }

                $this->writeLock($store, $admin);
            });
        } catch (Throwable $exception) {
            $this->error('Installation failed: '.$exception->getMessage());

            return self::FAILURE;
        }

        @unlink($path);
        $settings->flushCache();

        $tracking = app(InstallTracker::class)->register(
            Store::default(),
            User::query()->where('email', $adminData['email'])->first(),
        );

        $this->info('ShopKeeprr installed successfully.');
        $this->line('Administrator: '.$adminData['email']);

        if (! empty($payload['demo'])) {
            $this->line('Demo data: seeded · demo customer password is in storage/app/demo-credentials.txt');
        }

        $this->line('Tracking: '.$tracking['message']);

        return self::SUCCESS;
    }

    /**
     * Build an installer payload from flags, for hosts where the browser
     * installer cannot run Artisan because proc_open is disabled.
     *
     * @return array<string, mixed>|null
     */
    private function payloadFromOptions(): ?array
    {
        $storeName = trim((string) $this->option('store-name'));
        $adminEmail = Str::lower(trim((string) $this->option('admin-email')));
        $password = (string) $this->option('admin-password');
        $mode = Str::lower(trim((string) $this->option('mode')));

        if ($storeName === '' || $adminEmail === '' || $password === '') {
            $this->error('Provide --store-name, --admin-email and --admin-password, or pass --data=payload.json.');
            $this->line('Example:');
            $this->line('  php artisan shopkeeprr:install --store-name="My Store" --admin-email=you@example.com --admin-password="a-long-password" --mode=retail --demo');

            return null;
        }

        if (! filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
            $this->error('The administrator email is not valid.');

            return null;
        }

        if (mb_strlen($password) < 10) {
            $this->error('Choose an administrator password of at least 10 characters.');

            return null;
        }

        if (! in_array($mode, array_column(StoreMode::cases(), 'value'), true)) {
            $this->error('The store mode must be retail, b2b, subscription or multivendor.');

            return null;
        }

        return [
            'store' => [
                'name' => $storeName,
                'email' => trim((string) $this->option('store-email')) ?: $adminEmail,
                'mode' => $mode,
                'live' => (bool) $this->option('live'),
            ],
            'admin' => [
                'name' => trim((string) $this->option('admin-name')) ?: 'Store Administrator',
                'email' => $adminEmail,
                'password' => $password,
            ],
            'demo' => (bool) $this->option('demo'),
        ];
    }

    private function seedTiers(Store $store): void
    {
        CustomerTier::query()->create([
            'store_id' => $store->id,
            'name' => 'Standard',
            'slug' => 'standard',
            'description' => 'Everyday retail customers.',
            'discount_percent' => 0,
            'is_wholesale' => false,
            'requires_approval' => false,
            'priority' => 0,
        ]);

        CustomerTier::query()->create([
            'store_id' => $store->id,
            'name' => 'Wholesale',
            'slug' => 'wholesale',
            'description' => 'Approved trade accounts with trade pricing.',
            'discount_percent' => 10,
            'is_wholesale' => true,
            'requires_approval' => true,
            'priority' => 10,
        ]);

        CustomerTier::query()->create([
            'store_id' => $store->id,
            'name' => 'Preferred Trade',
            'slug' => 'preferred-trade',
            'description' => 'High-volume partners with the best price breaks.',
            'discount_percent' => 20,
            'is_wholesale' => true,
            'requires_approval' => true,
            'priority' => 20,
        ]);
    }

    private function seedShipping(Store $store): void
    {
        $zone = DB::table('shipping_zones')->insertGetId([
            'store_id' => $store->id,
            'name' => 'United States',
            'countries' => json_encode(['US']),
            'states' => null,
            'is_active' => true,
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('shipping_methods')->insert([
            [
                'zone_id' => $zone,
                'name' => 'Standard shipping',
                'type' => 'flat_rate',
                'cost' => 8,
                'free_threshold' => 75,
                'is_active' => true,
                'sort_order' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'zone_id' => $zone,
                'name' => 'Express shipping',
                'type' => 'flat_rate',
                'cost' => 18,
                'free_threshold' => null,
                'is_active' => true,
                'sort_order' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    private function seedPages(Store $store): void
    {
        $storeName = $store->name;
        $storeEmail = $store->email ?: (string) config('mail.from.address');
        $storeAddress = implode(', ', array_filter([
            (string) ($store->settings['address'] ?? ''),
            (string) ($store->settings['city'] ?? ''),
            (string) ($store->settings['country'] ?? ''),
        ]));

        $pages = [
            ['About', 'about', <<<TEXT
## A small team with a simple idea

We make and curate useful, lasting goods for everyday life. Every product is chosen for how it feels, how it works and how long it will stay in use.

## How we work

We work in small batches, partner with independent makers where we can, and keep our packaging as simple as possible. If something is not right, we want to hear about it.

## Talk to us

Questions, custom requests or wholesale enquiries can be sent to {$storeEmail}.
TEXT],
            ['Shipping & Returns', 'shipping-returns', <<<TEXT
## Dispatch times

In-stock orders normally leave our studio within two business days. Made-to-order items may take longer; the product page will say so before you order.

## Shipping rates

Shipping is calculated at checkout based on your address and the delivery method you choose. Free shipping is available on qualifying orders.

## Tracking

When your order ships, we will email a tracking link. If tracking has not updated for several days, contact us and we will investigate.

## Returns

Returns are accepted within 30 days of delivery in unused, resalable condition with original packaging. Contact us with your order number to start a return. Return shipping may be your responsibility unless the item arrived damaged or incorrect.

## Damaged or missing items

If an item arrives damaged or is missing from your parcel, contact us within 7 days of delivery with photos and your order number. We will arrange a replacement or refund.
TEXT],
            ['Terms of Service', 'terms', LegalPages::terms($storeName, $storeEmail, $storeAddress)],
            ['Privacy Policy', 'privacy', LegalPages::privacy($storeName, $storeEmail, $storeAddress)],
        ];

        foreach ($pages as [$title, $slug, $content]) {
            Page::query()->create([
                'store_id' => $store->id,
                'title' => $title,
                'slug' => $slug,
                'content' => $content,
                'is_published' => true,
            ]);
        }

        $store->update(['settings' => [
            'terms_page' => 'terms',
            'privacy_page' => 'privacy',
            'shipping_page' => 'shipping-returns',
        ]]);
    }

    private function writeLock(Store $store, User $admin): void
    {
        $lockPath = storage_path('app/installed.lock');

        if (! is_dir(dirname($lockPath))) {
            mkdir(dirname($lockPath), 0775, true);
        }

        file_put_contents($lockPath, json_encode([
            'installed_at' => now()->toIso8601String(),
            'store_id' => $store->id,
            'admin_id' => $admin->id,
            'version' => config('app.version', '1.0.0'),
        ], JSON_PRETTY_PRINT));
    }
}
