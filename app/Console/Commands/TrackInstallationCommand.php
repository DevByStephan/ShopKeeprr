<?php

namespace App\Console\Commands;

use App\Services\Tracking\InstallTracker;
use Illuminate\Console\Command;

class TrackInstallationCommand extends Command
{
    protected $aliases = ['nexus:track'];

    protected $signature = 'shopkeeprr:track
        {--force : Re-register even when a tracking file already exists}';

    protected $description = 'Register this ShopKeeprr installation and send its daily heartbeat.';

    public function handle(InstallTracker $tracker): int
    {
        if (! $tracker->enabled()) {
            $this->warn('Installation tracking is disabled in config/tracking.php.');

            return self::SUCCESS;
        }

        $result = $this->option('force')
            ? $tracker->register(null, null, true)
            : $tracker->heartbeat();

        $result['ok'] ? $this->info($result['message']) : $this->warn($result['message']);

        return $result['ok'] ? self::SUCCESS : self::FAILURE;
    }
}
