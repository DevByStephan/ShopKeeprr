<?php

namespace App\Services\Import;

use App\Enums\FulfillmentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\Refund;
use App\Models\Store;
use App\Models\TaxRate;
use App\Models\User;
use App\Models\WooCommerceMigration;
use App\Services\SettingsService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

final class WooCommerceImporter
{
    public function __construct(
        private readonly CsvReader $reader,
        private readonly WooCommerceImageDownloader $images,
        private readonly SettingsService $settings,
    ) {}

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function run(WooCommerceMigration $migration, string $mode, array $options = []): array
    {
        $dryRun = $mode !== 'apply';
        $update = filter_var($options['update_existing'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $createCategories = filter_var($options['create_categories'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $downloadImages = filter_var($options['download_images'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $continue = filter_var($options['continue_on_errors'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $storeId = Store::default()?->id;

        $stats = $this->emptyStats();
        $productsBySku = [];
        $productsByWooId = [];

        foreach ($this->files($migration, 'taxes') as $file) {
            $this->importTaxFile($file, $dryRun, $update, $storeId, $stats, $continue);
        }

        foreach (['parent', 'variation'] as $pass) {
            foreach ($this->files($migration, 'products') as $file) {
                foreach ($this->reader->rows($file['path'], $file['encoding'], $file['delimiter']) as $line => $row) {
                    $variation = $this->isVariation($row);

                    if (($pass === 'parent' && $variation) || ($pass === 'variation' && ! $variation)) {
                        continue;
                    }

                    $bucket = $variation ? 'variations' : 'products';
                    $stats[$bucket]['total']++;

                    try {
                        if ($variation) {
                            $result = $this->importVariation(
                                $row, $file, $dryRun, $update, $downloadImages, $storeId,
                                $stats, $productsBySku, $productsByWooId,
                            );
                        } else {
                            $result = $this->importProduct(
                                $row, $file, $dryRun, $update, $createCategories, $downloadImages, $storeId,
                                $stats, $productsBySku, $productsByWooId,
                            );
                        }

                        $stats[$bucket][$result]++;
                    } catch (Throwable $exception) {
                        $this->fail($stats, $file, $line, $bucket, $exception, $continue);
                    }
                }
            }
        }

        foreach ($this->files($migration, 'customers') as $file) {
            foreach ($this->reader->rows($file['path'], $file['encoding'], $file['delimiter']) as $line => $row) {
                $stats['customers']['total']++;

                try {
                    $stats['customers'][$this->importCustomer($row, $file, $dryRun, $update, $storeId, $stats)]++;
                } catch (Throwable $exception) {
                    $this->fail($stats, $file, $line, 'customers', $exception, $continue);
                }
            }
        }

        foreach ($this->files($migration, 'orders') as $file) {
            $this->importOrders($file, $dryRun, $update, $storeId, $stats, $continue);
        }

        $stats['error_report_path'] = $this->writeErrorReport($migration, $stats['errors']);
        $stats['errors'] = array_slice($stats['errors'], 0, 100);
        $stats['warnings'] = array_slice($stats['warnings'], 0, 100);

        return $stats;
    }

    /**
     * Start a fresh API batch accumulator.
     *
     * @return array<string, mixed>
     */
    public function initialStats(): array
    {
        return $this->emptyStats();
    }

    /**
     * Import one page of normalised WooCommerce API records.
     *
     * Products and their variations are intentionally supplied in the same
     * batch so dry runs can resolve newly created (unsaved) parents.
     *
     * @param  array{products?: list<array<string, mixed>>, variations?: list<array<string, mixed>>, customers?: list<array<string, mixed>>, orders?: list<array<string, mixed>>, taxes?: list<array<string, mixed>>, tax_settings?: array<string, mixed>}  $records
     * @param  array<string, mixed>  $options
     * @param  array<string, mixed>  $stats
     */
    public function importNormalisedBatch(array $records, bool $dryRun, array $options, array &$stats): void
    {
        $update = filter_var($options['update_existing'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $createCategories = filter_var($options['create_categories'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $downloadImages = filter_var($options['download_images'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $continue = filter_var($options['continue_on_errors'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $storeId = Store::default()?->id;
        $file = ['name' => 'WooCommerce API', 'path' => '', 'encoding' => 'UTF-8', 'delimiter' => ','];
        $productsBySku = [];
        $productsByWooId = [];
        $taxLocations = [];

        foreach ($records['taxes'] ?? [] as $index => $row) {
            if (! is_array($row)) {
                continue;
            }

            $row = $this->normaliseApiRow($row);
            $stats['taxes']['total']++;

            try {
                $this->warnDuplicateTaxLocation($row, $taxLocations, $stats, $file);
                $stats['taxes'][$this->importTaxRate($row, $file, $dryRun, $update, $storeId, $stats)]++;
            } catch (Throwable $exception) {
                $this->fail($stats, $file, (int) $index + 1, 'taxes', $exception, $continue);
            }
        }

        if (($records['tax_settings'] ?? []) !== [] && is_array($records['tax_settings'])) {
            $this->importTaxSettings($records['tax_settings'], $dryRun, $file, $stats);
        }

        foreach (['products', 'variations'] as $bucket) {
            foreach ($records[$bucket] ?? [] as $index => $row) {
                if (! is_array($row)) {
                    continue;
                }

                $row = $this->normaliseApiRow($row);
                $stats[$bucket]['total']++;

                try {
                    $result = $bucket === 'products'
                        ? $this->importProduct($row, $file, $dryRun, $update, $createCategories, $downloadImages, $storeId, $stats, $productsBySku, $productsByWooId)
                        : $this->importVariation($row, $file, $dryRun, $update, $downloadImages, $storeId, $stats, $productsBySku, $productsByWooId);

                    $stats[$bucket][$result]++;
                } catch (Throwable $exception) {
                    $this->fail($stats, $file, (int) $index + 1, $bucket, $exception, $continue);
                }
            }
        }

        foreach ($records['customers'] ?? [] as $index => $row) {
            if (! is_array($row)) {
                continue;
            }

            $row = $this->normaliseApiRow($row);
            $stats['customers']['total']++;

            try {
                $stats['customers'][$this->importCustomer($row, $file, $dryRun, $update, $storeId, $stats)]++;
            } catch (Throwable $exception) {
                $this->fail($stats, $file, (int) $index + 1, 'customers', $exception, $continue);
            }
        }

        foreach ($records['orders'] ?? [] as $index => $row) {
            if (! is_array($row)) {
                continue;
            }

            $row = $this->normaliseApiRow($row);
            $stats['orders']['total']++;

            try {
                $stats['orders'][$this->importOrder($row, [], $file, $dryRun, $update, $storeId, $stats)]++;
            } catch (Throwable $exception) {
                $this->fail($stats, $file, (int) $index + 1, 'orders', $exception, $continue);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $stats
     * @return array<string, mixed>
     */
    public function finaliseStats(WooCommerceMigration $migration, array &$stats): array
    {
        $stats['error_report_path'] = $this->writeErrorReport($migration, $stats['errors']);
        $stats['errors'] = array_slice($stats['errors'], 0, 100);
        $stats['warnings'] = array_slice($stats['warnings'], 0, 100);

        return $stats;
    }

    /**
     * API records arrive with readable keys; private importers expect the same
     * normalised keys produced by CsvReader.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, string>
     */
    private function normaliseApiRow(array $row): array
    {
        $normalised = [];

        foreach ($row as $key => $value) {
            $normalised[CsvReader::normalizeHeader((string) $key)] = is_scalar($value) || $value === null
                ? (string) $value
                : (string) json_encode($value);
        }

        return $normalised;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function files(WooCommerceMigration $migration, string $type): array
    {
        return collect($migration->files ?? [])
            ->where('type', $type)
            ->filter(fn (array $file): bool => is_file((string) ($file['path'] ?? '')))
            ->values()
            ->all();
    }

    /**
     * @param  array{name: string, path: string, encoding: string, delimiter: string}  $file
     * @param  array<string, mixed>  $stats
     */
    private function importTaxFile(array $file, bool $dryRun, bool $update, ?int $storeId, array &$stats, bool $continue): void
    {
        $locations = [];

        foreach ($this->reader->rows($file['path'], $file['encoding'], $file['delimiter']) as $line => $row) {
            $stats['taxes']['total']++;

            try {
                $this->warnDuplicateTaxLocation($row, $locations, $stats, $file);
                $stats['taxes'][$this->importTaxRate($row, $file, $dryRun, $update, $storeId, $stats)]++;
            } catch (Throwable $exception) {
                $this->fail($stats, $file, $line, 'taxes', $exception, $continue);
            }
        }
    }

    /**
     * ShopKeeprr applies one rate per location, so flag source stores that
     * combine state and local rates at different priorities.
     *
     * @param  array<string, string>  $row
     * @param  array<string, bool>  $locations
     * @param  array<string, mixed>  $stats
     * @param  array{name: string}  $file
     */
    private function warnDuplicateTaxLocation(array $row, array &$locations, array &$stats, array $file): void
    {
        $country = strtoupper((string) ($this->value($row, ['country code', 'country']) ?? ''));
        $state = $this->taxState($row);

        if (! preg_match('/^[A-Z]{2}$/', $country)) {
            return;
        }

        $key = $country.'|'.($state ?? '*');

        if (isset($locations[$key])) {
            $this->warn($stats, $file, 'WooCommerce has more than one tax rate for '.$country.($state ? '-'.$state : '').'. ShopKeeprr applies one rate per location; the highest-priority imported rate wins. Review combined state and local taxes after importing.');

            return;
        }

        $locations[$key] = true;
    }

    /**
     * @param  array<string, string>  $row
     * @param  array{name: string}  $file
     * @param  array<string, mixed>  $stats
     */
    private function importTaxRate(array $row, array $file, bool $dryRun, bool $update, ?int $storeId, array &$stats): string
    {
        $country = strtoupper((string) ($this->value($row, ['country code', 'country']) ?? ''));

        if (in_array($country, ['', '*', 'ALL'], true)) {
            $this->warn($stats, $file, 'Skipped a tax rate that applies to every country. Add a country-specific rate in ShopKeeprr instead.');

            return 'skipped';
        }

        if (! preg_match('/^[A-Z]{2}$/', $country)) {
            throw new \RuntimeException('Tax rate country must be a two-letter country code.');
        }

        $class = strtolower((string) ($this->value($row, ['tax class', 'class']) ?? 'standard'));
        $class = $class !== '' ? $class : 'standard';

        if (! in_array($class, ['standard', 'standard rate', 'standard-rate'], true)) {
            $this->warn($stats, $file, 'Skipped a '.$class.' tax rate for '.$country.'. ShopKeeprr does not yet calculate additional WooCommerce tax classes.');

            return 'skipped';
        }

        $postcode = $this->value($row, ['postcode', 'postcodes', 'zip', 'zip code']);
        $city = $this->value($row, ['city', 'cities']);

        if (($postcode !== null && ! in_array(strtoupper($postcode), ['*', 'ALL'], true))
            || ($city !== null && ! in_array(strtoupper($city), ['*', 'ALL'], true))) {
            $this->warn($stats, $file, 'Skipped a postcode or city-restricted tax rate for '.$country.'. ShopKeeprr tax rates apply to the whole country or state, so importing it would overtax other customers.');

            return 'skipped';
        }

        if ($this->boolean($this->value($row, ['compound']))) {
            $this->warn($stats, $file, 'Skipped a compound tax rate for '.$country.'. ShopKeeprr applies simple tax rates and cannot reproduce compound WooCommerce taxes safely.');

            return 'skipped';
        }

        if ($this->value($row, ['shipping']) !== null && ! $this->boolean($this->value($row, ['shipping']))) {
            $this->warn($stats, $file, 'A tax rate for '.$country.' is not applied to shipping in WooCommerce. ShopKeeprr has one global shipping-tax setting, so review shipping tax after importing.');
        }

        $rate = $this->decimal($this->value($row, ['rate', 'tax rate']));

        if ($rate === null || $rate < 0 || $rate > 100) {
            throw new \RuntimeException('Tax rate must be a number between 0 and 100.');
        }

        $state = $this->taxState($row);
        $wooPriority = $this->integer($this->value($row, ['priority'])) ?? 1;
        // WooCommerce applies lower priority numbers first. Invert the value so
        // ShopKeeprr's descending-priority lookup keeps the same ordering.
        $priority = max(0, 1000 - min(1000, $wooPriority));
        $name = Str::limit((string) ($this->value($row, ['tax name', 'name']) ?? 'WooCommerce tax rate'), 120, '');

        $existing = TaxRate::query()
            ->where('store_id', $storeId)
            ->where('country', $country)
            ->where('state', $state)
            ->where('name', $name)
            ->first();

        if ($existing !== null && ! $update) {
            return 'skipped';
        }

        $data = [
            'name' => $name,
            'country' => $country,
            'state' => $state,
            'rate' => $rate,
            'is_active' => true,
            'priority' => $priority,
        ];

        if ($existing === null) {
            $data['store_id'] = $storeId;
        }

        if (! $dryRun) {
            if ($existing !== null) {
                $existing->update($data);
            } else {
                TaxRate::query()->create($data);
            }
        }

        return $existing !== null ? 'updated' : 'created';
    }

    /**
     * Import the WooCommerce tax options that ShopKeeprr can represent. The
     * remaining WooCommerce display, rounding and address-base options are
     * reported instead of being silently dropped.
     *
     * @param  array<string, mixed>  $settings
     * @param  array{name: string}  $file
     * @param  array<string, mixed>  $stats
     */
    private function importTaxSettings(array $settings, bool $dryRun, array $file, array &$stats): void
    {
        $updates = [];
        $prices = strtolower(trim((string) ($settings['woocommerce_prices_include_tax'] ?? '')));

        if (in_array($prices, ['yes', 'true', '1'], true)) {
            $updates['tax.prices_include_tax'] = true;
        } elseif (in_array($prices, ['no', 'false', '0'], true)) {
            $updates['tax.prices_include_tax'] = false;
        }

        $shippingClass = strtolower(trim((string) ($settings['woocommerce_shipping_tax_class'] ?? '')));

        if (in_array($shippingClass, ['zero-rate', 'none', 'no'], true)) {
            $updates['tax.shipping_taxable'] = false;
        } elseif ($shippingClass !== '') {
            $updates['tax.shipping_taxable'] = true;

            if (! in_array($shippingClass, ['inherit', 'standard', 'standard-rate'], true)) {
                $this->warn($stats, $file, 'Shipping uses the '.$shippingClass.' tax class in WooCommerce. ShopKeeprr will use the standard rate for shipping, so review shipping tax after importing.');
            }
        }

        if ($updates !== []) {
            $stats['taxes']['settings'] += count($updates);

            if (! $dryRun) {
                $this->settings->setMany($updates, 'tax');
                $this->settings->flushCache();
            }
        }

        $basedOn = strtolower(trim((string) ($settings['woocommerce_tax_based_on'] ?? '')));

        if (in_array($basedOn, ['billing', 'base'], true)) {
            $this->warn($stats, $file, 'WooCommerce calculates tax from the customer\'s '.$basedOn.' address. ShopKeeprr uses the address supplied at checkout; confirm the store address and tax location behaviour after importing.');
        }

        if (array_intersect_key($settings, array_flip([
            'woocommerce_tax_display_shop',
            'woocommerce_tax_display_cart',
            'woocommerce_tax_total_display',
            'woocommerce_tax_round_at_subtotal',
        ])) !== []) {
            $this->warn($stats, $file, 'WooCommerce tax display and subtotal-rounding options are not imported because ShopKeeprr has different display and rounding rules.');
        }

        if ($updates === []) {
            $this->warn($stats, $file, 'WooCommerce did not return prices-include-tax or shipping-tax settings; tax rates were still imported.');
        }
    }

    /**
     * @param  array<string, string>  $row
     */
    private function taxState(array $row): ?string
    {
        $state = strtoupper(trim((string) ($this->value($row, ['state code', 'state', 'tax state']) ?? '')));

        return $state === '' || in_array($state, ['*', 'ALL'], true) ? null : Str::limit($state, 80, '');
    }

    /**
     * @param  array<string, string>  $row
     * @param  array{name: string, path: string, encoding: string, delimiter: string}  $file
     * @param  array<string, Product>  $productsBySku
     * @param  array<string, Product>  $productsByWooId
     * @param  array<string, mixed>  $stats
     */
    private function importProduct(
        array $row,
        array $file,
        bool $dryRun,
        bool $update,
        bool $createCategories,
        bool $downloadImages,
        ?int $storeId,
        array &$stats,
        array &$productsBySku,
        array &$productsByWooId,
    ): string {
        $wooId = $this->value($row, ['id', 'product id', 'post id']);
        $name = $this->value($row, ['name', 'product name', 'title']);
        $sku = $this->value($row, ['sku']);

        if ($name === null) {
            throw new \RuntimeException('Product name is required.');
        }

        $sku = $sku ?: $this->generatedProductSku($wooId, $name);
        $existing = Product::query()->where('sku', $sku)->first();

        if ($existing !== null && ! $update) {
            return 'skipped';
        }

        $active = $this->published($row);
        $rawType = strtolower((string) ($this->value($row, ['type', 'product type']) ?? ''));

        if (in_array($rawType, ['external', 'grouped'], true)) {
            $this->warn($stats, $file, 'Product "'.$name.'" is a WooCommerce '.$rawType.' product and was imported as a simple product.');
        }

        $type = $this->productType($row, $existing);
        $regular = $this->decimal($this->value($row, ['regular price', 'price']));
        $sale = $this->decimal($this->value($row, ['sale price']));
        $price = $sale ?? $regular ?? 0.0;
        $compare = $sale !== null && $regular !== null && $regular > $sale ? $regular : null;
        $stock = $this->integer($this->value($row, ['stock', 'stock quantity']));
        $inStock = $this->value($row, ['in stock', 'in stock?']);
        $manageStock = $stock !== null || $inStock === '0';

        $data = [
            'name' => $name,
            'slug' => $existing?->slug ?: $this->uniqueSlug($name),
            'sku' => $sku,
            'type' => $type,
            'short_description' => $this->plainText($this->value($row, ['short description', 'short description'])),
            'description' => $this->plainText($this->value($row, ['description'])),
            'price' => $price,
            'compare_at_price' => $compare,
            'manage_stock' => $manageStock,
            'stock_quantity' => $stock ?? 0,
            'low_stock_threshold' => $this->integer($this->value($row, ['low stock amount', 'low stock threshold'])) ?? 5,
            'weight' => $this->decimal($this->value($row, ['weight kg', 'weight (kg)', 'weight'])),
            'is_active' => $active,
            'is_featured' => $this->boolean($this->value($row, ['is featured', 'is featured?', 'featured'])),
            'requires_shipping' => ! $this->boolean($this->value($row, ['virtual', 'downloadable'])),
            'tax_class' => $this->value($row, ['tax class']),
            'published_at' => $active ? ($existing?->published_at ?? now()) : null,
        ];

        if ($existing === null) {
            $data['store_id'] = $storeId;
        }

        $category = $this->categoryFor($row, $dryRun, $createCategories);
        if ($category !== null) {
            $data['category_id'] = $category;
        }

        $product = $dryRun
            ? new Product($data)
            : DB::transaction(function () use ($existing, $data): Product {
                if ($existing !== null) {
                    $existing->update($data);

                    return $existing->refresh();
                }

                return Product::query()->create($data);
            });

        if ($dryRun) {
            $stats['warnings'][] = [
                'file' => $file['name'],
                'message' => 'Dry run: images are not downloaded; remote URLs will be kept on import.',
            ];

            if ($this->imageValue($row) !== null) {
                $stats['warnings'][] = ['file' => $file['name'], 'message' => 'Dry run: images for "'.$name.'" were not imported.'];
            }
        } else {
            $this->syncProductImages($product, $this->imagesFromRow($row), $downloadImages, $stats);
        }

        if ($wooId !== null) {
            $productsByWooId[$wooId] = $product;
        }

        $productsBySku[$sku] = $product;

        return $existing !== null ? 'updated' : 'created';
    }

    /**
     * @param  array<string, string>  $row
     * @param  array{name: string, path: string, encoding: string, delimiter: string}  $file
     * @param  array<string, Product>  $productsBySku
     * @param  array<string, Product>  $productsByWooId
     * @param  array<string, mixed>  $stats
     */
    private function importVariation(
        array $row,
        array $file,
        bool $dryRun,
        bool $update,
        bool $downloadImages,
        ?int $storeId,
        array &$stats,
        array &$productsBySku,
        array &$productsByWooId,
    ): string {
        $parentRef = $this->value($row, ['parent', 'parent sku', 'parent id']);
        $parent = $this->resolveParent($parentRef, $productsBySku, $productsByWooId);

        if ($parent === null) {
            throw new \RuntimeException('Parent product was not found for variation. Import the parent row first.');
        }

        $options = $this->variationOptions($row);
        $name = $this->value($row, ['name', 'product name'])
            ?: (implode(' / ', array_values($options)) ?: 'Variation');
        $sku = $this->value($row, ['sku']) ?: $this->variantSku($parent, $name, $options);
        $existing = ProductVariant::query()->where('sku', $sku)->first();

        if ($existing !== null && (int) $existing->product_id !== (int) $parent->id) {
            throw new \RuntimeException('Variation SKU '.$sku.' already belongs to another product.');
        }

        if ($existing !== null && ! $update) {
            return 'skipped';
        }

        $regular = $this->decimal($this->value($row, ['regular price', 'price']));
        $sale = $this->decimal($this->value($row, ['sale price']));
        $price = $sale ?? $regular ?? $parent->price;
        $compare = $sale !== null && $regular !== null && $regular > $sale ? $regular : null;
        $stock = $this->integer($this->value($row, ['stock', 'stock quantity']));
        $image = $this->imageValue($row);

        $data = [
            'name' => $name,
            'sku' => $sku,
            'price' => $price,
            'compare_at_price' => $compare,
            'stock_quantity' => $stock ?? 0,
            'weight' => $this->decimal($this->value($row, ['weight kg', 'weight (kg)', 'weight'])) ?? $parent->weight,
            'options' => $options,
            'is_active' => $this->published($row),
            'sort_order' => $dryRun ? 0 : ($parent->variants()->count() + 1),
        ];

        if ($image !== null) {
            if ($downloadImages && ! $dryRun) {
                try {
                    $data['image'] = $this->images->download($image);
                } catch (Throwable $exception) {
                    $data['image'] = $image;
                    $stats['warnings'][] = ['file' => $file['name'], 'message' => 'Variation image kept as a remote URL: '.$exception->getMessage()];
                }
            } else {
                $data['image'] = $image;
            }
        }

        $variant = $dryRun
            ? new ProductVariant($data)
            : DB::transaction(function () use ($existing, $parent, $data): ProductVariant {
                if ($existing !== null) {
                    $existing->update($data);

                    return $existing->refresh();
                }

                $variant = $parent->variants()->create($data);
                $parent->update(['type' => 'variable']);

                return $variant;
            });

        if ($image !== null && ! $dryRun && $variant->exists) {
            $this->syncVariantImage($variant, $parent, $image, $stats, $file);
        }

        if ($dryRun && $image !== null) {
            $stats['warnings'][] = ['file' => $file['name'], 'message' => 'Dry run: variation image for "'.$name.'" was not imported.'];
        }

        return $existing !== null ? 'updated' : 'created';
    }

    /**
     * @param  array<string, string>  $row
     * @param  array{name: string, path: string, encoding: string, delimiter: string}  $file
     * @param  array<string, mixed>  $stats
     */
    private function importCustomer(array $row, array $file, bool $dryRun, bool $update, ?int $storeId, array &$stats): string
    {
        $email = strtolower((string) ($this->value($row, ['email', 'billing email', 'user email', 'customer email']) ?? ''));

        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('Customer email is required and must be valid.');
        }

        $existing = User::query()->where('email', $email)->first();

        if ($existing !== null && ($existing->isAdmin() || $existing->role !== 'customer')) {
            return 'skipped';
        }

        if ($existing !== null && ! $update) {
            return 'skipped';
        }

        $name = $this->value($row, ['display name', 'name', 'customer name'])
            ?: trim(($this->value($row, ['first name', 'billing first name']) ?? '').' '.($this->value($row, ['last name', 'billing last name']) ?? ''));
        $name = $name !== '' ? $name : $email;
        $billing = $this->address($row, 'billing');
        $shipping = $this->address($row, 'shipping');
        $created = $this->date($this->value($row, ['date created', 'user registered', 'registered']));

        $data = [
            'name' => $name,
            'email' => $email,
            'role' => 'customer',
            'phone' => $this->value($row, ['phone', 'billing phone']),
            'company_name' => $this->value($row, ['company', 'billing company']),
            'tax_id' => $this->value($row, ['tax id', 'vat']),
            'billing_address' => $billing ?: null,
            'shipping_address' => $shipping ?: null,
        ];

        if ($existing === null) {
            $data['store_id'] = $storeId;
            $data['password'] = Str::random(40);
        }

        if ($dryRun) {
            return $existing !== null ? 'updated' : 'created';
        }

        DB::transaction(function () use ($existing, $data, $created): void {
            if ($existing !== null) {
                $existing->update($data);
                $existing->forceFill([
                    'email_verified_at' => $existing->email_verified_at ?? now(),
                    'updated_at' => now(),
                ])->saveQuietly();

                return;
            }

            $user = User::query()->create($data);
            $user->forceFill([
                'email_verified_at' => now(),
                'created_at' => $created ?? now(),
                'updated_at' => $created ?? now(),
                'admin_notes' => 'Imported from WooCommerce. Send a password reset before the customer signs in.',
            ])->saveQuietly();
        });

        if ($existing === null) {
            $stats['warnings'][] = [
                'file' => $file['name'],
                'message' => 'Customer '.$email.' was created without a usable password; they must use password reset.',
            ];
        }

        return $existing !== null ? 'updated' : 'created';
    }

    /**
     * @param  array{name: string, path: string, encoding: string, delimiter: string}  $file
     * @param  array<string, mixed>  $stats
     */
    private function importOrders(array $file, bool $dryRun, bool $update, ?int $storeId, array &$stats, bool $continue): void
    {
        $groups = [];

        foreach ($this->reader->rows($file['path'], $file['encoding'], $file['delimiter']) as $line => $row) {
            $orderId = $this->value($row, ['order id', 'order number', 'id']);

            if ($orderId === null) {
                $this->fail($stats, $file, $line, 'orders', new \RuntimeException('Order ID is required.'), $continue);

                continue;
            }

            $groups[$orderId] ??= ['row' => $row, 'items' => [], 'line' => $line];

            if ($this->hasItemColumns($row) && ! $this->lineItemsValue($row)) {
                $groups[$orderId]['items'][] = $row;
            }
        }

        $stats['orders']['total'] += count($groups);

        foreach ($groups as $orderId => $group) {
            try {
                $result = $this->importOrder($group['row'], $group['items'], $file, $dryRun, $update, $storeId, $stats);
                $stats['orders'][$result]++;
            } catch (Throwable $exception) {
                $this->fail($stats, $file, (int) $group['line'], 'orders', $exception, $continue);
            }
        }
    }

    /**
     * @param  array<string, string>  $row
     * @param  list<array<string, string>>  $itemRows
     * @param  array{name: string, path: string, encoding: string, delimiter: string}  $file
     * @param  array<string, mixed>  $stats
     */
    private function importOrder(
        array $row,
        array $itemRows,
        array $file,
        bool $dryRun,
        bool $update,
        ?int $storeId,
        array &$stats,
    ): string {
        $wooOrderId = (string) ($this->value($row, ['order id', 'order number', 'id']) ?? '');
        $number = $this->orderNumber($row, $wooOrderId);
        $existing = Order::query()->where('number', $number)->first();

        if ($existing !== null && ! $update) {
            return 'skipped';
        }

        if ($existing !== null && ! str_contains((string) $existing->admin_note, 'WooCommerce')) {
            $this->warn($stats, $file, 'Order number '.$number.' already exists outside this migration; left untouched.');

            return 'skipped';
        }

        $items = $this->lineItems($row, $itemRows);
        $email = strtolower((string) ($this->value($row, ['billing email', 'customer email', 'email']) ?? ''));
        $customer = $email !== ''
            ? User::query()->where('email', $email)->where('role', 'customer')->first()
            : null;

        $billing = $this->address($row, 'billing');
        $shipping = $this->address($row, 'shipping') ?: $billing;
        $placedAt = $this->date($this->value($row, ['order date', 'date created', 'created at'])) ?? now();
        $status = $this->orderStatus($this->value($row, ['status', 'order status']));
        $refundTotal = $this->decimal($this->value($row, ['refund total', 'refunded total', 'total refunded'])) ?? 0.0;
        $grand = $this->decimal($this->value($row, ['order total', 'total', 'grand total'])) ?? $this->sumItems($items, 'total');
        $subtotal = $this->decimal($this->value($row, ['order subtotal', 'subtotal', 'cart subtotal'])) ?? $this->sumItems($items, 'subtotal');
        $discount = $this->decimal($this->value($row, ['discount total', 'cart discount', 'order discount'])) ?? 0.0;
        $shippingTotal = $this->decimal($this->value($row, ['shipping total', 'shipping total tax'])) ?? 0.0;
        $taxTotal = $this->decimal($this->value($row, ['tax total', 'order tax', 'total tax'])) ?? 0.0;
        $paidAt = $this->date($this->value($row, ['date paid', 'paid date', 'date completed', 'completed date']));
        $isPaid = $paidAt !== null || in_array($status, [OrderStatus::Completed, OrderStatus::Processing], true);
        $paymentStatus = $this->paymentStatus($status, $isPaid, $refundTotal, $grand);
        $fulfillment = $status === OrderStatus::Completed ? FulfillmentStatus::Fulfilled : FulfillmentStatus::Unfulfilled;
        $customerName = trim(($this->value($row, ['billing first name']) ?? '').' '.($this->value($row, ['billing last name']) ?? ''));
        $customerName = $customerName ?: ($this->value($row, ['customer name', 'shipping first name']) ?? $email ?: 'Guest');

        $data = [
            'store_id' => $storeId,
            'user_id' => $customer?->id,
            'number' => $number,
            'access_token' => $existing?->access_token ?: Str::random(64),
            'status' => $status,
            'payment_status' => $paymentStatus,
            'fulfillment_status' => $fulfillment,
            'currency' => strtoupper(substr((string) ($this->value($row, ['order currency', 'currency']) ?? 'USD'), 0, 3)),
            'subtotal' => $subtotal,
            'discount_total' => $discount,
            'shipping_total' => $shippingTotal,
            'tax_total' => $taxTotal,
            'grand_total' => $grand,
            'paid_total' => $isPaid ? max(0, $grand - $refundTotal) : 0,
            'refunded_total' => $refundTotal,
            'coupon_code' => $this->value($row, ['coupon code', 'coupons', 'coupon']),
            'customer_email' => $email !== '' ? $email : 'guest-'.$wooOrderId.'@import.invalid',
            'customer_name' => $customerName,
            'customer_phone' => $this->value($row, ['billing phone', 'customer phone', 'phone']),
            'shipping_address' => $shipping ?: [],
            'billing_address' => $billing ?: null,
            'shipping_method' => $this->value($row, ['shipping method', 'shipping method id']),
            'shipping_method_title' => $this->value($row, ['shipping method title']),
            'payment_method' => Str::limit((string) ($this->value($row, ['payment method', 'payment method id']) ?? 'woocommerce'), 40, ''),
            'transaction_id' => $this->value($row, ['transaction id']),
            'customer_note' => $this->value($row, ['customer note']),
            'admin_note' => 'Imported from WooCommerce order #'.($this->value($row, ['order number']) ?: $wooOrderId).'.',
            'mode' => 'retail',
            'is_subscription' => false,
            'placed_at' => $placedAt,
            'paid_at' => $isPaid ? ($paidAt ?? $placedAt) : null,
            'cancelled_at' => $status === OrderStatus::Cancelled ? $placedAt : null,
        ];

        $stats['orders']['items'] += count($items);

        if ($dryRun) {
            return $existing !== null ? 'updated' : 'created';
        }

        DB::transaction(function () use ($existing, $data, $items, $number, $wooOrderId, $placedAt, $status, $isPaid, $paymentStatus, $grand, $refundTotal): void {
            if ($existing !== null) {
                $existing->update($data);
                $existing->items()->delete();
                $order = $existing;
            } else {
                $order = Order::query()->create($data);
            }

            $order->forceFill([
                'created_at' => $placedAt,
                'updated_at' => $placedAt,
            ])->saveQuietly();

            foreach ($items as $item) {
                $this->createOrderItem($order, $item, $placedAt);
            }

            if ($isPaid) {
                Payment::query()->firstOrCreate(
                    ['order_id' => $order->id, 'gateway' => 'woocommerce', 'gateway_reference' => $number],
                    [
                        'amount' => $grand,
                        'currency' => $order->currency,
                        'status' => $paymentStatus === PaymentStatus::Refunded ? 'refunded' : 'paid',
                        'payload' => ['woocommerce_order_id' => $wooOrderId, 'payment_status' => $paymentStatus->value],
                        'paid_at' => $order->paid_at ?? $placedAt,
                    ],
                );
            }

            if ($refundTotal > 0) {
                Refund::query()->firstOrCreate(
                    ['order_id' => $order->id, 'gateway_refund_id' => 'woocommerce-'.$wooOrderId],
                    ['amount' => $refundTotal, 'reason' => 'Imported WooCommerce refund', 'status' => 'completed'],
                );
            }

            $history = OrderStatusHistory::query()->firstOrCreate(
                ['order_id' => $order->id, 'to_status' => $status->value, 'note' => 'Imported from WooCommerce'],
                ['from_status' => null],
            );
            $history->forceFill(['created_at' => $placedAt, 'updated_at' => $placedAt])->saveQuietly();
        });

        return $existing !== null ? 'updated' : 'created';
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function createOrderItem(Order $order, array $item, Carbon $createdAt): void
    {
        $product = null;

        // WooCommerce product/variation IDs are remote IDs and must never be
        // treated as local primary keys. Match by SKU first, then exact name.
        if (! empty($item['sku'])) {
            $product = Product::query()->where('sku', $item['sku'])->first();
        }

        if (! $product && ! empty($item['name'])) {
            $product = Product::query()->where('name', $item['name'])->first();
        }

        $variant = null;
        $variantSku = $item['variation_sku'] ?? $item['sku'] ?? null;

        if (! empty($variantSku)) {
            $variant = ProductVariant::query()->where('sku', $variantSku)->first();
        }

        if ($variant && $product && (int) $variant->product_id !== (int) $product->id) {
            $variant = null;
        }

        if ($variant && ! $product) {
            $product = $variant->product;
        }

        $quantity = max(1, (int) ($item['quantity'] ?? 1));
        $total = (float) ($item['total'] ?? 0);
        $subtotal = (float) ($item['subtotal'] ?? $total);

        $line = OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $product?->id,
            'variant_id' => $variant?->id,
            'vendor_id' => $product?->vendor_id,
            'product_name' => (string) ($item['name'] ?? 'Imported item'),
            'variant_name' => $item['variation'] ?? $variant?->name,
            'sku' => $item['sku'] ?? $variant?->sku,
            'quantity' => $quantity,
            'unit_price' => (float) ($item['unit_price'] ?? ($total / $quantity)),
            'line_subtotal' => $subtotal,
            'discount_amount' => max(0, $subtotal - $total),
            'tax_amount' => (float) ($item['tax'] ?? 0),
            'line_total' => $total,
            'options' => $item['options'] ?? null,
            'is_subscription' => false,
        ]);

        $line->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->saveQuietly();
    }

    /**
     * @param  array<string, mixed>  $stats
     */
    private function fail(array &$stats, array $file, int $line, string $entity, Throwable $exception, bool $continue): void
    {
        $stats[$entity]['failed']++;
        $stats['errors'][] = [
            'file' => $file['name'],
            'row' => $line,
            'entity' => $entity,
            'message' => $exception->getMessage(),
            'value' => '',
        ];

        if (! $continue) {
            throw $exception;
        }
    }

    /**
     * @param  array<string, mixed>  $stats
     */
    private function warn(array &$stats, array $file, string $message): void
    {
        if (count($stats['warnings']) < 200) {
            $stats['warnings'][] = ['file' => $file['name'], 'message' => $message];
        }
    }

    private function emptyStats(): array
    {
        $empty = ['total' => 0, 'created' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0];

        return [
            'products' => $empty,
            'variations' => $empty,
            'customers' => $empty,
            'orders' => $empty + ['items' => 0],
            'taxes' => $empty + ['settings' => 0],
            'warnings' => [],
            'errors' => [],
        ];
    }

    /**
     * @param  array<string, string>  $row
     * @param  list<string>  $aliases
     */
    private function value(array $row, array $aliases): ?string
    {
        foreach ($aliases as $alias) {
            $key = CsvReader::normalizeHeader($alias);

            if (array_key_exists($key, $row) && trim((string) $row[$key]) !== '') {
                return trim((string) $row[$key]);
            }
        }

        return null;
    }

    /**
     * @param  array<string, string>  $row
     */
    private function published(array $row): bool
    {
        $value = strtolower((string) ($this->value($row, ['published', 'status', 'product status']) ?? '1'));

        return ! in_array($value, ['0', '-1', '2', 'draft', 'private', 'pending', 'false', 'no'], true);
    }

    /**
     * @param  array<string, string>  $row
     */
    private function productType(array $row, ?Product $existing): string
    {
        $type = strtolower((string) ($this->value($row, ['type', 'product type']) ?? ''));

        if (in_array($type, ['variable'], true)) {
            return 'variable';
        }

        if ($existing !== null && $existing->type === 'variable') {
            return 'variable';
        }

        return 'simple';
    }

    /**
     * @param  array<string, string>  $row
     */
    private function isVariation(array $row): bool
    {
        return strtolower((string) ($this->value($row, ['type', 'product type']) ?? '')) === 'variation'
            || $this->value($row, ['parent', 'parent sku', 'parent id']) !== null;
    }

    /**
     * @param  array<string, Product>  $productsBySku
     * @param  array<string, Product>  $productsByWooId
     */
    private function resolveParent(?string $reference, array $productsBySku, array $productsByWooId): ?Product
    {
        if ($reference === null) {
            return null;
        }

        $reference = trim($reference);
        $bare = str_starts_with(strtolower($reference), 'id:') ? substr($reference, 3) : $reference;

        if (isset($productsByWooId[$bare])) {
            return $productsByWooId[$bare];
        }

        if (isset($productsBySku[$bare])) {
            return $productsBySku[$bare];
        }

        return Product::query()
            ->where('sku', $bare)
            ->when(ctype_digit($bare), fn ($query) => $query->orWhere('id', (int) $bare))
            ->first();
    }

    /**
     * @param  array<string, string>  $row
     * @return array<string, string>
     */
    private function variationOptions(array $row): array
    {
        $options = [];

        for ($index = 1; $index <= 20; $index++) {
            $name = $this->value($row, ['attribute '.$index.' name']);
            $value = $this->value($row, ['attribute '.$index.' value s', 'attribute '.$index.' value(s)']);

            if ($name !== null && $value !== null) {
                $options[$name] = $value;
            }
        }

        return $options;
    }

    /**
     * @param  array<string, string>  $row
     */
    private function imageValue(array $row): ?string
    {
        $images = $this->imagesFromRow($row);

        return $images[0] ?? null;
    }

    /**
     * @param  array<string, string>  $row
     * @return list<string>
     */
    private function imagesFromRow(array $row): array
    {
        $value = $this->value($row, ['images', 'image']);

        if ($value === null) {
            return [];
        }

        return array_values(array_filter(array_map('trim', preg_split('/\s*,\s*/', $value) ?: [])));
    }

    /**
     * @param  array<string, string>  $row
     */
    private function categoryFor(array $row, bool $dryRun, bool $create): ?int
    {
        $value = $this->value($row, ['categories', 'category']);

        if ($value === null) {
            return null;
        }

        $first = trim(explode(',', $value)[0] ?? '');

        if ($first === '') {
            return null;
        }

        $parent = null;

        foreach (array_map('trim', explode('>', $first)) as $name) {
            if ($name === '') {
                continue;
            }

            $category = Category::query()
                ->where('parent_id', $parent)
                ->where(fn ($query) => $query->where('name', $name)->orWhere('slug', Str::slug($name)))
                ->first();

            if ($category === null && $create && ! $dryRun) {
                $category = Category::query()->create([
                    'name' => $name,
                    'slug' => $this->uniqueCategorySlug($name),
                    'is_active' => true,
                ]);
            }

            if ($category === null) {
                return null;
            }

            $parent = $category->id;
        }

        return $parent;
    }

    /**
     * @param  array<string, mixed>  $stats
     * @param  array<string, mixed>  $file
     */
    private function syncProductImages(Product $product, array $urls, bool $download, array &$stats): void
    {
        $sort = (int) ProductImage::query()->where('product_id', $product->id)->max('sort_order');

        foreach ($urls as $url) {
            $url = trim((string) $url);

            if ($url === '') {
                continue;
            }

            if ($download) {
                try {
                    $url = $this->images->download($url);
                } catch (Throwable $exception) {
                    $stats['warnings'][] = ['file' => 'products', 'message' => 'Image kept as a remote URL: '.$exception->getMessage()];
                }
            }

            ProductImage::query()->firstOrCreate(
                ['product_id' => $product->id, 'path' => $url],
                ['alt' => $product->name, 'sort_order' => ++$sort],
            );
        }
    }

    /**
     * @param  array<string, mixed>  $stats
     * @param  array<string, mixed>  $file
     */
    private function syncVariantImage(ProductVariant $variant, Product $parent, string $url, array &$stats, array $file): void
    {
        $path = $variant->image ?: $url;

        if (str_starts_with($path, 'http')) {
            $stats['warnings'][] = ['file' => $file['name'], 'message' => 'Variation image kept as a remote URL.'];
        }

        ProductImage::query()->firstOrCreate(
            ['product_id' => $parent->id, 'variant_id' => $variant->id, 'path' => $path],
            ['alt' => $variant->name, 'sort_order' => 0],
        );
    }

    /**
     * @param  array<string, string>  $row
     * @return list<array<string, mixed>>
     */
    private function lineItems(array $row, array $itemRows): array
    {
        if ($itemRows !== []) {
            return array_map(fn (array $item): array => $this->normalizeLineItem([
                'product_id' => $this->value($item, ['product id']),
                'variation_id' => $this->value($item, ['variation id']),
                'name' => $this->value($item, ['order item name', 'line item name', 'product name', 'item name', 'name']),
                'quantity' => $this->value($item, ['quantity', 'qty']),
                'subtotal' => $this->value($item, ['line subtotal', 'subtotal']),
                'total' => $this->value($item, ['line total', 'total']),
                'tax' => $this->value($item, ['line tax', 'tax amount', 'tax']),
                'sku' => $this->value($item, ['item sku', 'sku']),
                'variation_sku' => $this->value($item, ['variation sku']),
                'variation' => $this->value($item, ['variation', 'variation name']),
                'options' => $this->value($item, ['item meta', 'options', 'variation attributes']),
            ]), $itemRows);
        }

        $raw = $this->lineItemsValue($row);

        if ($raw === null) {
            return [];
        }

        $decoded = json_decode($raw, true);

        if (is_array($decoded)) {
            if (isset($decoded['line_items']) && is_array($decoded['line_items'])) {
                $decoded = $decoded['line_items'];
            }

            if (! array_is_list($decoded)) {
                $decoded = isset($decoded['name']) || isset($decoded['product_id']) ? [$decoded] : array_values($decoded);
            }

            return array_values(array_map(fn (mixed $item): array => $this->normalizeLineItem(is_array($item) ? $item : []), $decoded));
        }

        // CSV Import Suite uses pipe-delimited item fields. Support one item
        // per cell and the common ; separator for multiple items.
        $items = [];

        foreach (preg_split('/\s*;\s*/', $raw) ?: [] as $chunk) {
            $item = [];

            foreach (preg_split('/\s*\|\s*/', $chunk) ?: [] as $part) {
                if (! str_contains($part, ':') && ! str_contains($part, '=')) {
                    continue;
                }

                [$key, $value] = preg_split('/[:=]/', $part, 2);
                $item[trim((string) $key)] = trim((string) $value);
            }

            if ($item !== []) {
                $items[] = $this->normalizeLineItem($item);
            }
        }

        return $items;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function normalizeLineItem(array $item): array
    {
        $quantity = max(1, (int) ($item['quantity'] ?? $item['qty'] ?? 1));
        $total = $this->decimal(isset($item['total']) ? (string) $item['total'] : null) ?? 0.0;
        $subtotal = $this->decimal(isset($item['subtotal']) ? (string) $item['subtotal'] : null) ?? $total;
        $options = $item['options'] ?? null;

        if (is_string($options)) {
            $decoded = json_decode($options, true);
            $options = is_array($decoded) ? $decoded : null;
        }

        return [
            'product_id' => $item['product_id'] ?? null,
            'variation_id' => $item['variation_id'] ?? null,
            'name' => $item['name'] ?? $item['product_name'] ?? 'Imported item',
            'quantity' => $quantity,
            'subtotal' => $subtotal,
            'total' => $total,
            'tax' => $this->decimal(isset($item['tax']) ? (string) $item['tax'] : null) ?? 0.0,
            'sku' => $item['sku'] ?? null,
            'variation_sku' => $item['variation_sku'] ?? null,
            'variation' => $item['variation'] ?? $item['variation_name'] ?? null,
            'options' => is_array($options) ? $options : null,
        ];
    }

    /**
     * @param  array<string, string>  $row
     */
    private function lineItemsValue(array $row): ?string
    {
        return $this->value($row, ['line items', 'order items', 'items', 'line items json']);
    }

    /**
     * @param  array<string, string>  $row
     */
    private function hasItemColumns(array $row): bool
    {
        foreach (['product name', 'item name', 'order item name', 'line item name', 'product id', 'variation id'] as $column) {
            if (array_key_exists(CsvReader::normalizeHeader($column), $row)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, string>  $row
     */
    private function address(array $row, string $prefix): array
    {
        $aliases = static function (string $field) use ($prefix): array {
            $keys = [$prefix.' '.$field, $prefix.'_'.$field];

            // Several customer-export plugins use unprefixed address fields.
            // Only fall back for billing so a partial shipping address never
            // inherits the billing address by accident.
            if ($prefix === 'billing') {
                $keys[] = $field;
            }

            return $keys;
        };

        $name = trim(($this->value($row, $aliases('first name')) ?? '').' '.($this->value($row, $aliases('last name')) ?? ''));
        $address = [
            'name' => $name,
            'company' => $this->value($row, $aliases('company')),
            'line1' => $this->value($row, array_merge($aliases('address 1'), $aliases('address'))),
            'line2' => $this->value($row, $aliases('address 2')),
            'city' => $this->value($row, $aliases('city')),
            'state' => $this->value($row, array_merge($aliases('state'), $aliases('state code'))),
            'postcode' => $this->value($row, array_merge($aliases('postcode'), $aliases('zip code'))),
            'country' => $this->value($row, $aliases('country')),
            'phone' => $this->value($row, $aliases('phone')),
        ];

        return array_filter($address, static fn (mixed $value): bool => $value !== null && $value !== '');
    }

    private function orderStatus(?string $status): OrderStatus
    {
        $status = strtolower(str_replace(['wc-', ' '], ['', '_'], (string) $status));

        return match ($status) {
            'processing' => OrderStatus::Processing,
            'on_hold', 'onhold' => OrderStatus::OnHold,
            'completed' => OrderStatus::Completed,
            'cancelled', 'canceled' => OrderStatus::Cancelled,
            'refunded' => OrderStatus::Refunded,
            'failed' => OrderStatus::Failed,
            default => OrderStatus::Pending,
        };
    }

    private function paymentStatus(OrderStatus $status, bool $isPaid, float $refunded, float $grand): PaymentStatus
    {
        if ($refunded > 0) {
            return $refunded >= $grand && $grand > 0 ? PaymentStatus::Refunded : PaymentStatus::PartiallyRefunded;
        }

        if ($status === OrderStatus::Refunded) {
            return PaymentStatus::Refunded;
        }

        if ($status === OrderStatus::Failed) {
            return PaymentStatus::Failed;
        }

        return $isPaid ? PaymentStatus::Paid : PaymentStatus::Pending;
    }

    /**
     * @param  array<string, string>  $row
     */
    private function orderNumber(array $row, string $wooOrderId): string
    {
        $source = $this->value($row, ['order number']) ?: $wooOrderId;
        $source = preg_replace('/[^A-Za-z0-9_-]/', '', $source) ?: Str::random(8);

        return Str::limit('WC-'.$source, 40, '');
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    private function sumItems(array $items, string $key): float
    {
        return round(array_sum(array_map(static fn (array $item): float => (float) ($item[$key] ?? 0), $items)), 2);
    }

    private function generatedProductSku(?string $wooId, string $name): string
    {
        $base = $wooId !== null && $wooId !== ''
            ? 'WOO-'.preg_replace('/[^A-Za-z0-9_-]/', '', $wooId)
            : 'WOO-'.strtoupper(substr(sha1($name), 0, 10));

        $sku = $base;
        $index = 2;

        while (Product::query()->where('sku', $sku)->exists()) {
            $sku = $base.'-'.$index++;
        }

        return $sku;
    }

    /**
     * @param  array<string, string>  $options
     */
    private function variantSku(Product $parent, string $name, array $options): string
    {
        $base = strtoupper(Str::slug(($parent->sku ?: 'WOO').'-'.($options ? implode('-', $options) : $name), '-'));
        $base = $base !== '' ? $base : 'WOO-VARIATION';
        $sku = $base;
        $index = 2;

        while (ProductVariant::query()->where('sku', $sku)->exists()) {
            $sku = $base.'-'.$index++;
        }

        return $sku;
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'product';
        $slug = $base;
        $index = 2;

        while (Product::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$index++;
        }

        return $slug;
    }

    private function uniqueCategorySlug(string $name): string
    {
        $base = Str::slug($name) ?: 'category';
        $slug = $base;
        $index = 2;

        while (Category::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$index++;
        }

        return $slug;
    }

    private function plainText(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $value = str_replace(['<br>', '<br/>', '<br />', '</p>', '</li>'], ["\n", "\n", "\n", "\n\n", "\n"], $value);
        $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace("/\n{3,}/", "\n\n", $value) ?? '') ?: null;
    }

    private function decimal(?string $value): ?float
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $value = str_replace([' ', ','], ['', ''], $value);
        $value = preg_replace('/[^0-9.\-]/', '', $value) ?? '';

        return is_numeric($value) ? (float) $value : null;
    }

    private function integer(?string $value): ?int
    {
        $decimal = $this->decimal($value);

        return $decimal === null ? null : max(0, (int) $decimal);
    }

    private function boolean(?string $value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }

        return in_array(strtolower($value), ['1', 'true', 'yes', 'y', 'active', 'enabled'], true);
    }

    private function date(?string $value): ?Carbon
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param  list<array<string, mixed>>  $errors
     */
    private function writeErrorReport(WooCommerceMigration $migration, array $errors): ?string
    {
        if ($errors === []) {
            return null;
        }

        $directory = storage_path('app/migrations/'.$migration->id);
        @mkdir($directory, 0775, true);
        $path = $directory.'/errors-'.now()->format('YmdHis').'.csv';
        $handle = @fopen($path, 'wb');

        if ($handle === false) {
            return null;
        }

        fputcsv($handle, ['file', 'row', 'entity', 'message', 'original_value'], ',', '"', '');

        foreach ($errors as $error) {
            fputcsv($handle, [
                $this->neutralize((string) ($error['file'] ?? '')),
                $error['row'] ?? '',
                $error['entity'] ?? '',
                $this->neutralize((string) ($error['message'] ?? '')),
                $this->neutralize((string) ($error['value'] ?? '')),
            ], ',', '"', '');
        }

        fclose($handle);

        return $path;
    }

    private function neutralize(string $value): string
    {
        return preg_match('/^[=+\-@]/', $value) === 1 ? "'".$value : $value;
    }
}
