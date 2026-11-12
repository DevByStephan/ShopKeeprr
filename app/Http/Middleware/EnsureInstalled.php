<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureInstalled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! nexus_installed() && ! app()->runningUnitTests()) {
            return redirect('/install.php');
        }

        return $next($request);
    }
}
