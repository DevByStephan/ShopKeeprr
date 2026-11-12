# ShopKeeprr 1.1.0

Release date: 2026-11-11

ShopKeeprr 1.1 keeps every 1.0 capability and adds direct Store integration for
the Admin marketplace. The core application is now free to download from
[shopkeeprr.com](https://shopkeeprr.com/).

## Download

- Direct package: `https://shopkeeprr.com/downloads/shopkeeprr-1.1.0.zip`
- Source and developer documentation: <https://github.com/DevByStephan/ShopKeeprr>
- Store and extensions: <https://store.shopkeeprr.com/>

The core package is free. Marketplace extensions may be free or paid.

## What is new in 1.1

- **Store account linking** from Admin → Marketplace or Settings → Store
  account. The platform starts a one-time device-code request; approval happens
  while signed in to the Store. No Store password or client secret is stored in
  the self-hosted platform.
- **Store catalog and library** in Admin. The catalog is read from the Store and
  cached briefly; the library reflects the connected account's owned products.
- **One-click installs.** The platform asks the Store for the current package,
  downloads it server-side, verifies the SHA-256 checksum, validates the module
  manifest and installs it disabled. Enable it from Plugins when ready.
- **Manual ZIP upload remains** as the offline fallback.
- **Store site tokens** are revocable, encrypted at rest, masked in the UI and
  scoped to the Store account.
- **Marketplace scanner 1.1** keeps critical execution findings blocking while
  allowing warnings for lower-risk developer patterns such as console and
  command shell calls. Per-file scan size is 2 MB and the read limit is 32 MB.
- **Free launch presentation** on shopkeeprr.com: Download Now replaces the buy
  button and the core is delivered directly from the site.

## Retained from 1.0

- Retail, B2B wholesale, subscription-box and multi-vendor store models.
- Four storefront themes and the drag-and-drop storefront builder.
- Admin catalog, variants, inventory, orders, refunds, customers, coupons,
  shipping, tax, reports, pages, audit log and seller workspace.
- Module system, themes, storefront component registry and manual ZIP uploads.
- Signed update tooling with backups and rollback (disabled by default until a manifest host is published).
- Private marketplace downloads for paid extensions.

## Requirements

- PHP 8.3 or newer.
- MySQL 8+ or SQLite 3.
- A web server whose document root points at `public/`.
- HTTPS for account and checkout pages.
- Composer 2 for command-line installation.
- Node 20+ only when rebuilding front-end assets.

## Upgrade from 1.0

1. Back up the database and the existing application files.
2. Replace the application files, preserving `.env`, uploads and the database.
3. Run `php artisan migrate --force`.
4. Run `php artisan optimize:clear`.
5. Sign in to Admin and check Settings → Store account, then Admin →
   Marketplace.
6. Re-enable any installed modules from Plugins and smoke-test checkout.

The 1.1 migration only adds the `marketplace_connections` table. Existing
modules, themes and store data are unchanged.

## Verification

The release was tested locally with PHP 8.4.27:

- Platform: **143 tests passed, 814 assertions**.
- Store: **77 tests passed, 649 assertions**.
- All changed PHP files pass `php -l`.

The Store patch and its mapping are in `store-patch/`; the Store application also
contains the changes directly. See `MARKETPLACE-TESTING.md` for the focused test
recipe and manual connection checks.

## Package contents

The direct ZIP contains the application, `vendor/`, `public/build/`, migrations
and documentation. It deliberately excludes `.env`, the local SQLite database,
logs, tests, the demo marker, the Store patch and website staging files.
