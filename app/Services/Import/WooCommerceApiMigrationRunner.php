<?php

namespace App\Services\Import;

use App\Models\WooCommerceMigration;
use RuntimeException;
use Throwable;

final class WooCommerceApiMigrationRunner
{
    private const PRODUCTS_PER_PAGE = 20;

    private const CUSTOMERS_PER_PAGE = 50;

    private const ORDERS_PER_PAGE = 50;

    public function __construct(
        private readonly WooCommerceApiClient $client,
        private readonly WooCommerceApiNormalizer $normalizer,
        private readonly WooCommerceImporter $importer,
    ) {}

    /**
     * Reset a migration to the beginning and mark it running.
     *
     * @param  array<string, mixed>  $options
     */
    public function start(WooCommerceMigration $migration, string $mode, array $options): void
    {
        $connection = $migration->connection;

        if ($connection === null) {
            throw new RuntimeException('The WooCommerce connection is missing.');
        }

        $totals = $connection->counts();
        $state = [
            'phase' => 'taxes',
            'page' => 1,
            'counts' => [
                'products' => 0,
                'variations' => 0,
                'customers' => 0,
                'orders' => 0,
                'taxes' => 0,
                'pages' => 0,
            ],
            'totals' => [
                'products' => (int) ($totals['products'] ?? 0),
                'customers' => (int) ($totals['customers'] ?? 0),
                'orders' => (int) ($totals['orders'] ?? 0),
                'taxes' => (int) ($totals['taxes'] ?? 0),
            ],
            'last_error' => null,
        ];

        $migration->update([
            'source' => 'api',
            'status' => 'running',
            'mode' => $mode,
            'options' => $options,
            'stats' => $this->importer->initialStats(),
            'remote_state' => $state,
            'progress' => $this->progress($state),
            'error_report_path' => null,
            'started_at' => now(),
            'finished_at' => null,
        ]);
    }

    /**
     * Process one API page. The browser calls this repeatedly so the import
     * stays inside shared-hosting request limits without a queue or daemon.
     *
     * @return array{done: bool, status: string, progress: array<string, mixed>, counts: array<string, int>}
     */
    public function batch(WooCommerceMigration $migration): array
    {
        $connection = $migration->connection;

        if ($connection === null) {
            throw new RuntimeException('The WooCommerce connection is missing.');
        }

        $state = $migration->remote_state ?? [
            'phase' => 'taxes',
            'page' => 1,
            'counts' => ['products' => 0, 'variations' => 0, 'customers' => 0, 'orders' => 0, 'taxes' => 0, 'pages' => 0],
            'totals' => [],
        ];
        $stats = $migration->stats ?? $this->importer->initialStats();
        $options = $migration->options ?? [];
        $dryRun = $migration->mode !== 'apply';
        $phase = (string) ($state['phase'] ?? 'products');

        try {
            if ($phase === 'done') {
                return $this->finish($migration, $state, $stats, $dryRun);
            }

            $records = ['products' => [], 'variations' => [], 'customers' => [], 'orders' => [], 'taxes' => [], 'tax_settings' => []];
            $stateBeforeImport = $state;

            if ($phase === 'taxes') {
                foreach ($this->client->allTaxes($connection) as $rate) {
                    if (is_array($rate)) {
                        $records['taxes'][] = $this->normalizer->taxRate($rate);
                    }
                }

                try {
                    $records['tax_settings'] = $this->normalizer->taxSettings($this->client->taxSettings($connection));
                } catch (Throwable $exception) {
                    $stats['warnings'][] = [
                        'file' => 'WooCommerce API',
                        'message' => 'Tax rates were imported, but WooCommerce tax options could not be read: '.$exception->getMessage(),
                    ];
                }

                try {
                    $extraClasses = [];

                    foreach ($this->client->taxClasses($connection) as $class) {
                        $slug = is_array($class) ? strtolower((string) ($class['slug'] ?? '')) : '';

                        if ($slug !== '' && ! in_array($slug, ['standard', 'standard-rate'], true)) {
                            $extraClasses[] = (string) ($class['name'] ?? $slug);
                        }
                    }

                    if ($extraClasses !== []) {
                        $stats['warnings'][] = [
                            'file' => 'WooCommerce API',
                            'message' => 'Additional tax classes are not applied by ShopKeeprr: '.implode(', ', array_unique($extraClasses)).'. Standard rates were imported; review products that use these classes.',
                        ];
                    }
                } catch (Throwable) {
                    // Tax classes are supplementary. A store can block this
                    // endpoint without preventing standard rate import.
                }

                $state['phase'] = 'products';
                $state['page'] = 1;
            } elseif ($phase === 'products') {
                $page = max(1, (int) ($state['page'] ?? 1));
                $result = $this->client->products($connection, $page, self::PRODUCTS_PER_PAGE);

                foreach ($result['items'] as $product) {
                    if (! is_array($product)) {
                        continue;
                    }

                    $records['products'][] = $this->normalizer->product($product);

                    $variations = (array) ($product['variations'] ?? []);

                    if (($product['type'] ?? '') === 'variable' || $variations !== []) {
                        foreach ($this->client->allVariations($connection, (int) ($product['id'] ?? 0)) as $variation) {
                            $records['variations'][] = $this->normalizer->variation($variation, $product);
                        }
                    }
                }

                if ($result['items'] === [] || $page >= max(1, (int) $result['total_pages'])) {
                    $state['phase'] = 'customers';
                    $state['page'] = 1;
                } else {
                    $state['page'] = $page + 1;
                }
            } elseif ($phase === 'customers') {
                $page = max(1, (int) ($state['page'] ?? 1));
                $result = $this->client->customers($connection, $page, self::CUSTOMERS_PER_PAGE);

                foreach ($result['items'] as $customer) {
                    if (is_array($customer)) {
                        $records['customers'][] = $this->normalizer->customer($customer);
                    }
                }

                if ($result['items'] === [] || $page >= max(1, (int) $result['total_pages'])) {
                    $state['phase'] = 'orders';
                    $state['page'] = 1;
                } else {
                    $state['page'] = $page + 1;
                }
            } elseif ($phase === 'orders') {
                $page = max(1, (int) ($state['page'] ?? 1));
                $result = $this->client->orders($connection, $page, self::ORDERS_PER_PAGE);

                foreach ($result['items'] as $order) {
                    if (is_array($order)) {
                        $records['orders'][] = $this->normalizer->order($order);
                    }
                }

                if ($result['items'] === [] || $page >= max(1, (int) $result['total_pages'])) {
                    $state['phase'] = 'done';
                    $state['page'] = 1;
                } else {
                    $state['page'] = $page + 1;
                }
            }

            $this->importer->importNormalisedBatch($records, $dryRun, $options, $stats);
            $state['counts']['products'] += count($records['products']);
            $state['counts']['variations'] += count($records['variations']);
            $state['counts']['customers'] += count($records['customers']);
            $state['counts']['orders'] += count($records['orders']);
            $state['counts']['taxes'] += count($records['taxes']);
            $state['counts']['pages']++;
            $state['last_error'] = null;

            if ($state['phase'] === 'done') {
                return $this->finish($migration, $state, $stats, $dryRun);
            }

            $migration->update([
                'status' => 'running',
                'stats' => $stats,
                'remote_state' => $state,
                'progress' => $this->progress($state),
            ]);

            return [
                'done' => false,
                'status' => 'running',
                'progress' => $this->progress($state),
                'counts' => $state['counts'],
            ];
        } catch (Throwable $exception) {
            $state = $stateBeforeImport ?? $state;
            $state['last_error'] = $exception->getMessage();
            $stats['errors'][] = [
                'file' => 'WooCommerce API',
                'row' => 0,
                'entity' => 'api',
                'message' => $exception->getMessage(),
                'value' => '',
            ];

            $migration->update([
                'status' => 'failed',
                'stats' => $stats,
                'remote_state' => $state,
                'progress' => $this->progress($state),
                'finished_at' => now(),
            ]);

            throw $exception;
        }
    }

