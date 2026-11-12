<?php

namespace App\Services\Updates;

use App\Models\AuditLog;
use App\Models\UpdateRun;
use App\Models\User;
use App\Support\Version;
use App\Support\VersionConstraint;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Process\Process;
use Throwable;

class UpdateManager
{
    /** @var resource|null */
    private $lockHandle = null;

    public function __construct(
        private readonly ManifestClient $client,
        private readonly ModuleCompatibility $compatibility,
        private readonly PackageExtractor $extractor,
    ) {}

    public function root(): string
    {
        $configured = config('nexuscart.updates.root');

        return $configured
            ? rtrim((string) $configured, '/\\')
            : base_path();
    }

    public function channel(): string
    {
        return $this->client->channel();
    }

    public function manifestUrl(): string
    {
        return $this->client->manifestUrl();
    }

    public function lastCheckedAt(): ?string
    {
        return $this->client->lastCheckedAt();
    }

    public function check(bool $force = false, ?string $targetVersion = null): UpdatePlan
    {
        $manifest = $this->client->fetch($force);

        return $this->buildPlan($manifest, $targetVersion);
    }

    public function cachedCheck(?string $targetVersion = null): ?UpdatePlan
    {
        $manifest = $this->client->cached();

        return $manifest === null ? null : $this->buildPlan($manifest, $targetVersion);
    }

    /**
     * @return list<UpdateRun>
     */
    public function apply(UpdatePlan $plan, ?User $actor = null): array
    {
        if ($plan->error !== null) {
            throw new UpdateException($plan->error);
        }

        if ($plan->upToDate()) {
            throw new UpdateException('This store is already running the latest version.');
        }

        $runs = [];

        foreach ($plan->releases as $release) {
            if (! Version::isNewer($release->version, Version::current())) {
                continue;
            }

            $runs[] = $this->applyRelease($release, $actor);
        }

        if ($runs === []) {
            throw new UpdateException('No newer release in this plan needs to be installed.');
        }

        return $runs;
    }

    public function applyRelease(Release $release, ?User $actor = null): UpdateRun
    {
        $this->assertCanApply($release);
        $this->acquireLock();

        try {
            $run = UpdateRun::query()->create([
                'from_version' => Version::current(),
                'to_version' => $release->version,
                'status' => 'pending',
                'step' => 'environment',
                'channel' => $this->channel(),
                'user_id' => $actor?->id,
                'signature_verified' => false,
                'notes' => $release->notes,
                'started_at' => now(),
            ]);
        } catch (Throwable $exception) {
            $this->releaseLock();

            throw $exception;
        }

        $maintenance = false;
        $backupReady = false;
        $packagePath = null;
        $staging = $this->updatesDirectory().'/staging/'.$run->id;

        try {
            $run->update(['status' => 'verifying', 'step' => 'signature']);

            if (! ReleaseSigner::verify($release)) {
                throw new UpdateException('The release signature is not valid. The update was not downloaded.');
            }

            $run->update(['signature_verified' => true]);

            $packagePath = $this->download($release, $run);

            $run->update(['step' => 'package']);
            $this->extractor->extract($packagePath, $release, $staging);

            $run->update(['status' => 'backing_up', 'step' => 'maintenance']);
            $this->maintenanceOn();
            $maintenance = true;

            $run->update(['step' => 'database']);
            $database = (new DatabaseBackup($this->root()))->dump($run);
            $run->update([
                'database_backup_path' => $database['path'],
                'database_backup_format' => $database['format'],
                'step' => 'files',
            ]);

            $files = (new FileBackup($this->root()))->create($run, $release->files, $release->removedFiles);
            $backupReady = true;

            $run->update([
                'backup_path' => $files['path'],
                'backup_meta' => $files,
                'files_changed' => count($release->files),
                'step' => 'backup',
            ]);

            $run->update(['status' => 'applying', 'step' => 'files']);
            $this->applyFiles($release, $staging);

            $run->update(['status' => 'migrating', 'step' => 'migrations']);
            $this->runMigrations($run);

            $run->update(['status' => 'verifying', 'step' => 'verification']);
            $this->verifyInstallation($release);

            $this->clearCachesSafely();
            $this->writeInstalledVersion($release->version);
            $this->refreshRuntimeConfig();

            $run->update([
                'status' => 'completed',
                'step' => null,
                'finished_at' => now(),
                'error' => null,
            ]);

            try {
                $this->maintenanceOff();
                $maintenance = false;
            } catch (Throwable $exception) {
                $run->update([
                    'error' => 'The update was installed, but maintenance mode could not be cleared. Run "php artisan up". '.$exception->getMessage(),
                ]);
            }

            $this->audit('update.completed', $run, $actor);
            $this->prune();
        } catch (Throwable $exception) {
            $message = trim($exception->getMessage()) ?: 'The update failed.';
            $run->update(['error' => $message, 'step' => 'failed']);

            if ($backupReady) {
                $run->update(['status' => 'rolling_back', 'step' => 'rollback']);

                try {
                    $this->restoreBackup($run);
                    $this->refreshRuntimeConfig();

                    if ($maintenance) {
                        $this->maintenanceOff();
                        $maintenance = false;
                    }

                    $run->update([
                        'status' => 'rolled_back',
                        'rolled_back_at' => now(),
                        'finished_at' => now(),
                    ]);

                    $this->audit('update.rolled_back', $run, $actor);
                } catch (Throwable $rollbackError) {
                    $run->update([
                        'status' => 'rollback_failed',
                        'error' => $message."\n\nRollback also failed: ".$rollbackError->getMessage()
                            ."\n\nThe storefront remains in maintenance mode. Restore the backup in "
                            .$run->backup_path.' and the database backup in '.$run->database_backup_path.' manually, then run "php artisan up".',
                        'finished_at' => now(),
                    ]);

                    $this->audit('update.rollback_failed', $run, $actor);
                }
            } else {
                if ($maintenance) {
                    try {
                        $this->maintenanceOff();
                        $maintenance = false;
                    } catch (Throwable) {
                        //
                    }
                }

                $run->update(['status' => 'failed', 'finished_at' => now()]);
                $this->audit('update.failed', $run, $actor);
            }

            throw new UpdateException($run->refresh()->error ?? $message, $run);
        } finally {
            if (is_dir($staging)) {
                $this->removeDirectory($staging);
            }

            if ($packagePath !== null && is_file($packagePath)) {
                @unlink($packagePath);
            }

            $this->releaseLock();
        }

        return $run->refresh();
    }

