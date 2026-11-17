<?php

namespace App\Services\Import;

use Generator;
use RuntimeException;

/**
 * Small streaming CSV reader shared by migration imports.
 *
 * WooCommerce exports are commonly UTF-8 with a BOM, but older stores still
 * export Windows-1252 and semicolon-separated files. Reading stays streaming:
 * a 100 MB order export is never loaded into memory in one piece.
 */
final class CsvReader
{
    /**
     * @return array{encoding: string, delimiter: string, headers: list<string>, keys: list<string>}
     */
    public function inspect(string $path): array
    {
        $sample = (string) file_get_contents($path, false, null, 0, 65536);
        $encoding = str_starts_with($sample, "\xEF\xBB\xBF")
            ? 'UTF-8'
            : (mb_check_encoding($sample, 'UTF-8') ? 'UTF-8' : 'Windows-1252');

        $firstLine = strtok($sample, "\r\n") ?: '';
        $delimiter = ',';
        $best = -1;

        foreach ([',', ';', "\t"] as $candidate) {
            $cells = str_getcsv($firstLine, $candidate, '"', '');
            $count = count($cells);

            if ($count > $best) {
                $best = $count;
                $delimiter = $candidate;
            }
        }

        $handle = $this->open($path);

        try {
            $raw = fgetcsv($handle, 0, $delimiter, '"', '') ?: [];
        } finally {
            fclose($handle);
        }

        $headers = [];
        $keys = [];

        foreach ($raw as $index => $value) {
            $header = ltrim(trim($this->decode((string) $value, $encoding)), "\xEF\xBB\xBF");
            $header = $header !== '' ? $header : 'column_'.($index + 1);
            $headers[] = $header;
            $keys[] = $this->uniqueKey(self::normalizeHeader($header), $keys, $index);
        }

        return [
            'encoding' => $encoding,
            'delimiter' => $delimiter,
            'headers' => $headers,
            'keys' => $keys,
        ];
    }

    /**
     * @return array{headers: list<string>, rows: list<list<string>>}
     */
    public function preview(string $path, string $encoding, string $delimiter, int $limit = 8): array
    {
        $handle = $this->open($path);
        $headers = [];
        $rows = [];

        try {
            $rawHeaders = fgetcsv($handle, 0, $delimiter, '"', '');

            foreach ($rawHeaders ?: [] as $index => $value) {
                $header = ltrim(trim($this->decode((string) $value, $encoding)), "\xEF\xBB\xBF");
                $headers[] = $header !== '' ? $header : 'column_'.($index + 1);
            }

            while (count($rows) < $limit && ($cells = fgetcsv($handle, 0, $delimiter, '"', '')) !== false) {
                if ($this->isBlank($cells)) {
                    continue;
                }

                $rows[] = array_map(
                    fn (mixed $cell): string => $this->decode((string) $cell, $encoding),
                    $cells,
                );
            }
        } finally {
            fclose($handle);
        }

        return ['headers' => $headers, 'rows' => $rows];
    }

    /**
     * @return Generator<int, array<string, string>>
     */
    public function rows(string $path, string $encoding, string $delimiter): Generator
    {
        $handle = $this->open($path);
        $keys = [];
        $line = 1;

        try {
            $rawHeaders = fgetcsv($handle, 0, $delimiter, '"', '');

            foreach ($rawHeaders ?: [] as $index => $value) {
                $header = ltrim(trim($this->decode((string) $value, $encoding)), "\xEF\xBB\xBF");
                $header = $header !== '' ? $header : 'column_'.($index + 1);
                $keys[] = $this->uniqueKey(self::normalizeHeader($header), $keys, $index);
            }

            while (($cells = fgetcsv($handle, 0, $delimiter, '"', '')) !== false) {
                $line++;

                if ($this->isBlank($cells)) {
                    continue;
                }

                $row = [];

                foreach ($keys as $index => $key) {
                    $row[$key] = isset($cells[$index])
                        ? trim($this->decode((string) $cells[$index], $encoding))
                        : '';
                }

                yield $line => $row;
            }
        } finally {
            fclose($handle);
        }
    }

    public function count(string $path, string $encoding, string $delimiter): int
    {
        $count = 0;

        foreach ($this->rows($path, $encoding, $delimiter) as $row) {
            $count++;
        }

        return $count;
    }

    /**
     * Normalised keys make header matching tolerant of casing, spaces and
     * punctuation differences between WooCommerce export versions.
     */
    public static function normalizeHeader(string $header): string
    {
        $header = ltrim(trim($header), "\xEF\xBB\xBF");
        $header = strtolower($header);
        $header = preg_replace('/[^a-z0-9]+/', ' ', $header) ?? '';

        return trim(preg_replace('/\s+/', ' ', $header) ?? '');
    }

    /**
     * @param  list<string>  $keys
     */
    private function uniqueKey(string $key, array $keys, int $index): string
    {
        $key = $key !== '' ? $key : 'column '.($index + 1);
        $candidate = $key;
        $suffix = 2;

        while (in_array($candidate, $keys, true)) {
            $candidate = $key.' '.$suffix++;
        }

        return $candidate;
    }

    private function decode(string $value, string $encoding): string
    {
        if ($encoding === '' || $encoding === 'UTF-8') {
            return $value;
        }

        return (string) mb_convert_encoding($value, 'UTF-8', $encoding);
    }

    /**
     * @param  list<string|null>  $cells
     */
    private function isBlank(array $cells): bool
    {
        foreach ($cells as $cell) {
            if (trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @return resource
     */
    private function open(string $path)
    {
        $handle = @fopen($path, 'rb');

        if ($handle === false) {
            throw new RuntimeException('The CSV file could not be opened: '.basename($path));
        }

        return $handle;
    }
}
