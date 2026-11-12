<?php

namespace App\Services;

use App\Enums\StoreMode;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SettingsService
{
    private const CACHE_KEY = 'nexuscart.settings';

    /** @var array<string, array{value: mixed, type: string}>|null */
    private ?array $loaded = null;

    /**
     * Default values keep the storefront usable before a setting is saved.
     *
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'store.name' => 'ShopKeeprr',
            'store.tagline' => 'Thoughtfully made goods for everyday life.',
            'store.email' => '',
            'store.phone' => '',
            'store.address' => '',
            'store.currency' => 'USD',
            'store.currency_symbol' => '$',
            'store.country' => 'US',
            'store.logo' => '',
            'store.mode' => null,
            'store.mode_revision' => 0,
            'store.live' => false,
            'store.setup_completed' => false,
            'store.announcement' => 'Free shipping on orders over $75',
            'store.hero_eyebrow' => 'The everyday edit',
            'store.hero_title' => 'Good design, made to last.',
            'store.hero_subtitle' => 'Considered pieces for your home, your desk and your everyday rituals.',
            'store.hero_image' => '',
            'store.footer_text' => 'Small-batch goods chosen with care.',
            'store.social_links' => [],
            'store.terms_page' => '',
            'store.privacy_page' => '',
            'checkout.guest_checkout' => true,
            'checkout.require_phone' => false,
            'checkout.min_order_total' => 0,
            'checkout.order_prefix' => 'NC',
            'orders.abandoned_after_hours' => 24,
            'orders.abandoned_retention_days' => 30,
            'tax.prices_include_tax' => false,
            'tax.shipping_taxable' => false,
            'tax.default_rate' => 0,
            'shipping.free_threshold' => 75,
            'b2b.hide_prices_guests' => true,
            'b2b.require_approval' => true,
            'b2b.min_order_total' => 250,
            'b2b.default_tier' => '',
            'subscriptions.grace_days' => 3,
            'subscriptions.auto_renew' => true,
            'marketplace.commission_type' => 'percentage',
            'marketplace.commission_percent' => 10,
            'marketplace.commission_fixed' => 0,
            'marketplace.payout_hold_days' => 7,
            'marketplace.minimum_payout' => 25,
            'marketplace.auto_payout' => true,
            'payments.stripe.enabled' => false,
            'payments.stripe.mode' => 'test',
            'payments.stripe.publishable_key' => '',
            'payments.stripe.secret_key' => '',
            'payments.stripe.webhook_secret' => '',
            'payments.paypal.enabled' => false,
            'payments.paypal.mode' => 'sandbox',
            'payments.paypal.client_id' => '',
            'payments.paypal.client_secret' => '',
            'payments.paypal.webhook_id' => '',
            'payments.manual.enabled' => true,
            'payments.manual.instructions' => 'We will email you payment instructions after your order is placed.',
            'mail.mailer' => 'log',
            'mail.host' => '127.0.0.1',
            'mail.port' => 2525,
            'mail.username' => '',
            'mail.password' => '',
            'mail.encryption' => 'tls',
            'mail.from_address' => '',
            'mail.from_name' => '',
            'theme.preset' => 'editorial',
            'theme.primary_color' => '#0f766e',
            'theme.accent_color' => '#f59e0b',
            'theme.heading_font' => 'Fraunces',
            'theme.body_font' => 'Inter',
            'theme.product_columns' => 3,
            'theme.custom_css' => '',
        ];
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $all = $this->all();

        if (array_key_exists($key, $all)) {
            return $all[$key];
        }

        return $default ?? (self::defaults()[$key] ?? null);
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->all());
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        $loaded = $this->loaded ??= $this->load();

        $values = [];
        foreach (self::defaults() as $key => $default) {
            $values[$key] = $default;
        }

        foreach ($loaded as $key => $row) {
            $values[$key] = $this->cast($row['value'], $row['type'], $key);
        }

        return $values;
    }

    public function set(string $key, mixed $value, ?string $group = null, bool $encrypted = false): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        $type = $encrypted ? 'encrypted' : $this->detectType($value);
        $stored = $this->serialize($value, $type, $encrypted);

        $group = $group ?: (str_contains($key, '.') ? str($key)->before('.')->toString() : 'general');

        DB::table('settings')->updateOrInsert(
            ['key' => $key],
            [
                'group' => $group,
                'value' => $stored,
                'type' => $type,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $this->loaded = null;
        cache()->forget(self::CACHE_KEY);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function setMany(array $values, ?string $group = null): void
    {
        foreach ($values as $key => $value) {
            $this->set((string) $key, $value, $group);
        }
    }

    public function forget(string $key): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        DB::table('settings')->where('key', $key)->delete();
        $this->loaded = null;
        cache()->forget(self::CACHE_KEY);
    }

    public function flushCache(): void
    {
        $this->loaded = null;
        cache()->forget(self::CACHE_KEY);
    }

    public function mode(): ?StoreMode
    {
        $mode = $this->get('store.mode');

        return $mode instanceof StoreMode ? $mode : ($mode ? StoreMode::tryFrom((string) $mode) : null);
    }

    public function setMode(?StoreMode $mode): void
    {
        $this->set('store.mode', $mode?->value, 'store');
    }

    public function modeRevision(): int
    {
        return max(0, (int) $this->get('store.mode_revision', 0));
    }

    public function setModeRevision(int $revision): void
    {
        $this->set('store.mode_revision', max(0, $revision), 'store');
    }

    public function isLive(): bool
    {
        return (bool) $this->get('store.live', false) && $this->mode() !== null;
    }

    public function isInstalled(): bool
    {
        return nexus_installed();
    }

    /**
     * Apply SMTP settings saved by the administrator without editing .env.
     */
    public function applyMailConfiguration(): void
    {
        try {
            if (! Schema::hasTable('settings')) {
                return;
            }

            $mailer = (string) $this->get('mail.mailer', 'log');
            if ($mailer === 'smtp') {
                $encryption = (string) $this->get('mail.encryption', 'tls');
                config([
                    'mail.default' => 'smtp',
                    'mail.mailers.smtp.host' => $this->get('mail.host'),
                    'mail.mailers.smtp.port' => (int) $this->get('mail.port'),
                    'mail.mailers.smtp.username' => $this->get('mail.username'),
                    'mail.mailers.smtp.password' => $this->get('mail.password'),
                    'mail.mailers.smtp.scheme' => $encryption === 'ssl' ? 'smtps' : 'smtp',
                ]);
            } else {
                config(['mail.default' => $mailer]);
            }

            $from = (string) $this->get('mail.from_address');
            if ($from !== '') {
                config(['mail.from.address' => $from]);
            }

            $fromName = (string) $this->get('mail.from_name');
            if ($fromName !== '') {
                config(['mail.from.name' => $fromName]);
            }
        } catch (\Throwable) {
            // A broken mail setting must never take the storefront down.
        }
    }

    /**
     * @return array<string, array{value: mixed, type: string}>
     */
    private function load(): array
    {
        if (! Schema::hasTable('settings')) {
            return [];
        }

        try {
            $rows = DB::table('settings')->get(['key', 'value', 'type']);
        } catch (\Throwable) {
            return [];
        }

        $loaded = [];
        foreach ($rows as $row) {
            $loaded[$row->key] = ['value' => $row->value, 'type' => $row->type];
        }

        return $loaded;
    }

    private function cast(mixed $value, string $type, string $key): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'json' => json_decode((string) $value, true) ?? [],
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $value,
            'encrypted' => $this->decrypt((string) $value),
            default => $value,
        };
    }

    private function decrypt(string $value): string
    {
        try {
            return Crypt::decryptString($value);
        } catch (\Throwable) {
            return '';
        }
    }

    private function detectType(mixed $value): string
    {
        return match (true) {
            is_array($value) => 'json',
            is_bool($value) => 'boolean',
            is_int($value) => 'integer',
            default => 'string',
        };
    }

    private function serialize(mixed $value, string $type, bool $encrypted): ?string
    {
        if ($value === null) {
            return null;
        }

        $serialized = match ($type) {
            'json' => json_encode($value),
            'boolean' => $value ? '1' : '0',
            default => (string) $value,
        };

        return $encrypted ? Crypt::encryptString($serialized) : $serialized;
    }
}
