<?php

namespace App\Services\Import;

use App\Models\WooCommerceMigration;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;
use ZipArchive;

final class WooCommerceArchive
{
    private const MAX_FILES = 50;

    private const MAX_UNCOMPRESSED_BYTES = 150 * 1024 * 1024;

    private const MAX_FILE_BYTES = 50 * 1024 * 1024;

    public function __construct(
        private readonly CsvReader $reader,
    ) {}

    /**
     * Move the upload into private storage, extract CSVs from a ZIP, and
     * classify each file by its header row.
     */
    public function prepare(WooCommerceMigration $migration, UploadedFile $file): void
    {
        $extension = strtolower($file->getClientOriginalExtension());

        if (! in_array($extension, ['zip', 'csv', 'txt'], true)) {
            throw new RuntimeException('Upload a .zip archive or a single .csv file.');
        }

        $directory = storage_path('app/migrations/'.$migration->id.'-'.Str::lower(Str::random(8)));
        $extracted = $directory.'/extracted';
        File::ensureDirectoryExists($extracted);

        $source = $directory.'/source.'.$extension;
        $file->move($directory, 'source.'.$extension);

        $paths = $extension === 'zip'
            ? $this->extractZip($source, $extracted)
            : [$extracted.'/'.$this->safeName($file->getClientOriginalName(), 'source.csv')];

        if ($extension !== 'zip') {
            File::copy($source, $paths[0]);
        }

        $files = [];

        foreach ($paths as $path) {
            if (! is_file($path)) {
                continue;
            }

            try {
                $inspect = $this->reader->inspect($path);
                $rows = $this->reader->count($path, $inspect['encoding'], $inspect['delimiter']);
                $type = $this->classify($inspect['keys']);

                $files[] = [
                    'name' => basename($path),
                    'path' => $path,
                    'type' => $type,
                    'encoding' => $inspect['encoding'],
                    'delimiter' => $inspect['delimiter'],
                    'headers' => $inspect['headers'],
                    'keys' => $inspect['keys'],
                    'rows' => $rows,
                    'size' => filesize($path) ?: 0,
                ];
            } catch (Throwable $exception) {
                $files[] = [
                    'name' => basename($path),
                    'path' => $path,
                    'type' => 'unknown',
                    'error' => $exception->getMessage(),
                    'rows' => 0,
                    'size' => filesize($path) ?: 0,
                ];
            }
        }

        if ($files === []) {
            throw new RuntimeException('No CSV files were found in the upload.');
        }

        if (! collect($files)->contains(fn (array $file): bool => $file['type'] !== 'unknown')) {
            $detail = collect($files)
                ->map(fn (array $file): string => ($file['name'] ?? 'file').' ['.implode(', ', array_slice($file['keys'] ?? [], 0, 8)).']'.(isset($file['error']) ? ' '.$file['error'] : ''))
                ->implode('; ');

            throw new RuntimeException('None of the CSV headers matched WooCommerce products, customers, orders or tax rates. Export the files again with their header row included. Detected: '.$detail);
        }

        $migration->update([
            'disk_path' => $source,
            'extracted_path' => $extracted,
            'status' => 'analyzed',
            'files' => $files,
        ]);
    }