    public function rollback(UpdateRun $run, ?User $actor = null): UpdateRun
    {
        if (! $run->isRollbackable()) {
            throw new UpdateException('This update does not have a complete backup and cannot be rolled back.');
        }

        $this->acquireLock();
        $wasDown = app()->isDownForMaintenance();

        try {
            if (! $wasDown) {
                $this->maintenanceOn();
            }

            $run->update([
                'status' => 'rolling_back',
                'step' => 'files',
                'error' => null,
                'finished_at' => null,
            ]);

            $this->restoreBackup($run);
            $this->refreshRuntimeConfig();

            try {
                $this->maintenanceOff();
            } catch (Throwable $exception) {
                $run->update(['error' => 'Rollback finished but maintenance mode could not be cleared: '.$exception->getMessage()]);
            }

            $run->update([
                'status' => 'rolled_back',
                'rolled_back_at' => now(),
                'finished_at' => now(),
            ]);

            $this->audit('update.rolled_back', $run, $actor);

            return $run->refresh();
        } catch (Throwable $exception) {
            $run->update([
                'status' => 'rollback_failed',
                'error' => $exception->getMessage(),
                'finished_at' => now(),
            ]);

            $this->audit('update.rollback_failed', $run, $actor);

            throw new UpdateException(
                'Rollback failed: '.$exception->getMessage().' The storefront remains in maintenance mode.',
                $run
            );
        } finally {
            $this->releaseLock();
        }
    }

