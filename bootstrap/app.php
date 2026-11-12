<?php

use App\Http\Middleware\EnsureDemoReadOnly;
use App\Http\Middleware\EnsureInstalled;
use App\Http\Middleware\EnsureStoreIsLive;
use App\Http\Middleware\EnsureStoreMode;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\EnsureVendor;
use App\Http\Middleware\LogAdminActivity;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Cloudflare quick tunnels terminate HTTPS locally and forward the
        // original scheme. Trusting the local proxy keeps generated links,
        // redirects and secure cookies on https when the demo is public.
        $middleware->trustProxies(at: '*');

        $middleware->alias([
            'demo.readonly' => EnsureDemoReadOnly::class,
            'admin' => EnsureUserIsAdmin::class,
            'installed' => EnsureInstalled::class,
            'store.live' => EnsureStoreIsLive::class,
            'store.mode' => EnsureStoreMode::class,
            'admin.activity' => LogAdminActivity::class,
            'vendor' => EnsureVendor::class,
        ]);

        $middleware->web(append: [
            SecurityHeaders::class,
            EnsureDemoReadOnly::class,
        ]);

        // The updater puts the store into maintenance mode while files and
        // migrations change. Keep the update screen, login and compiled
        // assets reachable so an administrator can follow progress.
        $middleware->preventRequestsDuringMaintenance(except: [
            'admin/updates',
            'admin/updates/*',
            'admin/login',
            'build/*',
            'up',
        ]);

        $middleware->validateCsrfTokens(except: [
            'webhooks/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
