<?php

namespace App\Services;

use App\Enums\ModuleType;
use App\Models\Module;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;
use ZipArchive;

class ModuleManager
{
    public function basePath(): string
    {
        return base_path('modules');
    }

    public function sync(): void
    {
        if (! Schema::hasTable('modules') || ! is_dir($this->basePath())) {
            return;
        }

        foreach (glob($this->basePath().'/*/module.json') ?: [] as $manifestPath) {
            $manifest = $this->readManifest($manifestPath);
            if (! $manifest) {
                continue;
            }

            Module::query()->updateOrCreate(
                ['slug' => $manifest['slug']],
                [
                    'name' => $manifest['name'],
                    'version' => $manifest['version'] ?? '1.0.0',
                    'type' => ModuleType::tryFrom($manifest['type'] ?? 'utility') ?? ModuleType::Utility,
                    'provider' => $manifest['provider'],
                    'path' => 'modules/'.$manifest['slug'],
                    'description' => $manifest['description'] ?? null,
                    'installed_at' => now(),
                    'meta' => $manifest,
                ]
            );
        }
    }

    public function registerEnabledProviders(): void
    {
        if (! Schema::hasTable('modules')) {
            return;
        }

        try {
            $modules = Module::query()->where('is_enabled', true)->get();
        } catch (Throwable) {
            return;
        }

        foreach ($modules as $module) {
            $manifest = $this->readManifest(base_path($module->path.'/module.json'));
            if (! $manifest) {
                continue;
            }

            $namespace = rtrim($manifest['namespace'] ?? '', '\\').'\\';
            $source = $manifest['source'] ?? 'src';

            if ($namespace) {
                spl_autoload_register(function (string $class) use ($namespace, $module, $source) {
                    if (! str_starts_with($class, $namespace)) {
                        return;
                    }

                    $relative = str_replace('\\', '/', substr($class, strlen($namespace)));
                    $file = base_path($module->path.'/'.$source.'/'.$relative.'.php');
                    if (is_file($file)) {
                        require_once $file;
                    }
                });
            }

            $provider = $manifest['provider'] ?? '';
            if ($provider && class_exists($provider)) {
                app()->register($provider);
            }
        }
    }

    public function enabledCount(): int
    {
        try {
            return Module::query()->where('is_enabled', true)->count();
        } catch (Throwable) {
            return 0;
        }
    }

    public function install(UploadedFile $file): Module
    {
        return $this->installArchive($file->getRealPath(), $file->getClientOriginalName());
    }