    public function rollbackLast(?User $actor = null): UpdateRun
    {
        $run = UpdateRun::query()
            ->where('status', 'completed')
            ->whereNotNull('backup_path')
            ->whereNotNull('database_backup_path')
            ->latest('id')
            ->first();

        if ($run === null) {
            throw new UpdateException('There is no completed update with a backup to roll back.');
        }

        return $this->rollback($run, $actor);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, UpdateRun>
     */
    public function recentRuns(int $limit = 12)
    {
        return UpdateRun::query()->latest('id')->take($limit)->get();
    }

    private function buildPlan(Manifest $manifest, ?string $targetVersion): UpdatePlan
    {
        $current = Version::current();
        $targetVersion = $targetVersion !== null ? Version::normalize($targetVersion) : null;

        $releases = $manifest->sortedReleases();
        $warnings = [];
        $error = null;
        $fatal = false;

        if ($targetVersion !== null) {
            if (Version::compare($targetVersion, $current) <= 0) {
                return new UpdatePlan(
                    currentVersion: $current,
                    releases: [],
                    warnings: [],
                    error: "This store is already on {$current}; choose a newer version.",
                    checkedAt: $this->client->lastCheckedAt(),
                    channel: $manifest->channel,
                    manifestUrl: $this->manifestUrl(),
                );
            }

            $known = array_filter($releases, static fn (Release $release): bool => $release->version === $targetVersion);
            if ($known === []) {
                return new UpdatePlan(
                    currentVersion: $current,
                    releases: [],
                    warnings: [],
                    error: "Version {$targetVersion} is not listed on the update server.",
                    checkedAt: $this->client->lastCheckedAt(),
                    channel: $manifest->channel,
                    manifestUrl: $this->manifestUrl(),
                );
            }
        }

        $chain = [];
        $cursor = $current;

        foreach ($releases as $release) {
            if (! Version::isNewer($release->version, $cursor)) {
                continue;
            }

            if ($targetVersion !== null && Version::compare($release->version, $targetVersion) > 0) {
                break;
            }

            $phpConstraint = $release->requires['php'] ?? $release->minPhp;

            if (! VersionConstraint::satisfies(PHP_VERSION, $phpConstraint)) {
                $message = "ShopKeeprr {$release->version} requires PHP {$phpConstraint}; this server runs PHP ".PHP_VERSION.'.';
                $targetVersion !== null ? $error = $message : $warnings[] = $this->planWarning('warning', $message);
                $fatal = true;
                break;
            }

            $from = trim((string) ($release->requires['from'] ?? ''));

            if ($from !== '' && ! VersionConstraint::satisfies($cursor, $from)) {
                $message = "ShopKeeprr {$release->version} requires an existing version matching {$from}, but this store is on {$cursor}.";
                $targetVersion !== null ? $error = $message : $warnings[] = $this->planWarning('warning', $message);
                $fatal = true;
                break;
            }

            if (! ReleaseSigner::verify($release)) {
                $message = "ShopKeeprr {$release->version} failed signature verification and was ignored.";
                $targetVersion !== null ? $error = $message : $warnings[] = $this->planWarning('blocking', $message);
                $fatal = true;
                break;
            }

            $chain[] = $release;
            $cursor = $release->version;

            if ($targetVersion !== null && Version::compare($cursor, $targetVersion) >= 0) {
                break;
            }
        }

        if ($targetVersion !== null && $cursor !== $targetVersion && $error === null) {
            $error = "No valid upgrade path from {$current} to {$targetVersion} was found.";
        }

        if ($chain === [] && $error === null) {
            $newest = $releases === [] ? null : $releases[array_key_last($releases)];

            if ($newest !== null && Version::isNewer($newest->version, $current)) {
                $error = "The available release ({$newest->version}) cannot be applied from {$current}.";
                if (! $fatal) {
                    $error .= ' The update server may be missing an intermediate release.';
                }
            }
        }

        if ($chain !== [] && $error === null) {
            $last = $chain[array_key_last($chain)];

            $compatibility = $this->compatibility->inspect($last->version, $last->moduleApi);
            $warnings = array_merge($warnings, $compatibility);
        }

        return new UpdatePlan(
            currentVersion: $current,
            releases: $chain,
            warnings: $warnings,
            error: $error,
            checkedAt: $this->client->lastCheckedAt(),
            channel: $manifest->channel,
            manifestUrl: $this->manifestUrl(),
        );
    }

    /**
     * @return array{severity: string, module: string|null, message: string}
     */
    private function planWarning(string $severity, string $message): array
    {
        return ['severity' => $severity, 'module' => null, 'message' => $message];
    }

    private function assertCanApply(Release $release): void
    {
        if (! (bool) config('nexuscart.updates.enabled', true)) {
            throw new UpdateException('Updates are disabled in this installation.');
        }

        if (app()->isDownForMaintenance()) {
            throw new UpdateException('The store is already in maintenance mode. Run "php artisan up" before updating.');
        }

        if (! Version::isNewer($release->version, Version::current())) {
            throw new UpdateException("This store is already on {$release->version} or newer.");
        }

        $phpConstraint = $release->requires['php'] ?? $release->minPhp;

        if (! VersionConstraint::satisfies(PHP_VERSION, $phpConstraint)) {
            throw new UpdateException("This release requires PHP {$phpConstraint}; this server runs PHP ".PHP_VERSION.'.');
        }

        if (! $this->lockDirectoryIsWritable()) {
            throw new UpdateException('The storage/app directory is not writable, so the updater cannot create a backup.');
        }

        $free = @disk_free_space($this->root());
        $needed = $release->size * 2 + 20 * 1024 * 1024;

        if ($free !== false && $free < $needed) {
            throw new UpdateException('There is not enough free disk space for the download and backup. Free at least '.$this->humanBytes((int) ($needed - $free)).'.');
        }
    }

    private function download(Release $release, UpdateRun $run): string
    {
        $directory = $this->updatesDirectory().'/downloads';
        $this->ensureDirectory($directory);

        $path = $directory.'/'.$release->version.'-'.bin2hex(random_bytes(5)).'.zip';
        $handle = fopen($path, 'wb');

        if ($handle === false) {
            throw new UpdateException('Could not open a file for the update download.');
        }

        $this->client->assertTransportIsTrusted($release->downloadUrl, 'update package');

        try {
            $response = Http::withUserAgent('ShopKeeprr/'.Version::current().' (updater)')
                ->timeout(max(30, (int) config('nexuscart.updates.download_timeout', 600)))
                ->withOptions(['sink' => $handle])
                ->get($release->downloadUrl);
        } catch (ConnectionException $exception) {
            fclose($handle);
            @unlink($path);

            throw new UpdateException('The update package could not be downloaded: '.$exception->getMessage());
        } catch (Throwable $exception) {
            fclose($handle);
            @unlink($path);

            throw new UpdateException('The update package could not be downloaded: '.$exception->getMessage());
        }

        fclose($handle);

        if (! $response->successful()) {
            @unlink($path);

            throw new UpdateException('The update server returned HTTP '.$response->status().' for the package.');
        }

        if (! is_file($path)) {
            throw new UpdateException('The update package download did not create a file.');
        }

        $size = (int) filesize($path);
        if ($size !== $release->size) {
            @unlink($path);

            throw new UpdateException("The update package is {$size} bytes, but the signed manifest says {$release->size}.");
        }

        $hash = hash_file('sha256', $path);
        if (! hash_equals($release->sha256, $hash)) {
            @unlink($path);

            throw new UpdateException('The downloaded update package does not match its signed SHA-256 hash.');
        }

        $run->update([
            'package_url' => $release->downloadUrl,
            'package_sha256' => $hash,
            'signature_verified' => true,
        ]);

        return $path;
    }

    private function applyFiles(Release $release, string $staging): void
    {
        foreach ($release->files as $relative => $hash) {
            $relative = UpdatePaths::normalize($relative);

            if (! UpdatePaths::isAllowed($relative)) {
                throw new UpdateException("Refusing to write protected path {$relative}.");
            }

            $source = UpdatePaths::absolute($staging, $relative);
            $target = UpdatePaths::absolute($this->root(), $relative);

            if (! is_file($source) || ! hash_equals($hash, hash_file('sha256', $source))) {
                throw new UpdateException("The staged file for {$relative} is missing or corrupt.");
            }

            $directory = dirname($target);
            if (! is_dir($directory) && ! @mkdir($directory, 0775, true) && ! is_dir($directory)) {
                throw new UpdateException("Could not create the directory for {$relative}.");
            }

            if (! is_writable($directory)) {
                throw new UpdateException("The directory for {$relative} is not writable.");
            }

            if (is_link($target)) {
                @unlink($target);
            }

            $temporary = $target.'.nexus-new-'.bin2hex(random_bytes(4));

            if (! @copy($source, $temporary)) {
                @unlink($temporary);
                throw new UpdateException("Could not stage {$relative} for installation.");
            }

            @chmod($temporary, 0644);

            if (! @rename($temporary, $target)) {
                @unlink($temporary);
                throw new UpdateException("Could not replace {$relative}.");
            }

            $this->invalidate($target);
        }

        foreach ($release->removedFiles as $relative) {
            $relative = UpdatePaths::normalize($relative);

            if (! UpdatePaths::isAllowed($relative)) {
                throw new UpdateException("Refusing to remove protected path {$relative}.");
            }

            $target = UpdatePaths::absolute($this->root(), $relative);

            if (is_file($target)) {
                @unlink($target);
                $this->invalidate($target);
            }
        }
    }

    private function runMigrations(UpdateRun $run): void
    {
        $php = $this->phpBinary();

        if ($php !== null && $this->canUseProcess()) {
            try {
                $process = new Process([$php, 'artisan', 'migrate', '--force', '--no-interaction'], $this->root());
                $process->setTimeout(1800);
                $process->run();

                if (! $process->isSuccessful()) {
                    $output = trim($process->getErrorOutput()."\n".$process->getOutput());

                    throw new UpdateException('The database migrations failed: '.($output !== '' ? $output : 'unknown error'));
                }

                return;
            } catch (UpdateException $exception) {
                throw $exception;
            } catch (Throwable) {
                // proc_open may be disabled even though the function exists.
            }
        }

        $this->mergeNewConfig();

        $exitCode = Artisan::call('migrate', ['--force' => true, '--no-interaction' => true]);
        $output = trim(Artisan::output());

        if ($exitCode !== 0) {
            throw new UpdateException('The database migrations failed: '.($output !== '' ? $this->plain($output) : 'unknown error'));
        }
    }

    private function verifyInstallation(Release $release): void
    {
        $configPath = $this->root().'/config/nexuscart.php';
        $config = is_file($configPath) ? @include $configPath : null;

        if (! is_array($config) || (string) ($config['version'] ?? '') !== $release->version) {
            throw new UpdateException('The installed files do not report the expected version after the update.');
        }

        foreach (['settings', 'orders', 'users', 'update_runs'] as $table) {
            if (! Schema::hasTable($table)) {
                throw new UpdateException("The database is missing the [{$table}] table after the update.");
            }
        }

        $ran = DB::table('migrations')->pluck('migration')->all();
        $pending = [];

        foreach (glob($this->root().'/database/migrations/*.php') ?: [] as $file) {
            $name = basename($file, '.php');

            if (! in_array($name, $ran, true)) {
                $pending[] = $name;
            }
        }

        if ($pending !== []) {
            throw new UpdateException('These migrations did not run: '.implode(', ', array_slice($pending, 0, 5)));
        }
    }

    private function restoreBackup(UpdateRun $run): void
    {
        $meta = is_array($run->backup_meta) ? $run->backup_meta : [];

        if ($meta === []) {
            throw new UpdateException('The file backup metadata is missing.');
        }

        // A database restore also restores the update_runs and audit_logs tables
        // to their pre-update state. Keep the history so the administrator can
        // still see what happened and roll back further if needed.
        $history = $this->historySnapshot();

        (new FileBackup($this->root()))->restore($meta);
        $run->update(['step' => 'database']);
        (new DatabaseBackup($this->root()))->restore($run);
        $this->restoreHistory($history);
    }

    /**
     * @return array{runs: list<array<string, mixed>>, audit: list<array<string, mixed>>}
     */
    private function historySnapshot(): array
    {
        $history = ['runs' => [], 'audit' => []];

        try {
            if (Schema::hasTable('update_runs')) {
                $history['runs'] = DB::table('update_runs')->orderBy('id')->get()->map(
                    static fn (object $row): array => (array) $row
                )->all();
            }
        } catch (Throwable) {
            //
        }

        try {
            if (Schema::hasTable('audit_logs')) {
                $history['audit'] = DB::table('audit_logs')
                    ->where('action', 'like', 'update.%')
                    ->orderBy('id')
                    ->get()
                    ->map(static fn (object $row): array => (array) $row)
                    ->all();
            }
        } catch (Throwable) {
            //
        }

        return $history;
    }

    /**
     * @param  array{runs: list<array<string, mixed>>, audit: list<array<string, mixed>>}  $history
     */
    private function restoreHistory(array $history): void
    {
        try {
            if ($history['runs'] !== [] && Schema::hasTable('update_runs')) {
                foreach ($history['runs'] as $row) {
                    if (! isset($row['id'])) {
                        continue;
                    }

                    $values = $row;
                    unset($values['id']);

                    if (isset($values['backup_meta']) && is_array($values['backup_meta'])) {
                        $values['backup_meta'] = json_encode($values['backup_meta'], JSON_UNESCAPED_SLASHES);
                    }

                    DB::table('update_runs')->updateOrInsert(['id' => $row['id']], $values);
                }
            }

            if ($history['audit'] !== [] && Schema::hasTable('audit_logs')) {
                foreach ($history['audit'] as $row) {
                    if (! isset($row['id'])) {
                        continue;
                    }

                    $values = $row;
                    unset($values['id']);

                    foreach (['old_values', 'new_values'] as $column) {
                        if (isset($values[$column]) && is_array($values[$column])) {
                            $values[$column] = json_encode($values[$column], JSON_UNESCAPED_SLASHES);
                        }
                    }

                    DB::table('audit_logs')->updateOrInsert(['id' => $row['id']], $values);
                }
            }
        } catch (Throwable) {
            // The application state is already restored. History is important,
            // but a failure to re-insert it must not force another rollback.
        }
    }

    private function maintenanceOn(): void
    {
        if (app()->isDownForMaintenance()) {
            return;
        }

        try {
            Artisan::call('down', ['--retry' => 60]);
        } catch (Throwable) {
            // The direct file fallback below still works for the file driver.
        }

        if (! app()->isDownForMaintenance()) {
            $path = $this->root().'/storage/framework/down';
            $this->ensureDirectory(dirname($path));

            $written = @file_put_contents($path, json_encode([
                'time' => time(),
                'message' => 'ShopKeeprr is being updated. Please check back shortly.',
                'retry' => 60,
            ]));

            if ($written === false || ! app()->isDownForMaintenance()) {
                throw new UpdateException('Could not enable maintenance mode. Check that storage/framework is writable.');
            }
        }
    }

    private function maintenanceOff(): void
    {
        try {
            Artisan::call('up');
        } catch (Throwable) {
            //
        }

        if (app()->isDownForMaintenance()) {
            @unlink($this->root().'/storage/framework/down');
        }

        if (app()->isDownForMaintenance()) {
            throw new UpdateException('Could not disable maintenance mode. Run "php artisan up" manually.');
        }
    }

    private function clearCachesSafely(): void
    {
        try {
            $php = $this->phpBinary();

            if ($php !== null && $this->canUseProcess()) {
                $process = new Process([$php, 'artisan', 'optimize:clear', '--no-interaction'], $this->root());
                $process->setTimeout(180);
                $process->run();

                if ($process->isSuccessful()) {
                    return;
                }
            }
        } catch (Throwable) {
            //
        }

        foreach (['config.php', 'routes-*.php', 'events.php', 'views/*'] as $pattern) {
            foreach (glob($this->root().'/bootstrap/cache/'.$pattern) ?: [] as $file) {
                if (is_file($file)) {
                    @unlink($file);
                }
            }
        }

        try {
            Artisan::call('view:clear');
            Artisan::call('cache:clear');
        } catch (Throwable) {
            //
        }

        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }
    }

