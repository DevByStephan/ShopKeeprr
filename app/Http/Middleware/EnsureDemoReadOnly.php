<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureDemoReadOnly
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! nexus_demo() || $request->isMethodSafe()) {
            return $next($request);
        }

        $route = $request->route()?->getName();

        if (in_array($route, ['admin.login.store', 'admin.logout', 'logout'], true)) {
            return $next($request);
        }

        // The payments screen deliberately stays editable in the demo for
        // invoice settings, while the controller ignores gateway credentials.
        if ($route === 'admin.settings.update' && $request->input('section') === 'payments') {
            return $next($request);
        }

        $path = trim($request->path(), '/');
        $guarded = str_starts_with($path, 'admin')
            || str_starts_with($path, 'vendor')
            || str_starts_with($path, 'account')
            || in_array($path, ['register', 'forgot-password', 'reset-password', 'newsletter', 'trade'], true);

        if (! $guarded) {
            return $next($request);
        }

        $message = 'This is a public demo. Admin changes, account changes and registration are disabled.';

        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 403);
        }

        return back()->with('demo_error', $message);
    }
}