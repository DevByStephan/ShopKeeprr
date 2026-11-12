<?php

namespace Modules\AbandonedCart\Console;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\SettingsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Modules\AbandonedCart\Mail\AbandonedCheckoutReminder;
use Modules\AbandonedCart\Models\Reminder;
use Throwable;

class SendRemindersCommand extends Command
{
    protected $aliases = ['nexus:abandoned-cart:remind'];

    protected $signature = 'shopkeeprr:abandoned-cart:remind';

    protected $description = 'Email customers whose checkout was started but not paid.';

    public function handle(SettingsService $settings): int
    {
        if (! (bool) $settings->get('abandoned.enabled', true)) {
            $this->line('Abandoned checkout reminders are disabled.');

            return self::SUCCESS;
        }

        $delay = max(1, (int) $settings->get('abandoned.delay_hours', 4));
        $maxAge = max(1, (int) $settings->get('abandoned.max_age_days', 30));

        $orders = Order::query()
            ->with('items')
            ->where('status', OrderStatus::Pending->value)
            ->whereNotNull('customer_email')
            ->where('created_at', '<=', now()->subHours($delay))
            ->where('created_at', '>=', now()->subDays($maxAge))
            ->whereDoesntHave('payments', fn ($query) => $query->where('status', 'succeeded'))
            ->whereNotIn('id', Reminder::query()->select('order_id'))
            ->orderBy('created_at')
            ->limit(50)
            ->get();

        $sent = 0;

        foreach ($orders as $order) {
            try {
                $url = URL::temporarySignedRoute('abandoned.return', now()->addDays(7), ['order' => $order->id]);

                Mail::to($order->customer_email)->send(new AbandonedCheckoutReminder(
                    $order,
                    $url,
                    (string) nexus_setting('store.name', 'ShopKeeprr'),
                ));

                Reminder::query()->create([
                    'order_id' => $order->id,
                    'email' => $order->customer_email,
                    'sent_at' => now(),
                ]);

                $sent++;
            } catch (Throwable $exception) {
                report($exception);
                $this->warn('Could not send a reminder for order '.$order->number.'.');
            }
        }

        $this->info($sent.' reminder'.($sent === 1 ? '' : 's').' sent.');

        return self::SUCCESS;
    }
}
