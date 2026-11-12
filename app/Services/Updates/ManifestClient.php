<?php

namespace App\Services\Updates;

use App\Support\Version;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class ManifestClient
{
    private const CACHE_MINUTES = 15;

    public function channel(): string
    {
        return (string) config('nexuscart.updates.channel', 'stable');
    }

    public function manifestUrl(): string
    {
        return trim((string) config('nexuscart.updates.manifest_url'));
    }

    public function lastCheckedAt(): ?string
    {
        $cached = Cache::get($this->cacheKey());

        return is_array($cached) ? ($cached['fetched_at'] ?? null) : null;
    }

    public function cached(): ?Manifest
    {
        $cached = Cache::get($this->cacheKey());

        if (! is_array($cached) || ! isset($cached['data']) || ! is_array($cached['data'])) {
            return null;
        }

        try {
            return Manifest::fromArray($cached['data'], $this->channel());
        } catch (Throwable) {
            return null;
        }
    }

    public function fetch(bool $force = false): Manifest
    {
        if (! (bool) config('nexuscart.updates.enabled', true)) {
            throw new UpdateException('Updates are disabled in this installation.');
        }

        if (! $force) {
            $cached = $this->cached();
            if ($cached !== null) {
                return $cached;
            }
        }

        $url = $this->manifestUrl();

        if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw new UpdateException('The update server URL is not configured. Set SHOPKEEPRR_UPDATE_URL in .env.');
        }

        $this->assertTransportIsTrusted($url, 'update server');

        $requestUrl = $url.(str_contains($url, '?') ? '&' : '?').http_build_query([
            'channel' => $this->channel(),
            'version' => Version::current(),
            'php' => PHP_VERSION,
        ]);

        try {
            $response = Http::acceptJson()
                ->withUserAgent('ShopKeeprr/'.Version::current().' (updater)')
                ->timeout(max(5, (int) config('nexuscart.updates.timeout', 20)))
                ->get($requestUrl);
        } catch (ConnectionException $exception) {
            throw new UpdateException('Could not reach the update server: '.$exception->getMessage());
        }

        if (! $response->successful()) {
            throw new UpdateException('The update server returned HTTP '.$response->status().'. Try again later.');
        }

        $data = $response->json();

        if (! is_array($data)) {
            throw new UpdateException('The update server did not return a valid JSON manifest.');
        }

        try {
            $manifest = Manifest::fromArray($data, $this->channel());
        } catch (Throwable $exception) {
            throw new UpdateException('The update manifest is invalid: '.$exception->getMessage());
        }

        Cache::put($this->cacheKey(), [
            'data' => $data,
            'fetched_at' => now()->toIso8601String(),
        ], now()->addMinutes(self::CACHE_MINUTES));

        return $manifest;
    }

    public function clearCache(): void
    {
        Cache::forget($this->cacheKey());
    }

    public function assertTransportIsTrusted(string $url, string $label): void
    {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $loopback = in_array($host, ['localhost', '127.0.0.1', '::1'], true);

        if ($scheme === 'https' || $loopback) {
            return;
        }

        if ((bool) config('nexuscart.updates.allow_http', false)) {
            return;
        }

        throw new UpdateException("The {$label} must use HTTPS. Refusing to download over plain HTTP.");
    }

    private function cacheKey(): string
    {
        return 'nexuscart.updates.manifest.'.$this->channel();
    }
}
