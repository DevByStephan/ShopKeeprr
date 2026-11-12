<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\VendorSplit;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $vendor = $request->attributes->get('vendor');

        $splits = $vendor->splits();

        $stats = [
            'products' => $vendor->products()->count(),
            'active_products' => $vendor->products()->where('is_active', true)->count(),
            'orders' => (clone $splits)->count(),
            'balance' => (clone $splits)
                ->whereIn('status', [VendorSplit::STATUS_PENDING, VendorSplit::STATUS_HELD])
                ->get()
                ->sum(fn (VendorSplit $split) => $split->netPayout()),
            'paid' => (float) (clone $splits)->where('status', VendorSplit::STATUS_PAID)->sum('payout_total'),
            'refunded' => (float) (clone $splits)->sum('refunded_total'),
        ];

        $recent = (clone $splits)->with('order')->latest()->take(8)->get();
        $products = $vendor->products()->with('images')->latest()->take(5)->get();

        return view('vendor.dashboard', compact('vendor', 'stats', 'recent', 'products'));
    }
}