    /**
     * @param  array<string, mixed>  $state
     * @param  array<string, mixed>  $stats
     * @return array{done: bool, status: string, progress: array<string, mixed>, counts: array<string, int>}
     */
    private function finish(WooCommerceMigration $migration, array $state, array $stats, bool $dryRun): array
    {
        $stats = $this->importer->finaliseStats($migration, $stats);

        $migration->update([
            'status' => $dryRun ? 'previewed' : 'completed',
            'stats' => $stats,
            'error_report_path' => $stats['error_report_path'] ?? null,
            'remote_state' => $state,
            'progress' => $this->progress($state),
            'finished_at' => now(),
        ]);

        return [
            'done' => true,
            'status' => (string) $migration->status,
            'progress' => $this->progress($state),
            'counts' => $state['counts'] ?? [],
        ];
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    private function progress(array $state): array
    {
        $counts = (array) ($state['counts'] ?? []);
        $totals = (array) ($state['totals'] ?? []);
        $processed = (int) ($counts['products'] ?? 0) + (int) ($counts['customers'] ?? 0) + (int) ($counts['orders'] ?? 0) + (int) ($counts['taxes'] ?? 0);
        $total = (int) ($totals['products'] ?? 0) + (int) ($totals['customers'] ?? 0) + (int) ($totals['orders'] ?? 0) + (int) ($totals['taxes'] ?? 0);

        return [
            'phase' => (string) ($state['phase'] ?? 'products'),
            'page' => (int) ($state['page'] ?? 1),
            'counts' => [
                'products' => (int) ($counts['products'] ?? 0),
                'variations' => (int) ($counts['variations'] ?? 0),
                'customers' => (int) ($counts['customers'] ?? 0),
                'orders' => (int) ($counts['orders'] ?? 0),
                'taxes' => (int) ($counts['taxes'] ?? 0),
                'pages' => (int) ($counts['pages'] ?? 0),
            ],
            'totals' => [
                'products' => (int) ($totals['products'] ?? 0),
                'customers' => (int) ($totals['customers'] ?? 0),
                'orders' => (int) ($totals['orders'] ?? 0),
                'taxes' => (int) ($totals['taxes'] ?? 0),
            ],
            'percent' => ($state['phase'] ?? '') === 'done'
                ? 100
                : ($total > 0 ? min(99, (int) round(($processed / $total) * 100)) : 0),
        ];
    }
}
