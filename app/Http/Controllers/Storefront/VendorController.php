<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VendorController extends Controller
{
    public function index(): View
    {
        abort_unless(nexus_mode()?->allowsVendors(), 404);

        $vendors = Vendor::query()
            ->approved()
            ->withCount(['products' => fn ($query) => $query->visibleInMode()])
            ->orderBy('name')
            ->paginate(18);

        return nexus_view('vendors.index', compact('vendors'));
    }

    public function show(Request $request, string $slug): View
    {
        abort_unless(nexus_mode()?->allowsVendors(), 404);

        $vendor = Vendor::query()->approved()->where('slug', $slug)->firstOrFail();

        $products = Product::query()
            ->visibleInMode()
            ->where('vendor_id', $vendor->id)
            ->with(['images', 'category'])
            ->when($request->filled('q'), fn ($query) => $query->search($request->string('q')->toString()))
            ->latest('published_at')
            ->paginate(12)
            ->withQueryString();

        return nexus_view('vendors.show', compact('vendor', 'products'));
    }
}
