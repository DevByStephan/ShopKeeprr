<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureStoreIsLive
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->isAdmin()) {
            return $next($request);
        }

        if (! nexus_store_is_live()) {
            return response()->view('theme.errors.store-offline', [
                'mode' => nexus_mode(),
            ], 503);
        }

        return $next($request);
    }
}
