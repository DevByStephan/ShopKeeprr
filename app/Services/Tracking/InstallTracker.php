<?php

namespace App\Services\Tracking;

use App\Models\Module;
use App\Models\Store;
use App\Models\User;
use App\Services\SettingsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class InstallTracker
{
    public function __construct(private readonly SettingsService $settings)
    {
    }

    public function enabled(): bool
    {
        return (bool) config('tracking.enabled', false) && filled(config('tracking.endpoint'));
    }

    /**
     * @return array<string, mixed>|null
     */
    public function current(): ?array
    {
        $path = (string) config('tracking.file');

        if (! is_file($path)) {
            return null;
        }

        $data = json_decode((string) file_get_contents($path), true);

        return is_array($data) && ! empty($data['install_id']) ? $data : null;
    }

    /**
     * Register an installation once. A repeated call is sent as a heartbeat.
     *
     * @return array{ok: bool, status: string, message: string}
     */
    public function register(?Store $store = null, ?User $admin = null, bool $force = false): array
    {
        if (! $this->enabled()) {
            return ['ok' => false, 'status' => 'disabled', 'message' => 'Installation tracking is disabled.'];
        }

        $existing = $this->current();

        if ($existing && ! $force) {
            return $this->heartbeat();
        }

        $store ??= Store::default();
        $admin ??= User::query()->where('role', 'admin')->orderBy('id')->first();

        $record = [
            'install_id' => $existing['install_id'] ?? (string) Str::uuid(),
            'installed_at' => $existing['installed_at'] ?? now()->toIso8601String(),
            'admin_email' => $admin?->email ?? ($existing['admin_email'] ?? null),
            'domain' => $this->domain($store?->domain),
            'site_url' => $this->siteUrl(),
            'last_sent_at' => null,
            'last_status' => 'pending',
        ];

        $this->write($record);

        return $this->send($record, 'register');
    }

    /**
     * @return array{ok: bool, status: string, message: string}
     */
    public function heartbeat(): array
    {
        if (! $this->enabled()) {
            return ['ok' => false, 'status' => 'disabled', 'message' => 'Installation tracking is disabled.'];
        }

        $record = $this->current();

        if (! $record) {
            return $this->register();
        }

        return $this->send($record, 'heartbeat');
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array{ok: bool, status: string, message: string}
     */
    private function send(array $record, string $event): array
    {
        $payload = [
            'install_id' => $record['install_id'],
            'domain' => $this->domain($record['domain'] ?? null),
            'site_url' => $this->siteUrl(),
            'admin_email' => $record['admin_email'] ?? null,
            'app_version' => (string) config('app.version', '1.0.0'),
            'store_mode' => $this->setting('store.mode'),
            'theme' => $this->setting('theme.preset'),
            'php_version' => PHP_VERSION,
            'database_driver' => $this->databaseDriver(),
            'module_count' => $this->moduleCount(),
        ];

        try {
            $response = Http::acceptJson()
                ->timeout(max(2, (int) config('tracking.timeout', 8)))
                ->post(rtrim((string) config('tracking.endpoint'), '/').'/'.$event, $payload);

            $ok = $response->successful();
            $record['last_sent_at'] = now()->toIso8601String();
            $record['last_status'] = $ok ? 'sent' : 'http_'.$response->status();
            $record['last_payload'] = $payload;
            $this->write($record);

            return [
                'ok' => $ok,
                'status' => $record['last_status'],
                'message' => $ok
                    ? 'Installation '.$event.' sent to the ShopKeeprr store.'
                    : 'The store returned HTTP '.$response->status().'.',
            ];
        } catch (Throwable $exception) {
            $record['last_sent_at'] = now()->toIso8601String();
            $record['last_status'] = 'failed';
            $this->write($record);

            Log::debug('Installation tracking request failed.', ['error' => $exception->getMessage()]);

            return [
                'ok' => false,
                'status' => 'failed',
                'message' => 'Could not reach the ShopKeeprr store right now. The installation is complete; tracking will retry automatically.',
            ];
        }
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function write(array $record): void
    {
        $path = (string) config('tracking.file');
        $directory = dirname($path);

        if (! is_dir($directory)) {
            @mkdir($directory, 0775, true);
        }

        @file_put_contents($path, json_encode($record, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
    }

    private function siteUrl(): string
    {
        return mb_substr(rtrim((string) config('app.url', url('/')), '/'), 0, 500);
    }

    private function domain(?string $fallback = null): string
    {
        $host = parse_url($this->siteUrl(), PHP_URL_HOST);

        if (! $host) {
            $host = $fallback ?: 'localhost';
        }

        return preg_replace('/^www\./i', '', strtolower((string) $host)) ?: 'localhost';
    }

    private function setting(string $key): ?string
    {
        try {
            if (! Schema::hasTable('settings')) {
                return null;
            }

            $value = $this->settings->get($key);

            return $value === null || $value === '' ? null : mb_substr((string) $value, 0, 80);
        } catch (Throwable) {
            return null;
        }
    }

    private function databaseDriver(): ?string
    {
        try {
            return DB::connection()->getDriverName();
        } catch (Throwable) {
            return null;
        }
    }

    private function moduleCount(): int
    {
        try {
            return Schema::hasTable('modules') ? Module::query()->where('is_enabled', true)->count() : 0;
        } catch (Throwable) {
            return 0;
        }
    }
}
