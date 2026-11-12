<?php

namespace App\Services\Updates;

use App\Support\Version;
use InvalidArgumentException;

final class Release
{
    /**
     * @param  array<string, string>  $files
     * @param  list<string>  $removedFiles
     * @param  list<string>  $highlights
     * @param  array<string, string>  $requires
     */
    public function __construct(
        public readonly string $version,
        public readonly string $releasedAt,
        public readonly string $downloadUrl,
        public readonly string $sha256,
        public readonly int $size,
        public readonly string $signature,
        public readonly string $signatureAlgorithm,
        public readonly string $notes,
        public readonly array $highlights,
        public readonly array $files,
        public readonly array $removedFiles,
        public readonly array $requires,
        public readonly string $moduleApi,
        public readonly string $minPhp,
        public readonly ?string $channel = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $version = trim((string) ($data['version'] ?? ''));
        $sha256 = strtolower(trim((string) ($data['sha256'] ?? '')));
        $downloadUrl = trim((string) ($data['download_url'] ?? ''));
        $signature = trim((string) ($data['signature'] ?? ''));
        $size = (int) ($data['size'] ?? 0);

        if ($version === '') {
            throw new InvalidArgumentException('A release in the manifest is missing its version.');
        }

        if ($downloadUrl === '' || filter_var($downloadUrl, FILTER_VALIDATE_URL) === false) {
            throw new InvalidArgumentException("Release {$version} has an invalid download URL.");
        }

        if (! preg_match('/^[a-f0-9]{64}$/', $sha256)) {
            throw new InvalidArgumentException("Release {$version} has an invalid SHA-256 hash.");
        }

        if ($size <= 0) {
            throw new InvalidArgumentException("Release {$version} has an invalid package size.");
        }

        if ($signature === '' || base64_decode($signature, true) === false) {
            throw new InvalidArgumentException("Release {$version} is not signed correctly.");
        }

        $files = $data['files'] ?? null;

        if (! is_array($files) || $files === []) {
            throw new InvalidArgumentException("Release {$version} does not list its files.");
        }

        $normalizedFiles = [];
        foreach ($files as $path => $hash) {
            if (! is_string($path) || $path === '' || ! is_string($hash) || ! preg_match('/^[a-f0-9]{64}$/i', $hash)) {
                throw new InvalidArgumentException("Release {$version} contains an invalid file manifest.");
            }

            $normalizedFiles[$path] = strtolower($hash);
        }

        $removed = $data['removed_files'] ?? [];
        if (! is_array($removed)) {
            throw new InvalidArgumentException("Release {$version} contains an invalid removal list.");
        }

        $removed = array_values(array_filter(array_map(
            static fn (mixed $path): string => is_string($path) ? trim($path) : '',
            $removed
        )));

        $requires = $data['requires'] ?? [];
        if (! is_array($requires)) {
            $requires = [];
        }

        $highlights = $data['highlights'] ?? [];
        if (! is_array($highlights)) {
            $highlights = [];
        }

        return new self(
            version: $version,
            releasedAt: (string) ($data['released_at'] ?? ''),
            downloadUrl: $downloadUrl,
            sha256: $sha256,
            size: $size,
            signature: $signature,
            signatureAlgorithm: (string) ($data['signature_algorithm'] ?? 'rsa-sha256'),
            notes: (string) ($data['notes'] ?? ''),
            highlights: array_values(array_filter(array_map(
                static fn (mixed $line): string => is_string($line) ? trim($line) : '',
                $highlights
            ))),
            files: $normalizedFiles,
            removedFiles: $removed,
            requires: array_map(static fn (mixed $value): string => (string) $value, $requires),
            moduleApi: (string) ($data['module_api'] ?? '1.0'),
            minPhp: (string) ($data['min_php'] ?? '8.3.0'),
            channel: isset($data['channel']) ? (string) $data['channel'] : null,
        );
    }

    public function signaturePayload(): string
    {
        return Version::signaturePayload($this->version, $this->sha256, $this->size);
    }

    public function humanSize(): string
    {
        return $this->size >= 1048576
            ? number_format($this->size / 1048576, 1).' MB'
            : number_format(max(1, (int) ceil($this->size / 1024))).' KB';
    }

    public function changedFileCount(): int
    {
        return count($this->files);
    }
}
