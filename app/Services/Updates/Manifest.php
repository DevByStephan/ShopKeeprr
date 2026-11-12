<?php

namespace App\Services\Updates;

use InvalidArgumentException;

final class Manifest
{
    /**
     * @param  list<Release>  $releases
     */
    public function __construct(
        public readonly int $schema,
        public readonly string $channel,
        public readonly string $generatedAt,
        public readonly array $releases,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data, string $expectedChannel): self
    {
        $schema = (int) ($data['schema'] ?? 0);

        if ($schema !== 1) {
            throw new InvalidArgumentException('The update manifest uses an unsupported schema.');
        }

        $channel = (string) ($data['channel'] ?? $expectedChannel);
        $rawReleases = $data['releases'] ?? null;

        if (! is_array($rawReleases)) {
            throw new InvalidArgumentException('The update manifest does not contain a release list.');
        }

        $releases = [];

        foreach ($rawReleases as $raw) {
            if (! is_array($raw)) {
                throw new InvalidArgumentException('The update manifest contains an invalid release.');
            }

            $release = Release::fromArray($raw);

            if ($release->channel !== null && $release->channel !== $expectedChannel) {
                continue;
            }

            $releases[] = $release;
        }

        return new self(
            schema: $schema,
            channel: $channel,
            generatedAt: (string) ($data['generated_at'] ?? ''),
            releases: $releases,
        );
    }

    /**
     * @return list<Release>
     */
    public function sortedReleases(): array
    {
        $releases = $this->releases;

        usort($releases, static fn (Release $left, Release $right): int => version_compare($left->version, $right->version));

        return $releases;
    }
}
