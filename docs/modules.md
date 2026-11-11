# Modules

Modules are self-contained packages under `modules/{slug}`. They add capabilities to a ShopKeeprr installation without modifying `app/`, `config/` or another module. A module is trusted, executable PHP code; it is not sandboxed.

## Manifest

Every module starts with a root `module.json`:

```json
{
  "name": "Hello ShopKeeprr",
  "slug": "hello-shopkeeprr",
  "version": "1.0.0",
  "type": "plugin",
  "namespace": "Modules\\HelloShopKeeprr\\",
  "source": "src",
  "provider": "Modules\\HelloShopKeeprr\\HelloShopKeeprrServiceProvider",
  "description": "Adds a small account page to demonstrate the module contract.",
  "requires": {
    "shopkeeprr": ">=1.0.0 <2.0.0",
    "module_api": "1.0"
  }
}
```

Required fields are `name`, `slug` and `provider`. `namespace` and `source` let ShopKeeprr autoload the module classes. `type`, `version` and `description` are used by the admin module manager and marketplace listing.

The `requires.shopkeeprr` constraint lets the updater warn before a core release. Use a semantic version range, not a single exact version. The `nexus` compatibility key is accepted for older packages, but new packages should use `shopkeeprr`.

## Service provider

The provider is the module entry point. Keep it small and explicit:

```php
<?php

namespace Modules\HelloShopKeeprr;

use Illuminate\Support\ServiceProvider;

class HelloShopKeeprrServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'hello-shopkeeprr');

        if (! $this->app->routesAreCached()) {
            require __DIR__.'/../routes/web.php';
            $this->app['router']->getRoutes()->refreshNameLookups();
            $this->app['router']->getRoutes()->refreshActionLookups();
        }
    }
}
```

Register views, routes, migrations, admin navigation, scheduled work and storefront blocks from the provider. Do not edit core files to wire a module.

## Routes

Module routes should use the same middleware as the surface they extend. Account pages normally use `web`, `installed` and `auth`. Admin pages additionally use the application's admin authorization middleware.

```php
Route::middleware(['web', 'installed', 'auth'])
    ->get('account/hello-shopkeeprr', fn () => view('hello-shopkeeprr::index'))
    ->name('hello-shopkeeprr.index');
```

Keep route names and paths vendor-prefixed so they cannot collide with core or another module.

## Migrations

Place migrations under the module's `database/migrations` directory and load them from the provider with `loadMigrationsFrom()`. Migrations run during installation. Treat every migration as production code: it can alter schema, write data and fail halfway through. Test it against a copy of a real store.

## Compatibility

Declare the ShopKeeprr and PHP versions you support:

```json
{
  "requires": {
    "shopkeeprr": ">=1.0.0 <2.0.0",
    "php": ">=8.3"
  }
}
```

If a package includes `composer.json`, the scanner also reads `require.php`, `require.laravel/framework` and `extra.shopkeeprr.requires`. A package that excludes the current PHP or ShopKeeprr release is approved with a compatibility warning; a declared blocking incompatibility must be acknowledged before the admin can install it.

## Security boundary

A module can:

- Read, change or delete any data in the store database.
- Read files on the server and call external services.
- Run arbitrary SQL or code during installation.

Because of this, **only install modules from authors you trust**. The admin requires an explicit trust confirmation before installation. Review the source and take a database backup first.

## Packaging

A clean module ZIP has `module.json` at its root:

```text
hello-shopkeeprr/
  module.json
  src/
    HelloShopKeeprrServiceProvider.php
  routes/
    web.php
  resources/
    views/
      index.blade.php
  README.md
```

Do not include `.env`, `vendor/`, `node_modules/`, local databases, logs, compiled caches, customer data or license keys. Bundled dependencies are not inspected by the automated scanner and always produce a manual-review warning.

Upload the ZIP under **Admin → Plugins**, confirm the author and trust warning, then enable the module. The [Hello ShopKeeprr example](../examples/plugin/hello-shopkeeprr/README.md) is a complete minimal template.
