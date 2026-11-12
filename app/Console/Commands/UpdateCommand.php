<?php

namespace App\Console\Commands;

use App\Models\UpdateRun;
use App\Services\Updates\UpdateException;
use App\Services\Updates\UpdateManager;
use App\Services\Updates\UpdatePlan;
use App\Support\Version;
use Illuminate\Console\Command;
use Throwable;

class UpdateCommand extends Command
{
    protected $aliases = ['nexus:update'];

    protected $signature = 'shopkeeprr:update
        {--check : Check for updates and show the release notes without installing}
        {--install= : Install a specific version from the available upgrade chain}
        {--rollback : Restore the files and database from a previous update backup}
        {--run= : Update run ID for --rollback, or "last" (default)}
        {--status : Show the current version and recent update history}
        {--force : Apply without asking for confirmation}
        {--allow-incompatible : Continue when an installed module declares an incompatible version}';

    protected $description = 'Check for, install or roll back a signed ShopKeeprr update.';

    public function handle(UpdateManager $manager): int
    {
        if ($this->option('rollback')) {
            return $this->rollback($manager);
        }

        if ($this->option('status')) {
            return $this->status($manager);
        }

        $target = $this->option('install');
        $target = is_string($target) && trim($target) !== '' ? trim($target) : null;

        $this->line('Current version: <info>'.Version::current().'</info>  ·  PHP '.PHP_VERSION);

        try {
            $plan = $manager->check(true, $target);
        } catch (UpdateException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($plan->error !== null) {
            $this->error($plan->error);

            return self::FAILURE;
        }

        if ($plan->upToDate()) {
            $this->info('ShopKeeprr is up to date.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->info('Available updates:');

        foreach ($plan->releases as $release) {
            $this->line("  {$release->version}  ·  {$release->humanSize()}  ·  {$release->changedFileCount()} files  ·  released {$release->releasedAt}");

            foreach ($release->highlights as $highlight) {
                $this->line("    - {$highlight}");
            }
        }

        $this->newLine();

        foreach ($plan->releases as $release) {
            if (trim($release->notes) === '') {
                continue;
            }

            $this->comment("Release notes for {$release->version}:");
            $this->line(trim($release->notes));
            $this->newLine();
        }

        $this->reportWarnings($plan);

        if ($this->option('check')) {
            return self::SUCCESS;
        }

        if ($plan->hasBlockingWarnings() && ! $this->option('allow-incompatible')) {
            $this->error('One or more installed modules declare that they are incompatible with this release.');
            $this->line('Review the warnings above, then re-run with --allow-incompatible if you accept the risk.');

            return self::FAILURE;
        }

        if (! $this->option('force')) {
            $this->warn('A database and file backup will be taken before anything is changed.');
            $this->warn('The storefront will be in maintenance mode while files and migrations are applied.');

            if (! $this->confirm('Install '.$plan->targetVersion().' now?', false)) {
                $this->line('Nothing was changed.');

                return self::SUCCESS;
            }
        }

        try {
            $runs = $manager->apply($plan);
        } catch (UpdateException $exception) {
            $this->error($exception->getMessage());

            if ($exception->run !== null) {
                $this->reportRun($exception->run->refresh());
            }

            $this->line('If the storefront is still in maintenance mode, run: php artisan up');

            return self::FAILURE;
        }

        $this->newLine();

        foreach ($runs as $run) {
            $this->reportRun($run);
        }

        $last = $runs[array_key_last($runs)];

        $this->info('ShopKeeprr is now running '.$last->to_version.'.');
        $this->line('Rollback: php artisan shopkeeprr:update --rollback --run='.$last->id);
        $this->line('Backup:   '.$last->backup_path.'  ·  '.$last->database_backup_path);

        return self::SUCCESS;
    }

    private function rollback(UpdateManager $manager): int
    {
        $requested = trim((string) $this->option('run'));

        if ($requested !== '' && strtolower($requested) !== 'last') {
            $run = UpdateRun::query()->find((int) $requested);

            if (! $run) {
                $this->error('No update run with ID '.$requested.' was found.');

                return self::FAILURE;
            }
        } else {
            $run = UpdateRun::query()
                ->where('status', 'completed')
                ->whereNotNull('backup_path')
                ->whereNotNull('database_backup_path')
                ->latest('id')
                ->first();

            if (! $run) {
                $this->error('There is no completed update with a backup to roll back.');

                return self::FAILURE;
            }
        }

        $this->warn("Rolling back update #{$run->id}: {$run->from_version} -> {$run->to_version}.");
        $this->warn('This restores the database to the moment before that update. Orders and customers created since then will be removed.');
        $this->line('Files:    '.$run->backup_path);
        $this->line('Database: '.$run->database_backup_path);

        if (! $this->option('force') && ! $this->confirm('Continue with the rollback?', false)) {
            $this->line('Nothing was changed.');

            return self::SUCCESS;
        }

        try {
            $run = $manager->rollback($run);
        } catch (UpdateException $exception) {
            $this->error($exception->getMessage());

            if ($exception->run !== null) {
                $this->reportRun($exception->run->refresh());
            }

            return self::FAILURE;
        }

        $this->info('Rolled back to '.$run->from_version.'.');
        $this->line('The storefront is live again.');

        return self::SUCCESS;
    }

    private function status(UpdateManager $manager): int
    {
        $this->line('ShopKeeprr:  <info>'.Version::current().'</info>');
        $this->line('PHP:        '.PHP_VERSION);
        $this->line('Channel:    '.$manager->channel());
        $this->line('Manifest:   '.$manager->manifestUrl());
        $this->line('Last check: '.($manager->lastCheckedAt() ?? 'never'));
        $this->newLine();

        $runs = $manager->recentRuns(15);

        if ($runs->isEmpty()) {
            $this->line('No updates have been installed yet.');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'From', 'To', 'Status', 'Started', 'Duration', 'Error'],
            $runs->map(fn (UpdateRun $run): array => [
                $run->id,
                $run->from_version,
                $run->to_version,
                $run->statusLabel(),
                $run->started_at?->format('Y-m-d H:i') ?? '—',
                $run->duration() ?? '—',
                $run->error ? mb_strimwidth(preg_replace('/\s+/', ' ', $run->error), 0, 70, '…') : '—',
            ])->all()
        );

        return self::SUCCESS;
    }

    private function reportWarnings(UpdatePlan $plan): void
    {
        if ($plan->warnings === []) {
            return;
        }

        $labels = [
            'blocking' => 'BLOCKING',
            'warning' => 'WARNING',
            'notice' => 'NOTE',
        ];

        $this->newLine();

        foreach ($plan->warnings as $warning) {
            $severity = (string) ($warning['severity'] ?? 'notice');
            $label = $labels[$severity] ?? strtoupper($severity);
            $message = (string) ($warning['message'] ?? '');

            $this->line("  [{$label}] {$message}");
        }

        $this->newLine();
    }

    private function reportRun(UpdateRun $run): void
    {
        $this->line("Update #{$run->id}: {$run->from_version} -> {$run->to_version} · {$run->statusLabel()}");

        if ($run->duration()) {
            $this->line('Duration: '.$run->duration().' · Files changed: '.$run->files_changed);
        }

        if ($run->error) {
            $this->line('Details: '.trim($run->error));
        }

        if ($run->backup_path) {
            $this->line('Backup:  '.$run->backup_path);
        }

        if ($run->database_backup_path) {
            $this->line('Database: '.$run->database_backup_path);
        }

        $this->newLine();
    }
}
