<?php

namespace App\Services\Updates;

use App\Models\UpdateRun;
use ZipArchive;

class FileBackup
{
    public function __construct(private readonly string $root) {}

    /**
     * @param  array<string, string>  $packageFiles  relative path => sha256
     * @param  list<string>  $removedFiles
     * @return array<string, mixed>
     */
    public function create(UpdateRun $run, array $packageFiles, array $removedFiles): array
    {
        $directory = $this->directory($run);
        $this->ensureDirectory($directory);

        $zipPath = $directory.'/files.zip';
        @unlink($zipPath);

        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new UpdateException('Could not create the file backup archive.');
        }

        $existing = [];
        $added = [];
        $removed = [];
        $hashes = [];

        $candidates = array_unique(array_merge(array_keys($packageFiles), $removedFiles));

        try {
            foreach ($candidates as $relative) {
                $relative = UpdatePaths::normalize((string) $relative);

                if (! UpdatePaths::isAllowed($relative)) {
                    throw new UpdateException("The update package contains a protected path: {$relative}");
                }

                $absolute = UpdatePaths::absolute($this->root, $relative);

                if (is_file($absolute)) {
                    if (! is_readable($absolute)) {
                        throw new UpdateException("Cannot read {$relative} to back it up.");
                    }

                    if (! $zip->addFile($absolute, $relative)) {
                        throw new UpdateException("Could not add {$relative} to the file backup.");
                    }

                    $existing[] = $relative;
                    $hashes[$relative] = hash_file('sha256', $absolute);

                    if (in_array($relative, $removedFiles, true)) {
                        $removed[] = $relative;
                    }

                    continue;
                }

                if (array_key_exists($relative, $packageFiles)) {
                    $added[] = $relative;
                }
            }
        } finally {
            $close = $zip->close();
        }

        if (($close ?? false) !== true && ! is_file($zipPath)) {
            throw new UpdateException('The file backup archive could not be written.');
        }

        $meta = [
            'version' => 1,
            'path' => $this->relative($zipPath),
            'from_version' => $run->from_version,
            'to_version' => $run->to_version,
            'created_at' => now()->toIso8601String(),
            'existing' => $existing,
            'added' => $added,
            'removed' => $removed,
            'hashes' => $hashes,
        ];

        file_put_contents($directory.'/backup.json', json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $meta;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public function restore(array $meta): void
    {
        $path = (string) ($meta['path'] ?? '');
        $absoluteZip = $path !== '' && str_starts_with($path, '/')
            ? $path
            : $this->root.'/'.ltrim($path, '/');

        if (! is_file($absoluteZip)) {
            throw new UpdateException('The file backup archive is missing.');
        }

        $zip = new ZipArchive;
        if ($zip->open($absoluteZip) !== true) {
            throw new UpdateException('The file backup archive could not be opened.');
        }

        $hashes = is_array($meta['hashes'] ?? null) ? $meta['hashes'] : [];

        try {
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $relative = UpdatePaths::normalize((string) $zip->getNameIndex($index));

                if (! UpdatePaths::isAllowed($relative)) {
                    throw new UpdateException("The file backup contains a protected path: {$relative}");
                }

                $expected = (string) ($hashes[$relative] ?? '');
                $stream = $zip->getStream($zip->getNameIndex($index));

                if ($stream === false) {
                    throw new UpdateException("Could not read {$relative} from the file backup.");
                }

                $contents = stream_get_contents($stream);
                fclose($stream);

                if ($contents === false || hash('sha256', $contents) !== $expected) {
                    throw new UpdateException("The file backup for {$relative} is corrupt.");
                }

                $this->writeFile($relative, $contents);
            }
        } finally {
            $zip->close();
        }

        foreach ((array) ($meta['added'] ?? []) as $relative) {
            $relative = UpdatePaths::normalize((string) $relative);

            if (! UpdatePaths::isAllowed($relative)) {
                continue;
            }

            $absolute = UpdatePaths::absolute($this->root, $relative);

            if (is_file($absolute)) {
                @unlink($absolute);
                $this->invalidate($absolute);
            }
        }
    }

    public function directory(UpdateRun $run): string
    {
        return $this->root.'/storage/app/updates/backups/'.$run->id;
    }

    private function writeFile(string $relative, string $contents): void
    {
        $absolute = UpdatePaths::absolute($this->root, $relative);
        $directory = dirname($absolute);

        if (! is_dir($directory) && ! @mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new UpdateException("Could not create the directory for {$relative}.");
        }

        // Never write through a symlink planted in the installation.
        if (is_link($absolute)) {
            @unlink($absolute);
        }

        $temporary = $absolute.'.nexus-restore-'.bin2hex(random_bytes(4));

        if (file_put_contents($temporary, $contents) === false) {
            throw new UpdateException("Could not restore {$relative}.");
        }

        @chmod($temporary, 0644);

        if (! @rename($temporary, $absolute)) {
            @unlink($temporary);
            throw new UpdateException("Could not replace {$relative} during rollback.");
        }

        $this->invalidate($absolute);
    }

    private function invalidate(string $path): void
    {
        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate($path, true);
        }
    }

    private function ensureDirectory(string $directory): void
    {
        if (! is_dir($directory) && ! @mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new UpdateException('Could not create the update backup directory.');
        }
    }

    private function relative(string $path): string
    {
        $root = str_replace('\\', '/', rtrim($this->root, '/\\')).'/';
        $path = str_replace('\\', '/', $path);

        return str_starts_with($path, $root) ? substr($path, strlen($root)) : $path;
    }
}