    /**
     * Install a module from an already-downloaded archive. This is the same
     * path used by the manual uploader, so marketplace packages get the same
     * traversal, size and manifest checks.
     */
    public function installArchive(string $archivePath, string $originalName = 'module.zip'): Module
    {
        if (strtolower(pathinfo($originalName, PATHINFO_EXTENSION)) !== 'zip') {
            throw new RuntimeException('Modules must be uploaded as a .zip archive.');
        }

        if (! is_file($archivePath) || ! is_readable($archivePath)) {
            throw new RuntimeException('The module archive could not be read.');
        }

        if (filesize($archivePath) > 20 * 1024 * 1024) {
            throw new RuntimeException('The module archive is larger than 20 MB.');
        }

        $zip = new ZipArchive;
        $opened = $zip->open($archivePath);

        if ($opened !== true) {
            throw new RuntimeException('The module archive could not be opened.');
        }

        $temp = storage_path('app/module-upload-'.bin2hex(random_bytes(6)));
        @mkdir($temp, 0775, true);
        $totalSize = 0;
        $manifestPath = null;

        try {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);
                $normalized = str_replace('\\', '/', (string) $name);

                if ($normalized === '' || str_starts_with($normalized, '/') || str_contains($normalized, '..')) {
                    throw new RuntimeException('The archive contains an unsafe path.');
                }

                $stat = $zip->statIndex($i);
                $totalSize += (int) ($stat['size'] ?? 0);
                if ($totalSize > 50 * 1024 * 1024) {
                    throw new RuntimeException('The uncompressed module is larger than 50 MB.');
                }

                $target = $temp.'/'.$normalized;
                if (str_ends_with($normalized, '/')) {
                    @mkdir($target, 0775, true);
                    continue;
                }

                if (! is_dir(dirname($target))) {
                    @mkdir(dirname($target), 0775, true);
                }

                $stream = $zip->getStream($name);
                if (! $stream) {
                    throw new RuntimeException('A file in the archive could not be read.');
                }

                file_put_contents($target, stream_get_contents($stream));
                fclose($stream);

                if (basename($normalized) === 'module.json') {
                    $depth = substr_count(trim($normalized, '/'), '/');
                    if ($depth <= 1) {
                        $manifestPath = $target;
                    }
                }
            }
        } finally {
            $zip->close();
        }

        if (! $manifestPath) {
            $this->removeDirectory($temp);
            throw new RuntimeException('module.json was not found in the archive.');
        }

        $manifest = $this->readManifest($manifestPath);
        if (! $manifest) {
            $this->removeDirectory($temp);
            throw new RuntimeException('module.json is missing required fields.');
        }

        if (! preg_match('/^[a-z0-9][a-z0-9-]{1,60}$/', $manifest['slug'])) {
            $this->removeDirectory($temp);
            throw new RuntimeException('The module slug is invalid.');
        }

        $sourceRoot = dirname($manifestPath);
        $destination = $this->basePath().'/'.$manifest['slug'];

        if (is_dir($destination)) {
            $this->removeDirectory($temp);
            throw new RuntimeException('A module with this slug is already installed.');
        }

        if (! is_dir($this->basePath())) {
            @mkdir($this->basePath(), 0775, true);
        }

        if (! @rename($sourceRoot, $destination)) {
            $this->copyDirectory($sourceRoot, $destination);
            $this->removeDirectory($temp);
        }

        $this->runMigrations($destination);

        Module::query()->updateOrCreate(
            ['slug' => $manifest['slug']],
            [
                'name' => $manifest['name'],
                'version' => $manifest['version'] ?? '1.0.0',
                'type' => ModuleType::tryFrom($manifest['type'] ?? 'utility') ?? ModuleType::Utility,
                'provider' => $manifest['provider'],
                'path' => 'modules/'.$manifest['slug'],
                'description' => $manifest['description'] ?? null,
                'is_enabled' => false,
                'installed_at' => now(),
                'meta' => $manifest,
            ]
        );

        return Module::query()->where('slug', $manifest['slug'])->firstOrFail();
    }

    public function delete(Module $module): void
    {
        $path = base_path($module->path);
        $base = realpath($this->basePath());
        $target = realpath($path);

        if ($base && $target && str_starts_with($target, $base.DIRECTORY_SEPARATOR)) {
            $this->removeDirectory($target);
        }

        $module->delete();
    }

    public function toggle(Module $module): void
    {
        $module->update(['is_enabled' => ! $module->is_enabled]);
    }

    public function readManifest(string $path): ?array
    {
        if (! is_file($path)) {
            return null;
        }

        $data = json_decode((string) file_get_contents($path), true);

        if (! is_array($data) || empty($data['name']) || empty($data['slug']) || empty($data['provider'])) {
            return null;
        }

        return $data;
    }

    private function runMigrations(string $directory): void
    {
        $migrations = $directory.'/database/migrations';

        if (! is_dir($migrations)) {
            return;
        }

        // Migrate's --path is resolved from the application base. Passing an
        // absolute path works on some hosts but silently finds no migrations
        // on Windows and a few shared-hosting PHP builds, so keep it relative.
        $path = str_starts_with($migrations, base_path())
            ? ltrim(substr($migrations, strlen(base_path())), '/\\')
            : $migrations;

        $status = Artisan::call('migrate', ['--path' => $path, '--force' => true]);

        if ($status !== 0) {
            throw new RuntimeException('The module migrations could not be applied: '.trim(Artisan::output()));
        }
    }

    private function removeDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }

        @rmdir($directory);
    }

    private function copyDirectory(string $from, string $to): void
    {
        if (! is_dir($to)) {
            @mkdir($to, 0775, true);
        }

        foreach (scandir($from) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            is_dir($from.'/'.$item)
                ? $this->copyDirectory($from.'/'.$item, $to.'/'.$item)
                : copy($from.'/'.$item, $to.'/'.$item);
        }
    }
}
