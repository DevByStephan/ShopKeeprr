<?php

namespace App\Services\Import;

use App\Models\WooCommerceConnection;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

final class WooCommerceApiClient
{
    private const PER_PAGE = 50;

    /**
     * Verify that the stored key can read products, customers, orders and taxes.
     *
     * @return array{products: int, customers: int, orders: int, taxes: int}
     */
    public function testConnection(WooCommerceConnection $connection): array
    {
        $products = $this->get($connection, 'products', ['per_page' => 1, 'page' => 1]);
        $customers = $this->get($connection, 'customers', ['per_page' => 1, 'page' => 1]);
        $orders = $this->get($connection, 'orders', ['per_page' => 1, 'page' => 1]);
        $taxes = $this->get($connection, 'taxes', ['per_page' => 1, 'page' => 1]);

        return [
            'products' => (int) $products['total'],
            'customers' => (int) $customers['total'],
            'orders' => (int) $orders['total'],
            'taxes' => (int) $taxes['total'],
        ];
    }

    /**
     * @return array{items: list<array<string, mixed>>, total: int, total_pages: int}
     */
    public function products(WooCommerceConnection $connection, int $page = 1, int $perPage = self::PER_PAGE): array
    {
        return $this->get($connection, 'products', [
            'per_page' => max(1, min(100, $perPage)),
            'page' => max(1, $page),
        ]);
    }

    /**
     * @return array{items: list<array<string, mixed>>, total: int, total_pages: int}
     */
    public function customers(WooCommerceConnection $connection, int $page = 1, int $perPage = self::PER_PAGE): array
    {
        return $this->get($connection, 'customers', [
            'per_page' => max(1, min(100, $perPage)),
            'page' => max(1, $page),
        ]);
    }

    /**
     * @return array{items: list<array<string, mixed>>, total: int, total_pages: int}
     */
    public function orders(WooCommerceConnection $connection, int $page = 1, int $perPage = self::PER_PAGE): array
    {
        return $this->get($connection, 'orders', [
            'per_page' => max(1, min(100, $perPage)),
            'page' => max(1, $page),
            'orderby' => 'id',
            'order' => 'asc',
        ]);
    }

    /**
     * @return array{items: list<array<string, mixed>>, total: int, total_pages: int}
     */
    public function variations(WooCommerceConnection $connection, int $productId, int $page = 1): array
    {
        return $this->get($connection, 'products/'.$productId.'/variations', [
            'per_page' => 100,
            'page' => max(1, $page),
        ]);
    }

    /**
     * @return array{items: list<array<string, mixed>>, total: int, total_pages: int}
     */
    public function taxes(WooCommerceConnection $connection, int $page = 1, int $perPage = 100): array
    {
        return $this->get($connection, 'taxes', [
            'per_page' => max(1, min(100, $perPage)),
            'page' => max(1, $page),
        ]);
    }