    private function writeInstalledVersion(string $version): void
    {
        $path = $this->root().'/storage/app/installed.lock';

        try {
            $data = is_file($path) ? json_decode((string) file_get_contents($path), true) : [];
            $data = is_array($data) ? $data : [];
            $data['version'] = $version;
            $data['updated_at'] = now()->toIso8601String();

            @file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } catch (Throwable) {
            // The installed.lock version is informational; a failed write must
            // not roll back an otherwise successful update.
        }
    }

    private function refreshRuntimeConfig(): void
    {
        try {
            $config = @include $this->root().'/config/nexuscart.php';

            if (is_array($config)) {
                config(['nexuscart' => $config]);
                config(['app.version' => $config['version'] ?? config('app.version')]);
            }
        } catch (Throwable) {
            //
        }
    }

    private function mergeNewConfig(): void
    {
        $this->refreshRuntimeConfig();
    }

    private function audit(string $action, UpdateRun $run, ?User $actor): void
    {
        try {
            $request = app()->runningInConsole() ? null : request();

            AuditLog::query()->create([
                'user_id' => $actor?->id,
                'action' => $action,
                'model_type' => UpdateRun::class,
                'model_id' => $run->id,
                'old_values' => ['version' => $run->from_version],
                'new_values' => [
                    'version' => $run->status === 'rolled_back' ? $run->from_version : $run->to_version,
                    'status' => $run->status,
                    'backup' => $run->backup_path,
                ],
                'ip_address' => $request?->ip(),
                'user_agent' => $request?->userAgent(),
                'url' => $request?->fullUrl(),
                'method' => $request?->method(),
                'created_at' => now(),
            ]);
        } catch (Throwable) {
            // Audit logging is best-effort and must never mask an update error.
        }
    }

