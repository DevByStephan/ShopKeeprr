<?php

namespace App\Console\Commands;

use App\Services\StoreModeService;
use Illuminate\Console\Command;

class AuditStoreModeCommand extends Command
{
    protected $aliases = ['nexus:store-mode-audit'];

    protected $signature = 'shopkeeprr:store-mode-audit {--strict : Return a failure exit code when issues are found}';

    protected $description = 'Audit product compatibility and historical data before or after a business-model switch';

    public function handle(StoreModeService $modes): int
    {
        $integrity = $modes->integrityReport();

        $this->info('Store model integrity audit');
        $this->line('Conflicting products: '.$integrity['conflicts']);
        $this->line('Cross-model order lines: '.$integrity['cross_model_order_items']);
        $this->line('Cross-model seller splits: '.$integrity['cross_model_vendor_splits']);

        if ($integrity['conflict_products'] !== []) {
            $this->newLine();
            $this->warn('Products that cannot sell in any model:');

            foreach ($integrity['conflict_products'] as $product) {
                $this->line('  #'.$product['id'].' '.$product['name']);
                foreach ($product['reasons'] as $reason) {
                    $this->line('      - '.$reason);
                }
            }
        }

        $issues = $integrity['conflicts']
            + $integrity['cross_model_order_items']
            + $integrity['cross_model_vendor_splits'];

        if ($issues === 0) {
            $this->info('No integrity issues found.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->comment('Historical order lines and seller splits are reported only; they are never rewritten automatically.');

        return $this->option('strict') ? self::FAILURE : self::SUCCESS;
    }
}
