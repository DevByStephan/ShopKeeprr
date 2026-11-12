<?php

namespace Modules\Bundles\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Modules\Bundles\Models\Bundle;
use Modules\Bundles\Services\BundleCartService;

class BundleCartController extends Controller
{
    public function add(Bundle $bundle, BundleCartService $bundles): RedirectResponse
    {
        $bundles->add($bundle);

        return redirect()->route('storefront.cart')->with('status', $bundle->name.' added to your bag.');
    }
}