    private function acquireLock(): void
    {
        if ($this->lockHandle !== null) {
            return;
        }

        $directory = $this->updatesDirectory();
        $this->ensureDirectory($directory);

        $path = $directory.'/update.lock';
        $handle = @fopen($path, 'c+');

        if ($handle === false || ! flock($handle, LOCK_EX | LOCK_NB)) {
            if (is_resource($handle)) {
                fclose($handle);
            }

            throw new UpdateException('Another update is already running. Wait for it to finish and try again.');
        }

        ftruncate($handle, 0);
        fwrite($handle, json_encode([
            'pid' => getmypid(),
            'started_at' => now()->toIso8601String(),
            'version' => Version::current(),
        ], JSON_PRETTY_PRINT));
        fflush($handle);

        $this->lockHandle = $handle;
    }

    private function releaseLock(): void
    {
        if ($this->lockHandle === null) {
            return;
        }

        $path = $this->updatesDirectory().'/update.lock';

        flock($this->lockHandle, LOCK_UN);
        fclose($this->lockHandle);
        $this->lockHandle = null;
        @unlink($path);
    }

    private function updatesDirectory(): string
    {
        return $this->root().'/storage/app/updates';
    }

    private function lockDirectoryIsWritable(): bool
    {
        $directory = $this->root().'/storage/app';

        return is_dir($directory) && is_writable($directory);
    }

