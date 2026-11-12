<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\VendorSplit;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function index(Request $request): View
    {
        $vendor = $request->attributes->get('vendor');

        $splits = $vendor->splits()
            ->with('order')
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('vendor.orders.index', compact('vendor', 'splits'));
    }

    public function show(Request $request, VendorSplit $split): View
    {
        $vendor = $request->attributes->get('vendor');

        abort_unless((int) $split->vendor_id === (int) $vendor->id, 404);

        $split->load(['order.items' => fn ($query) => $query->where('vendor_id', $vendor->id)]);

        return view('vendor.orders.show', compact('vendor', 'split'));
    }
}
