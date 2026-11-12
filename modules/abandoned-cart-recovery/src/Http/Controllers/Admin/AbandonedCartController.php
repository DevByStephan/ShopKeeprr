<?php

namespace Modules\AbandonedCart\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\View\View;
use Modules\AbandonedCart\Models\Reminder;

class AbandonedCartController extends Controller
{
    public function index(SettingsService $settings): View
    {
        $sent = Reminder::query()->count();
        $clicked = Reminder::query()->whereNotNull('clicked_at')->count();
        $recovered = Reminder::query()->with('order')->get()->filter(fn (Reminder $reminder) => $reminder->recovered())->count();

        return view('abandoned-cart::admin.index', [
            'enabled' => (bool) $settings->get('abandoned.enabled', true),
            'delayHours' => (int) $settings->get('abandoned.delay_hours', 4),
            'maxAgeDays' => (int) $settings->get('abandoned.max_age_days', 30),
            'sent' => $sent,
            'clicked' => $clicked,
            'recovered' => $recovered,
            'reminders' => Reminder::query()->with('order')->latest('sent_at')->paginate(20),
            'waiting' => Order::query()
                ->where('status', 'pending')
                ->whereNotNull('customer_email')
                ->whereNotIn('id', Reminder::query()->select('order_id'))
                ->where('created_at', '<=', now()->subHours((int) $settings->get('abandoned.delay_hours', 4)))
                ->count(),
        ]);
    }

    public function settings(Request $request, SettingsService $settings): RedirectResponse
    {
        $data = $request->validate([
            'enabled' => ['nullable', 'boolean'],
            'delay_hours' => ['required', 'integer', 'min:1', 'max:168'],
            'max_age_days' => ['required', 'integer', 'min:1', 'max:90'],
        ]);

        $settings->setMany([
            'abandoned.enabled' => $request->boolean('enabled'),
            'abandoned.delay_hours' => $data['delay_hours'],
            'abandoned.max_age_days' => $data['max_age_days'],
        ]);

        return back()->with('status', 'Recovery settings saved.');
    }

    public function run(): RedirectResponse
    {
        Artisan::call('shopkeeprr:abandoned-cart:remind');

        return back()->with('status', trim(Artisan::output()) ?: 'Recovery run finished.');
    }
}