    private function ensureDirectory(string $directory): void
    {
        if (! is_dir($directory) && ! @mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new UpdateException('Could not create the update working directory.');
        }
    }

    private function phpBinary(): ?string
    {
        $configured = trim((string) config('nexuscart.updates.php_binary'));

        if ($configured !== '' && is_file($configured)) {
            return $configured;
        }

        if (PHP_SAPI === 'cli' && PHP_BINARY !== '' && is_file(PHP_BINARY)) {
            return PHP_BINARY;
        }

        $candidates = [
            PHP_BINDIR.'/php',
            PHP_BINDIR.'/php.exe',
            '/usr/local/bin/php',
            '/usr/bin/php',
        ];

        foreach ($candidates as $candidate) {
            if (is_file($candidate) && (DIRECTORY_SEPARATOR === '\\' || is_executable($candidate))) {
                return $candidate;
            }
        }

        return null;
    }

    private function canUseProcess(): bool
    {
        if (! function_exists('proc_open')) {
            return false;
        }

        $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));

        return ! in_array('proc_open', $disabled, true);
    }

    private function invalidate(string $path): void
    {
        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate($path, true);
        }
    }

    private function prune(): void
    {
        try {
            $keep = max(1, (int) config('nexuscart.updates.backup_keep', 5));
            $directories = glob($this->updatesDirectory().'/backups/*', GLOB_ONLYDIR) ?: [];

            usort($directories, static fn (string $left, string $right): int => filemtime($right) <=> filemtime($left));

            foreach (array_slice($directories, $keep) as $directory) {
                $this->removeDirectory($directory);
            }

            foreach (['staging', 'downloads'] as $sub) {
                foreach (glob($this->updatesDirectory().'/'.$sub.'/*') ?: [] as $path) {
                    if (is_file($path) && filemtime($path) < time() - 172800) {
                        @unlink($path);
                    } elseif (is_dir($path) && filemtime($path) < time() - 172800) {
                        $this->removeDirectory($path);
                    }
                }
            }
        } catch (Throwable) {
            //
        }
    }

    private function removeDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

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

    private function plain(string $text): string
    {
        return trim(preg_replace('/\s+/', ' ', strip_tags($text)) ?: $text);
    }

    private function humanBytes(int $bytes): string
    {
        return $bytes >= 1048576
            ? number_format($bytes / 1048576, 1).' MB'
            : number_format(max(1, $bytes / 1024)).' KB';
    }
}
