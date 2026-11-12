# Build a ShopKeeprr plugin in 15 minutes

This guide takes you from an empty folder to a module you can upload, enable and
see on a live storefront. It uses only core ShopKeeprr 1.1 conventions: no
build step, no dependencies, no core edits.

You need a staging ShopKeeprr installation you can break, and PHP 8.3+.

## 1. The shape of a module (1 minute)

A module is a folder with a manifest at the root:

```text
hello-launch/
  module.json
  src/HelloLaunchServiceProvider.php
  routes/web.php
  resources/views/index.blade.php
  README.md
```

`module.json` tells ShopKeeprr what the module is, which namespace to autoload
and which service provider to boot. Everything else is ordinary Laravel.

## 2. The manifest (2 minutes)

Create `module.json`:

```json
{
  "name": "Hello Launch",
  "slug": "hello-launch",
  "version": "1.0.0",
  "type": "plugin",
  "namespace": "Modules\\HelloLaunch\\",
  "source": "src",
  "provider": "Modules\\HelloLaunch\\HelloLaunchServiceProvider",
  "description": "Adds a small launch countdown page to the account area.",
  "requires": {
    "shopkeeprr": ">=1.1.0 <2.0.0",
    "module_api": "1.0",
    "php": ">=8.3"
  }
}
```

Rules that matter:

- `slug` must match the folder name and be lowercase with hyphens.
- `namespace` must end with a backslash.
- `provider` must be the fully qualified class name of a class that exists.
- Keep `requires.shopkeeprr` a range, never one exact version.

## 3. The provider (3 minutes)

Create `src/HelloLaunchServiceProvider.php`:

```php
<?php

namespace Modules\HelloLaunch;

use Illuminate\Support\ServiceProvider;

class HelloLaunchServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'hello-launch');

        if (! $this->app->routesAreCached()) {
            require __DIR__.'/../routes/web.php';
            $this->app['router']->getRoutes()->refreshNameLookups();
            $this->app['router']->getRoutes()->refreshActionLookups();
        }
    }
}
```

That is the whole entry point. Register views, routes, migrations, scheduled
work and storefront blocks here. Never edit core files to wire a module.

## 4. A route and a view (5 minutes)

Create `routes/web.php`:

```php
<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'installed', 'auth'])
    ->get('account/hello-launch', fn () => view('hello-launch::index'))
    ->name('hello-launch.index');
```

The middleware matters:

- `web` gives you sessions, CSRF and cookies.
- `installed` keeps the route closed until the store is installed.
- `auth` keeps it closed to guests.

Create `resources/views/index.blade.php`:

```blade
@extends('theme.layouts.app')

@section('title', 'Hello Launch')

@section('content')
<div class="container-wide py-12">
    <div class="mx-auto max-w-xl rounded-2xl border border-[var(--line)] bg-[var(--card)] p-8">
        <h1 class="font-display text-3xl">Hello, {{ auth()->user()->name }}.</h1>
        <p class="mt-2 text-sm text-[var(--muted)]">
            This page is rendered by a module, using the active theme layout.
        </p>
        <a href="{{ route('storefront.account.index') }}" class="btn-primary mt-6">Back to account</a>
    </div>
</div>
@endsection
```

Extending `theme.layouts.app` means your page inherits the merchant's active
theme, header, footer and colour tokens. Do not hard-code colours; use the
`--brand`, `--ink`, `--surface`, `--line` tokens so the page stays legible in
every theme.

## 5. Package it (2 minutes)

From the parent folder:

```bash
zip -r hello-launch-1.0.0.zip hello-launch -x "*.DS_Store" -x "__MACOSX/*"
```

The ZIP must have `module.json` at the root, not inside an extra folder level.
Before uploading, check:

```bash
unzip -l hello-launch-1.0.0.zip | head
php -l hello-launch/src/HelloLaunchServiceProvider.php
```

Never include `vendor/`, `node_modules/`, `.env`, a database dump, logs or
customer data. Bundled dependencies are not scanned and always trigger a manual
review.

## 6. Install and see it (2 minutes)

1. Open **Admin → Plugins** and upload the ZIP.
2. Read the trust warning. A module is executable PHP with full database access.
3. Enable it.
4. Sign in as a customer and open `/account/hello-launch`.

If the page 404s, run `php artisan optimize:clear` and reload. If the admin does
not list the module, the manifest is invalid or the slug does not match the
folder name.

## 7. Where to go next

| Goal | Read |
| --- | --- |
| Store data of your own | [Modules](modules.md) — migrations section |
| Add an admin screen | [Modules](modules.md) — routes and middleware |
| Add a page-builder section | [Modules](modules.md) — `BlockRegistry` |
| Build a theme instead | [Themes](themes.md) |
| Sell it on the Store | [Marketplace listings](marketplace.md) |

## The five mistakes that cost the most time

1. **Wrong manifest root.** An extra wrapping folder makes the ZIP uninstallable.
2. **Missing `refreshNameLookups()`.** Routes load but `route()` throws.
3. **Hard-coded colours.** Your page looks broken on a merchant's dark theme.
4. **A migration that assumes empty tables.** Test against a copy of a real store.
5. **No `README.md` in the package.** Reviewers and buyers need install steps.

That is the whole contract. A useful module is a manifest, a provider and the
Laravel code you already know how to write.
