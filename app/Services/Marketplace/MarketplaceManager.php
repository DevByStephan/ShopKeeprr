<?php

namespace App\Services\Marketplace;

use App\Models\MarketplaceConnection;
use App\Models\Module;
use App\Services\ModuleManager;
use App\Services\SettingsService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Throwable;

class MarketplaceManager
{
    public function __construct(
        private readonly MarketplaceClient $client,
        private readonly SettingsService $settings,
        private readonly ModuleManager $modules,
    ) {
    }

    public function connection(): ?MarketplaceConnection
    {
        return MarketplaceConnection::current();
    }

    public function isConnected(): bool
    {
        return (bool) $this->connection()?->isConnected();
    }

    public function siteId(): string
    {
        $siteId = $this->settings->get('marketplace.site_id');

        if (! is_string($siteId) || ! Str::isUuid($siteId)) {
            $siteId = (string) Str::uuid();
            $this->settings->set('marketplace.site_id', $siteId, 'marketplace');
        }

        return $siteId;
    }

    /**
     * @return array<string, mixed>
     */
    public function startLink(): array
    {
        $site = [
            'site_id' => $this->siteId(),
            'site_url' => url('/'),
            'site_name' => (string) nexus_setting('store.name', config('app.name', 'ShopKeeprr')),
            'platform_version' => (string) config('app.version', '1.1.0'),
        ];

        $data = $this->client->startLink($site);

        Session::put('marketplace.link', [
            'device_code' => $data['device_code'] ?? null,
            'user_code' => $data['user_code'] ?? null,
            'verification_url' => $data['verification_url'] ?? null,
            'expires_at' => $data['expires_at'] ?? null,
            'site' => $site,
        ]);

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    public function pendingLink(): array
    {
        return (array) Session::get('marketplace.link', []);
    }

    /**
     * @return array{status: string, connection?: MarketplaceConnection}
     */
    public function pollLink(): array
    {
        $pending = $this->pendingLink();
        $deviceCode = (string) ($pending['device_code'] ?? '');

        if ($deviceCode === '') {
            throw new MarketplaceException('This Store connection request has expired. Start again.', 410, 'link_expired');
        }

        $data = $this->client->pollLink($deviceCode);

        if (($data['status'] ?? 'pending') !== 'connected') {
            return ['status' => 'pending'];
        }

        $token = trim((string) ($data['token'] ?? ''));
        $account = (array) ($data['account'] ?? []);
        $site = (array) ($pending['site'] ?? []);

        if ($token === '') {
            throw new MarketplaceException('The Store approved the site but did not return an access token.');
        }

        $connection = MarketplaceConnection::query()->updateOrCreate(
            ['site_id' => (string) ($site['site_id'] ?? $data['site']['site_id'] ?? $this->siteId())],
            [
                'store_url' => $this->client->baseUrl(),
                'site_name' => (string) ($site['site_name'] ?? $data['site']['site_name'] ?? ''),
                'account_name' => (string) ($account['name'] ?? ''),
                'account_email' => (string) ($account['email'] ?? ''),
                'access_token' => $token,
                'token_last_four' => substr($token, -4),
                'connected_at' => now(),
                'last_synced_at' => now(),
                'meta' => [
                    'site_url' => $site['site_url'] ?? null,
                    'platform_version' => $site['platform_version'] ?? null,
                ],
            ]
        );

        Session::forget('marketplace.link');
        Cache::forget($this->catalogCacheKey());

        return ['status' => 'connected', 'connection' => $connection];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function catalog(): array
    {
        return Cache::remember(
            $this->catalogCacheKey(),
            max(30, (int) config('marketplace.catalog_ttl', 300)),
            fn (): array => $this->client->catalog(),
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function library(): array
    {
        $connection = $this->requireConnection();

        return (new MarketplaceClient($connection, $connection->store_url))->library();
    }

    public function install(string $slug, ?string $version = null): Module
    {
        $connection = $this->requireConnection();
        $client = new MarketplaceClient($connection, $connection->store_url);
        $data = $client->install($slug, $version);

        $url = (string) ($data['download_url'] ?? '');
        $expectedHash = strtolower((string) ($data['sha256'] ?? ''));

        if ($url === '' || $expectedHash === '') {
            throw new MarketplaceException('The Store did not return a verifiable package.');
        }

        $this->assertDownloadUrl($connection, $url);

        $temp = storage_path('app/marketplace-'.Str::random(20).'.zip');
        @mkdir(dirname($temp), 0775, true);

        try {
            $response = Http::withOptions([
                'sink' => $temp,
                'allow_redirects' => false,
            ])
                ->timeout(120)
                ->withHeaders(['User-Agent' => (string) config('marketplace.user_agent')])
                ->get($url);

            if (! $response->successful()) {
                throw new MarketplaceException('The package download failed (HTTP '.$response->status().').');
            }

            $size = @filesize($temp) ?: 0;
            $maxBytes = max(1, (int) config('marketplace.max_package_mb', 20)) * 1024 * 1024;

            if ($size <= 0) {
                throw new MarketplaceException('The downloaded package is empty.');
            }

            if ($size > $maxBytes) {
                throw new MarketplaceException('The package is larger than the configured marketplace limit.');
            }

            $actualHash = hash_file('sha256', $temp);

            if (! is_string($actualHash) || ! hash_equals($expectedHash, strtolower($actualHash))) {
                throw new MarketplaceException('The package checksum did not match the Store record. Installation was stopped.');
            }

            $manifest = (array) ($data['manifest'] ?? []);

            if (! empty($manifest['slug']) && (string) $manifest['slug'] !== $slug) {
                throw new MarketplaceException('The package manifest does not match the selected marketplace product.');
            }

            $module = $this->modules->installArchive($temp, (string) ($data['file_name'] ?? $slug.'.zip'));

            $connection->forceFill(['last_synced_at' => now()])->save();

            return $module;
        } catch (MarketplaceException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            throw new MarketplaceException('The package could not be installed: '.$exception->getMessage());
        } finally {
            if (is_file($temp)) {
                @unlink($temp);
            }
        }
    }

    public function disconnect(): void
    {
        $connection = $this->connection();

        if (! $connection) {
            return;
        }

        try {
            (new MarketplaceClient($connection, $connection->store_url))->revoke();
        } catch (Throwable $exception) {
            report($exception);
        }

        $connection->delete();
        Cache::forget($this->catalogCacheKey());
    }

    public function clearCatalogCache(): void
    {
        Cache::forget($this->catalogCacheKey());
    }

    public function catalogCacheKey(): string
    {
        return 'marketplace.catalog.'.sha1((string) config('marketplace.store_url'));
    }

    private function requireConnection(): MarketplaceConnection
    {
        $connection = $this->connection();

        if (! $connection?->isConnected()) {
            throw new MarketplaceException('Connect a ShopKeeprr Store account from Settings before installing a plugin.', 401, 'not_connected');
        }

        return $connection;
    }

    private function assertDownloadUrl(MarketplaceConnection $connection, string $url): void
    {
        $storeHost = strtolower((string) parse_url($connection->store_url, PHP_URL_HOST));
        $downloadHost = strtolower((string) parse_url($url, PHP_URL_HOST));
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        if ($storeHost === '' || $downloadHost === '' || ! hash_equals($storeHost, $downloadHost)) {
            throw new MarketplaceException('The package URL is not hosted by the connected ShopKeeprr Store.');
        }

        $local = in_array($downloadHost, ['localhost', '127.0.0.1', '::1'], true);

        if ($scheme !== 'https' && ! ($local && config('marketplace.allow_http'))) {
            throw new MarketplaceException('The package URL must use HTTPS.');
        }
    }
}
