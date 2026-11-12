<?php

namespace App\Services\Updates;

use Throwable;
use ZipArchive;

class PackageExtractor
{
    /**
     * ShopKeeprr/ is the current archive layout. NexusCart/ is still accepted
     * so a signed package built before the rebrand can be installed.
     */
    private const PREFIXES = ['ShopKeeprr/', 'NexusCart/'];

    /**
     * Extract a signed package into staging and verify every file's hash.
     *
     * @return list<string> relative paths in the package
     */
    public function extract(string $zipPath, Release $release, string $staging): array
    {
        if (! class_exists(ZipArchive::class)) {
            throw new UpdateException('The PHP zip extension is required to install updates.');
        }

        $this->resetDirectory($staging);

        $zip = new ZipArchive;
        if ($zip->open($zipPath) !== true) {
            throw new UpdateException('The downloaded update package is not a valid ZIP archive.');
        }

        $files = [];
        $totalSize = 0;
        $limit = max(1, (int) config('nexuscart.updates.max_package_mb', 150)) * 1024 * 1024 * 3;

        try {
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $raw = str_replace('\\', '/', (string) $zip->getNameIndex($index));
                $stat = $zip->statIndex($index);

                if ($stat === false || $raw === '' || str_contains($raw, "\0")) {
                    throw new UpdateException('The update package contains an unreadable entry.');
                }

                if (str_starts_with($raw, '/') || preg_match('/^[A-Za-z]:/', $raw) || in_array('..', explode('/', $raw), true)) {
                    throw new UpdateException('The update package contains an unsafe path.');
                }

                $prefix = null;

                foreach (self::PREFIXES as $candidate) {
                    if (str_starts_with($raw, $candidate)) {
                        $prefix = $candidate;
                        break;
                    }
                }

                if ($prefix === null) {
                    throw new UpdateException('The update package does not use the ShopKeeprr/ archive layout.');
                }

                $relative = UpdatePaths::normalize(substr($raw, strlen($prefix)));

                if ($relative === '' || str_ends_with($raw, '/')) {
                    continue;
                }

                if (! UpdatePaths::isAllowed($relative)) {
                    throw new UpdateException("The update package tries to write a protected path: {$relative}");
                }

                $attributes = (int) ($stat['external_attributes'] ?? 0);
                if (($attributes >> 16 & 0xF000) === 0xA000) {
                    throw new UpdateException('The update package contains a symbolic link, which is not allowed.');
                }

                if (isset($stat['encryption_method']) && (int) $stat['encryption_method'] !== 0) {
                    throw new UpdateException('Encrypted update packages are not supported.');
                }

                $totalSize += (int) ($stat['size'] ?? 0);
                if ($totalSize > $limit) {
                    throw new UpdateException('The uncompressed update package is larger than the configured limit.');
                }

                if (count($files) >= 20000) {
                    throw new UpdateException('The update package contains too many files.');
                }

                $target = UpdatePaths::absolute($staging, $relative);
                $directory = dirname($target);

                if (! is_dir($directory) && ! @mkdir($directory, 0775, true) && ! is_dir($directory)) {
                    throw new UpdateException('Could not create the update staging directory.');
                }

                $stream = $zip->getStream($zip->getNameIndex($index));
                if ($stream === false) {
                    throw new UpdateException("Could not read {$relative} from the update package.");
                }

                $contents = stream_get_contents($stream);
                fclose($stream);

                if ($contents === false || file_put_contents($target, $contents) === false) {
                    throw new UpdateException("Could not write {$relative} to the staging directory.");
                }

                $files[] = $relative;
            }
        } finally {
            $zip->close();
        }

        $expected = array_keys($release->files);

        $missing = array_values(array_diff($expected, $files));
        if ($missing !== []) {
            throw new UpdateException('The update package is missing: '.implode(', ', array_slice($missing, 0, 5)));
        }

        $unexpected = array_values(array_diff($files, $expected));
        if ($unexpected !== []) {
            throw new UpdateException('The update package contains files not listed in the signed manifest: '.implode(', ', array_slice($unexpected, 0, 5)));
        }

        foreach ($release->files as $relative => $hash) {
            $path = UpdatePaths::absolute($staging, $relative);

            if (! is_file($path) || ! hash_equals($hash, hash_file('sha256', $path))) {
                throw new UpdateException("The update package failed its integrity check for {$relative}.");
            }
        }

        return $files;
    }

    private function resetDirectory(string $directory): void
    {
        if (is_dir($directory)) {
            $this->removeDirectory($directory);
        }

        if (! @mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new UpdateException('Could not create the update staging directory.');
        }
    }

    private function removeDirectory(string $directory): void
    {
        try {
            $items = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );

            foreach ($items as $item) {
                $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
            }

            @rmdir($directory);
        } catch (Throwable) {
            //
        }
    }
}