    /**
     * @return list<string>
     */
    private function extractZip(string $source, string $destination): array
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('PHP ZIP support is not enabled on this server. Upload the CSV files individually instead.');
        }

        $zip = new ZipArchive;

        if ($zip->open($source) !== true) {
            throw new RuntimeException('The ZIP archive could not be opened.');
        }

        $paths = [];
        $total = 0;

        try {
            if ($zip->numFiles > self::MAX_FILES) {
                throw new RuntimeException('The ZIP archive contains too many files. Upload at most '.self::MAX_FILES.' CSV files.');
            }

            for ($index = 0; $index < $zip->numFiles; $index++) {
                $stat = $zip->statIndex($index);
                $name = (string) ($stat['name'] ?? '');

                if ($name === '' || str_ends_with($name, '/') || str_starts_with($name, '__MACOSX/')) {
                    continue;
                }

                $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));

                if (! in_array($extension, ['csv', 'txt'], true)) {
                    continue;
                }

                if (str_contains($name, '..') || str_starts_with($name, '/') || str_contains($name, '\\')) {
                    throw new RuntimeException('The ZIP archive contains an unsafe file path.');
                }

                $size = (int) ($stat['size'] ?? 0);

                if ($size > self::MAX_FILE_BYTES) {
                    throw new RuntimeException('A CSV file in the archive is too large. The limit is 50 MB per file.');
                }

                $total += $size;

                if ($total > self::MAX_UNCOMPRESSED_BYTES) {
                    throw new RuntimeException('The extracted CSV files are too large.');
                }

                $stream = $zip->getStream($name);

                if ($stream === false) {
                    throw new RuntimeException('A CSV file in the archive could not be read.');
                }

                $target = $destination.'/'.$index.'-'.$this->safeName(basename($name), 'import-'.$index.'.csv');
                $output = fopen($target, 'wb');

                if ($output === false) {
                    fclose($stream);
                    throw new RuntimeException('A CSV file in the archive could not be extracted.');
                }

                stream_copy_to_stream($stream, $output);
                fclose($stream);
                fclose($output);
                $paths[] = $target;
            }
        } finally {
            $zip->close();
        }

        if ($paths === []) {
            throw new RuntimeException('The ZIP archive does not contain any CSV files.');
        }

        return $paths;
    }

    private function safeName(string $name, string $fallback): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = preg_replace('/[^A-Za-z0-9._-]+/', '-', $name) ?: $fallback;

        return Str::limit($name, 120, '');
    }

    /**
     * @param  list<string>  $keys
     */
    public function classify(array $keys): string
    {
        $has = static fn (string $key): bool => in_array(CsvReader::normalizeHeader($key), $keys, true);

        $product = 0;
        $product += $has('type') ? 3 : 0;
        $product += $has('sku') ? 2 : 0;
        $product += $has('name') ? 2 : 0;
        $product += ($has('regular price') || $has('sale price')) ? 2 : 0;
        $product += $has('categories') ? 1 : 0;
        $product += $has('images') ? 1 : 0;
        $product += $has('parent') ? 1 : 0;
        $product += $has('attribute 1 name') ? 1 : 0;

        $order = 0;
        $order += ($has('order_id') || $has('order_number')) ? 4 : 0;
        $order += ($has('order_date') || $has('date_created')) ? 2 : 0;
        $order += ($has('order_total') || $has('total')) ? 2 : 0;
        $order += $has('status') ? 1 : 0;
        $order += $has('line_items') ? 2 : 0;
        $order += ($has('billing_email') || $has('billing_first_name')) ? 1 : 0;
        $order += $has('shipping_total') ? 1 : 0;

        $customer = 0;
        $customer += ($has('customer_id') || $has('user_id')) ? 3 : 0;
        $customer += ($has('email') || $has('billing_email') || $has('user_email')) ? 3 : 0;
        $customer += ($has('first_name') || $has('last_name') || $has('display_name')) ? 2 : 0;
        $customer += ($has('billing_address_1') || $has('date_created') || $has('phone')) ? 1 : 0;

        $tax = 0;
        $tax += $has('tax name') ? 3 : 0;
        $tax += $has('country code') ? 2 : 0;
        $tax += ($has('rate') || $has('tax rate')) ? 2 : 0;
        $tax += $has('tax class') ? 1 : 0;
        $tax += $has('priority') ? 1 : 0;
        $tax += ($has('compound') || $has('shipping')) ? 1 : 0;

        $scores = ['orders' => $order, 'products' => $product, 'customers' => $customer, 'taxes' => $tax];
        arsort($scores);
        $type = array_key_first($scores);
        $score = (int) reset($scores);

        return $score >= 4 && $type !== null ? $type : 'unknown';
    }
}
