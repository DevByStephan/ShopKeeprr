<?php

namespace App\Services\Import;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class WooCommerceImageDownloader
{
    private const MAX_BYTES = 8 * 1024 * 1024;

    /**
     * Download a public image into public/uploads/products.
     *
     * Only http(s) URLs resolving to public addresses are accepted. Redirects
     * are followed manually so every hop is checked against SSRF rules.
     */
    public function download(string $url): string
    {
        $current = trim($url);

        for ($hop = 0; $hop < 4; $hop++) {
            $this->assertSafeUrl($current);

            try {
                $response = Http::withHeaders([
                    'User-Agent' => 'ShopKeeprr WooCommerce migration',
                    'Accept' => 'image/avif,image/webp,image/png,image/jpeg,image/gif',
                ])->timeout(20)->withoutRedirecting()->get($current);
            } catch (Throwable $exception) {
                throw new RuntimeException('Image download failed: '.$exception->getMessage());
            }

            if ($response->redirect()) {
                $location = trim((string) $response->header('Location'));

                if ($location === '') {
                    throw new RuntimeException('Image download redirected without a Location header.');
                }

                $current = $this->resolveUrl($current, $location);

                continue;
            }

            if (! $response->successful()) {
                throw new RuntimeException('Image download returned HTTP '.$response->status().'.');
            }

            $length = (int) ($response->header('Content-Length') ?: 0);

            if ($length > self::MAX_BYTES) {
                throw new RuntimeException('Image is larger than 8 MB.');
            }

            $body = $response->body();

            if (strlen($body) > self::MAX_BYTES) {
                throw new RuntimeException('Image is larger than 8 MB.');
            }

            $info = @getimagesizefromstring($body);

            if (! is_array($info) || ! isset($info['mime'])) {
                throw new RuntimeException('The downloaded file is not a readable image.');
            }

            $extension = match (strtolower((string) $info['mime'])) {
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
                'image/gif' => 'gif',
                default => throw new RuntimeException('Unsupported image type: '.$info['mime']),
            };

            $directory = public_path('uploads/products');
            File::ensureDirectoryExists($directory);

            $base = Str::slug(pathinfo((string) parse_url($current, PHP_URL_PATH), PATHINFO_FILENAME)) ?: 'woocommerce-image';
            $name = Str::limit($base, 80, '').'-'.Str::lower(Str::random(8)).'.'.$extension;

            if (file_put_contents($directory.'/'.$name, $body) === false) {
                throw new RuntimeException('The downloaded image could not be saved.');
            }

            return 'uploads/products/'.$name;
        }

        throw new RuntimeException('Image download followed too many redirects.');
    }

    private function assertSafeUrl(string $url): void
    {
        $parts = parse_url($url);

        if (! is_array($parts) || ! in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)) {
            throw new RuntimeException('Only http and https image URLs are supported.');
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new RuntimeException('Image URLs with embedded credentials are not allowed.');
        }

        $host = strtolower(trim((string) ($parts['host'] ?? '')));

        if ($host === '' || $host === 'localhost' || str_ends_with($host, '.local') || str_ends_with($host, '.internal')) {
            throw new RuntimeException('The image host is not public.');
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
            throw new RuntimeException('The image host could not be resolved.');
        }

        foreach ($addresses as $address) {
            if (! $this->isPublicAddress($address)) {
                throw new RuntimeException('The image host resolves to a private or reserved address.');
            }
        }
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

            // Carrier-grade NAT and the benchmark range are not covered by
            // every PHP build's reserved-range filter.
            if (($long >= ip2long('100.64.0.0') && $long <= ip2long('100.127.255.255'))
                || ($long >= ip2long('198.18.0.0') && $long <= ip2long('198.19.255.255'))) {
                return false;
            }
        }

        return true;
    }

    private function resolveUrl(string $base, string $location): string
    {
        if (preg_match('#^https?://#i', $location) === 1) {
            return $location;
        }

        $parts = parse_url($base);

        if (! is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            throw new RuntimeException('The image redirect URL is invalid.');
        }

        $origin = $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');

        if (str_starts_with($location, '//')) {
            return $parts['scheme'].':'.$location;
        }

        if (str_starts_with($location, '/')) {
            return $origin.$location;
        }

        $directory = rtrim(dirname((string) ($parts['path'] ?? '/')), '/');

        return $origin.$directory.'/'.$location;
    }
}
