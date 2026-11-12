<?php

namespace App\Console\Commands;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Console\Command;
use Throwable;

class PruneAbandonedOrders extends Command
{
    protected $aliases = ['nexus:orders:prune-abandoned'];

    protected $signature = 'shopkeeprr:orders:prune-abandoned
        {--hours= : Override the configured abandonment window}
        {--dry-run : List the orders without cancelling them}';

    protected $description = 'Cancel unpaid orders older than the configured abandonment window.';

    public function handle(OrderService $service): int
    {
        $configured = (int) nexus_setting('orders.abandoned_after_hours', 24);
        $hours = max(1, (int) ($this->option('hours') ?: $configured));
        $cutoff = now()->subHours($hours);
        $dryRun = (bool) $this->option('dry-run');

        $query = Order::query()
            ->where('status', OrderStatus::Pending->value)
            ->where('payment_status', PaymentStatus::Pending->value)
            ->where('paid_total', 0)
            ->where('created_at', '<=', $cutoff)
            ->orderBy('id');

        $cancelled = 0;
        $failed = 0;

        $query->chunkById(100, function ($orders) use ($service, $hours, $dryRun, &$cancelled, &$failed): void {
            foreach ($orders as $order) {
                if ($dryRun) {
                    $this->line("Would cancel {$order->number} ({$order->customer_email}), placed {$order->created_at?->format('Y-m-d H:i')}.");
                    $cancelled++;

                    continue;
                }

                try {
                    $service->cancel($order, null, "Abandoned after {$hours} hours without payment.");
                    $cancelled++;
                } catch (Throwable $exception) {
                    $failed++;
                    report($exception);
                    $this->warn("Could not cancel {$order->number}: {$exception->getMessage()}");
                }
            }
        });

        if ($dryRun) {
            $this->info("{$cancelled} order(s) would be cancelled after {$hours} hours.");
        } else {
            $this->info("Cancelled {$cancelled} abandoned order(s) after {$hours} hours.");
        }

        if ($failed > 0) {
            $this->warn("{$failed} order(s) could not be cancelled; check the application log.");
        }

        return self::SUCCESS;
    }
}
