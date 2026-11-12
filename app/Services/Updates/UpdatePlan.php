<?php

namespace App\Services\Updates;

final class UpdatePlan
{
    /**
     * @param  list<Release>  $releases
     * @param  list<array{severity: string, module: string|null, message: string}>  $warnings
     */
    public function __construct(
        public readonly string $currentVersion,
        public readonly array $releases,
        public readonly array $warnings,
        public readonly ?string $error,
        public readonly ?string $checkedAt,
        public readonly string $channel,
        public readonly ?string $manifestUrl = null,
    ) {}

    public function upToDate(): bool
    {
        return $this->releases === [] && $this->error === null;
    }

    public function targetVersion(): string
    {
        return $this->releases === []
            ? $this->currentVersion
            : $this->releases[array_key_last($this->releases)]->version;
    }

    public function releaseFor(string $version): ?Release
    {
        foreach ($this->releases as $release) {
            if ($release->version === $version) {
                return $release;
            }
        }

        return null;
    }

    public function hasBlockingWarnings(): bool
    {
        foreach ($this->warnings as $warning) {
            if (($warning['severity'] ?? '') === 'blocking') {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<array{severity: string, module: string|null, message: string}>
     */
    public function blockingWarnings(): array
    {
        return array_values(array_filter(
            $this->warnings,
            static fn (array $warning): bool => ($warning['severity'] ?? '') === 'blocking'
        ));
    }

    public function totalDownloadSize(): int
    {
        return array_sum(array_map(static fn (Release $release): int => $release->size, $this->releases));
    }
}
