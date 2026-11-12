<?php

namespace App\Services\Marketplace;

use App\Models\MarketplaceConnection;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class MarketplaceClient
{
    public function __construct(
        private ?MarketplaceConnection $connection = null,
        private ?string $storeUrl = null,
    ) {
    }

    public function forConnection(MarketplaceConnection $connection): self
    {
        $clone = clone $this;
        $clone->connection = $connection;
        $clone->storeUrl = $connection->store_url;

        return $clone;
    }

    public function baseUrl(): string
    {
        $url = rtrim((string) ($this->storeUrl ?: config('marketplace.store_url')), '/');
        $parts = parse_url($url);

        if (! is_array($parts) || empty($parts['host']) || empty($parts['scheme'])) {
            throw new MarketplaceException('The ShopKeeprr Store URL is not configured correctly.');
        }

        $host = strtolower((string) $parts['host']);
        $local = in_array($host, ['localhost', '127.0.0.1', '::1'], true);

        if (($parts['scheme'] ?? '') !== 'https' && ! ($local && config('marketplace.allow_http'))) {
            throw new MarketplaceException('The ShopKeeprr Store must use HTTPS.');
        }

        if (isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
            throw new MarketplaceException('The ShopKeeprr Store URL contains unsupported credentials or query parameters.');
        }

        return $url;
    }

    /**
     * @param  array<string, mixed>  $site
     * @return array<string, mixed>
     */
    public function startLink(array $site): array
    {
        return $this->data(
            $this->http()->post('api/v1/marketplace/link/start', $site)
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function pollLink(string $deviceCode): array
    {
        return $this->data(
            $this->http()->post('api/v1/marketplace/link/poll', [
                'device_code' => $deviceCode,
            ])
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function catalog(): array
    {
        $json = $this->payload($this->http()->get('api/v1/marketplace/catalog'));

        return is_array($json['data'] ?? null) ? $json['data'] : [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function library(): array
    {
        $json = $this->payload($this->http()->withToken($this->token())->get('api/v1/marketplace/library'));

        return is_array($json['data'] ?? null) ? $json['data'] : [];
    }

    /**
     * @return array<string, mixed>
     */
    public function account(): array
    {
        return $this->data($this->http()->withToken($this->token())->get('api/v1/marketplace/account'));
    }

    /**
     * @return array<string, mixed>
     */
    public function install(string $slug, ?string $version = null): array
    {
        return $this->data(
            $this->http()
                ->withToken($this->token())
                ->post('api/v1/marketplace/install', array_filter([
                    'product' => $slug,
                    'version' => $version,
                ]))
        );
    }

    public function revoke(): void
    {
        if (! $this->connection?->isConnected()) {
            return;
        }

        $this->http()->withToken($this->token())->post('api/v1/marketplace/link/revoke');
    }

    private function http(): PendingRequest
    {
        $request = Http::baseUrl($this->baseUrl())
            ->acceptJson()
            ->asJson()
            ->timeout(max(5, (int) config('marketplace.timeout', 20)))
            ->withOptions(['allow_redirects' => false])
            ->withHeaders(['User-Agent' => (string) config('marketplace.user_agent')]);

        return $request;
    }

    private function token(): string
    {
        $token = trim((string) $this->connection?->access_token);

        if ($token === '') {
            throw new MarketplaceException('Connect a ShopKeeprr Store account before using the marketplace.', 401, 'not_connected');
        }

        return $token;
    }

    /**
     * @return array<string, mixed>
     */
    private function data(Response $response): array
    {
        $json = $this->payload($response);

        return is_array($json['data'] ?? null) ? $json['data'] : $json;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Response $response): array
    {
        $json = $response->json();

        if (! $response->successful() || (is_array($json) && ($json['success'] ?? true) === false)) {
            $message = is_array($json)
                ? (string) data_get($json, 'error.message', data_get($json, 'message', 'The Store rejected the request.'))
                : 'The Store rejected the request.';

            throw new MarketplaceException(
                $message,
                $response->status(),
                is_array($json) ? data_get($json, 'error.code') : null,
            );
        }

        if (! is_array($json)) {
            throw new MarketplaceException('The Store returned an invalid response.');
        }

        return $json;
    }
}