    /**
     * Fetch every standard-rate page. WooCommerce tax tables are small, so one
     * migration batch can hold all of them without stressing the host.
     *
     * @return list<array<string, mixed>>
     */
    public function allTaxes(WooCommerceConnection $connection): array
    {
        $items = [];

        for ($page = 1; $page <= 100; $page++) {
            $result = $this->taxes($connection, $page);

            foreach ($result['items'] as $item) {
                if (is_array($item)) {
                    $items[] = $item;
                }
            }

            if ($page >= $result['total_pages'] || $result['items'] === []) {
                break;
            }
        }

        return $items;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function taxSettings(WooCommerceConnection $connection): array
    {
        return $this->get($connection, 'settings/tax')['items'];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function taxClasses(WooCommerceConnection $connection): array
    {
        return $this->get($connection, 'taxes/classes')['items'];
    }

    /**
     * Fetch every variation page for a variable product.
     *
     * @return list<array<string, mixed>>
     */
    public function allVariations(WooCommerceConnection $connection, int $productId): array
    {
        $items = [];

        for ($page = 1; $page <= 100; $page++) {
            $result = $this->variations($connection, $productId, $page);

            foreach ($result['items'] as $item) {
                if (is_array($item)) {
                    $items[] = $item;
                }
            }

            if ($page >= $result['total_pages'] || $result['items'] === []) {
                break;
            }
        }

        return $items;
    }

    /**
     * @return array{items: list<array<string, mixed>>, total: int, total_pages: int}
     */
    private function get(WooCommerceConnection $connection, string $path, array $query = []): array
    {
        $base = $connection->apiBaseUrl();
        $this->assertSafeBaseUrl($base);

        $url = $base.'/'.ltrim($path, '/');
        $response = $this->request($connection, $url, $query);

        if ($response->redirect()) {
            throw new RuntimeException('The WooCommerce URL redirected. Use the final HTTPS store URL without a redirect.');
        }

        if ($response->status() === 401 || $response->status() === 403) {
            throw new RuntimeException('WooCommerce rejected the API key. Create a Read key and check the consumer key and secret.');
        }

        if ($response->status() === 404) {
            throw new RuntimeException('The WooCommerce REST API was not found at this URL. Check the store URL and that the REST API is enabled.');
        }

        if (! $response->successful()) {
            $message = data_get($response->json(), 'message');

            throw new RuntimeException('WooCommerce API returned HTTP '.$response->status().(is_string($message) && $message !== '' ? ': '.$message : '.'));
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            throw new RuntimeException('WooCommerce returned an invalid JSON response.');
        }

        $items = array_values(array_filter($payload, 'is_array'));
        $total = (int) ($response->header('X-WP-Total') ?: count($items));
        $totalPages = (int) ($response->header('X-WP-TotalPages') ?: ($items === [] ? 0 : 1));

        return [
            'items' => $items,
            'total' => $total,
            'total_pages' => max(0, $totalPages),
        ];
    }

    private function request(WooCommerceConnection $connection, string $url, array $query): Response
    {
        for ($attempt = 0; $attempt < 3; $attempt++) {
            try {
                $response = Http::withBasicAuth(
                    (string) $connection->consumer_key,
                    (string) $connection->consumer_secret,
                )
                    ->withHeaders([
                        'Accept' => 'application/json',
                        'User-Agent' => 'ShopKeeprr WooCommerce migration',
                    ])
                    ->connectTimeout(10)
                    ->timeout(30)
                    ->withoutRedirecting()
                    ->get($url, $query);
            } catch (ConnectionException) {
                if ($attempt < 2) {
                    usleep(250000 * ($attempt + 1));

                    continue;
                }

                throw new RuntimeException('Could not connect to the WooCommerce store. Check the URL and that the store allows outgoing API access.');
            } catch (Throwable $exception) {
                throw new RuntimeException('The WooCommerce request failed: '.$exception->getMessage());
            }

            if (in_array($response->status(), [429, 500, 502, 503, 504], true) && $attempt < 2) {
                usleep(250000 * ($attempt + 1));

                continue;
            }

            return $response;
        }

        throw new RuntimeException('The WooCommerce request failed after retries.');
    }

    /**
     * @return array<string, mixed>
     */
    private function assertSafeBaseUrl(string $url): array
    {
        $parts = parse_url($url);

        if (! is_array($parts) || strtolower((string) ($parts['scheme'] ?? '')) !== 'https') {
            throw new RuntimeException('Use an HTTPS WooCommerce store URL. Basic authentication and customer data must be sent over HTTPS.');
        }

        if (isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
            throw new RuntimeException('Enter the store URL without credentials, a query string or a fragment.');
        }

        $host = strtolower(trim((string) ($parts['host'] ?? '')));

        if ($host === '' || $host === 'localhost' || str_ends_with($host, '.local') || str_ends_with($host, '.internal')) {
            throw new RuntimeException('The WooCommerce host is not a public HTTPS host.');
        }

        $addresses = [];

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $addresses[] = $host;
        } else {
            $ipv4 = gethostbynamel($host);

            if (is_array($ipv4)) {
                $addresses = array_merge($addresses, $ipv4);
            }

            foreach (dns_get_record($host, DNS_AAAA) ?: [] as $record) {
                if (! empty($record['ipv6'])) {
                    $addresses[] = $record['ipv6'];
                }
            }
        }

        if ($addresses === []) {
            throw new RuntimeException('The WooCommerce host could not be resolved.');
        }

        foreach ($addresses as $address) {
            if (! $this->isPublicAddress($address)) {
                throw new RuntimeException('The WooCommerce host resolves to a private or reserved address.');
            }
        }

        return $parts;
    }

    private function isPublicAddress(string $address): bool
    {
        if (! filter_var($address, FILTER_VALIDATE_IP)) {
            return false;
        }

        if (! filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return false;
        }

        if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $long = ip2long($address);

            if ($long === false) {
                return false;
            }

            if (($long >= ip2long('100.64.0.0') && $long <= ip2long('100.127.255.255'))
                || ($long >= ip2long('198.18.0.0') && $long <= ip2long('198.19.255.255'))) {
                return false;
            }
        }

        return true;
    }
}
