# Hello ShopKeeprr module

A minimal module that adds a signed-in account page. It demonstrates the manifest, provider, route and view conventions used by ShopKeeprr 1.1.0.

## Try it on staging

1. Copy this folder to `modules/hello-shopkeeprr` in a staging ShopKeeprr installation.
2. Open **Admin → Plugins** and confirm **Hello ShopKeeprr** is detected.
3. Read the trust warning and enable it.
4. Sign in as a customer and open `/account/hello-shopkeeprr`.

For a distributable package, ZIP the folder so `module.json` is at the ZIP root. Do not include `vendor/`, `node_modules/`, `.env`, databases, logs or customer data.

## Files

| File | Purpose |
| --- | --- |
| `module.json` | Name, slug, namespace, provider and compatibility range |
| `src/HelloShopKeeprrServiceProvider.php` | Loads the namespaced views and routes |
| `routes/web.php` | Adds the signed-in account route |
| `resources/views/index.blade.php` | Uses the active storefront theme layout |

## Notes

- Modules are trusted PHP code and are not sandboxed.
- This example has no migration, external request or stored data.
- Do not add release ZIPs or credentials to this public repository.
- The example targets ShopKeeprr `>=1.0.0 <2.0.0` and module API `1.0`.
