<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureVendor
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()) {
            return redirect()->route('login');
        }

        // Query rather than reading a cached relation: a status change made
        // earlier in the same request must take effect immediately.
        $vendor = $request->user()->vendorProfile()->first();

        if (! $vendor) {
            abort(403, 'This account is not linked to a seller profile.');
        }

        if (! $vendor->isApproved()) {
            abort(403, 'This seller account is '.$vendor->status.'. Contact the store owner for access.');
        }

        $request->attributes->set('vendor', $vendor);
        view()->share('currentVendor', $vendor);

        return $next($request);
    }
}
