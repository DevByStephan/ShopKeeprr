<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ResetDemoCommand extends Command
{
    protected $signature = 'shopkeeprr:demo:reset';

    protected $description = 'Restore the public demo database from its snapshot';

    public function handle(): int
    {
        if (! nexus_demo()) {
            $this->error('Demo mode is not enabled in this installation.');

            return self::FAILURE;
        }

        $snapshot = storage_path('app/demo-snapshot.sqlite');
        $database = (string) config('database.connections.sqlite.database');

        if (! is_file($snapshot)) {
            $this->error('Demo snapshot not found: '.$snapshot);

            return self::FAILURE;
        }

        if ($database === '' || ! is_file($database)) {
            $this->error('SQLite database not found: '.$database);

            return self::FAILURE;
        }

        DB::disconnect();

        if (! @copy($snapshot, $database)) {
            $this->error('Could not copy the demo snapshot over the database.');

            return self::FAILURE;
        }

        DB::purge();

        if (Schema::hasTable('sessions')) {
            DB::table('sessions')->delete();
        }

        if (Schema::hasTable('password_reset_tokens')) {
            DB::table('password_reset_tokens')->delete();
        }

        $this->callSilently('cache:clear');
        $this->info('Demo database restored. Sessions, orders and admin changes were reset.');

        return self::SUCCESS;
    }
}