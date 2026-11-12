<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureStoreMode
{
    public function handle(Request $request, Closure $next, string ...$modes): Response
    {
        $current = nexus_mode();

        if (! $current || ! in_array($current->value, $modes, true)) {
            abort(404);
        }

        return $next($request);
    }
}
