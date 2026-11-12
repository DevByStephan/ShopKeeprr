<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $revenue = (float) Order::query()
            ->whereIn('payment_status', ['paid', 'partially_refunded'])
            ->sum(DB::raw('paid_total - refunded_total'));

        $ordersCount = Order::query()->count();
        $customersCount = User::query()->where('role', 'customer')->count();
        $averageOrder = (float) Order::query()->whereIn('payment_status', ['paid', 'partially_refunded'])->avg('grand_total');
        $pendingOrders = Order::query()->whereIn('status', ['pending', 'processing', 'on_hold'])->count();
        $pendingWholesale = User::query()->where('wholesale_status', 'pending')->count();

        $recentOrders = Order::query()->with('user')->latest()->take(7)->get();

        $lowStock = Product::query()
            ->where('manage_stock', true)
            ->whereColumn('stock_quantity', '<=', 'low_stock_threshold')
            ->orderBy('stock_quantity')
            ->take(5)
            ->get();

        $activeSubscriptions = Subscription::query()->whereIn('status', ['active', 'trialing'])->get();
        $mrr = $activeSubscriptions->sum(fn (Subscription $subscription) => $this->monthlyValue($subscription));

        $start = now()->subDays(13)->startOfDay();
        $salesRows = DB::table('orders')
            ->whereIn('payment_status', ['paid', 'partially_refunded'])
            ->where('created_at', '>=', $start)
            ->selectRaw('DATE(created_at) as day, SUM(paid_total - refunded_total) as total, COUNT(*) as orders')
            ->groupBy('day')
            ->pluck('total', 'day');

        $sales = collect(range(0, 13))->map(function ($offset) use ($start, $salesRows) {
            $day = $start->copy()->addDays($offset);
            $key = $day->toDateString();

            return [
                'label' => $day->format('M j'),
                'total' => (float) ($salesRows[$key] ?? 0),
            ];
        });

        $topProducts = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereIn('orders.payment_status', ['paid', 'partially_refunded'])
            ->selectRaw('order_items.product_name, SUM(order_items.quantity) as quantity, SUM(order_items.line_total) as revenue')
            ->groupBy('order_items.product_name')
            ->orderByDesc('revenue')
            ->limit(5)
            ->get();

        return view('admin.dashboard', compact(
            'revenue',
            'ordersCount',
            'customersCount',
            'averageOrder',
            'pendingOrders',
            'pendingWholesale',
            'recentOrders',
            'lowStock',
            'activeSubscriptions',
            'mrr',
            'sales',
            'topProducts'
        ));
    }

    private function monthlyValue(Subscription $subscription): float
    {
        $count = max(1, $subscription->interval_count);
        $value = (float) $subscription->price * $subscription->quantity;

        return match ($subscription->interval) {
            'day' => $value * (30 / $count),
            'week' => $value * (4.33 / $count),
            'year' => $value / (12 * $count),
            default => $value / $count,
        };
    }
}
