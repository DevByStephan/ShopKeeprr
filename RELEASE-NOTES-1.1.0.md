# ShopKeeprr 1.1.0

ShopKeeprr 1.1.0 is the free-launch release. It retains all 1.0 platform
features and adds a direct connection to the ShopKeeprr Store marketplace.

## Download

- Free core ZIP: <https://download.shopkeeprr.com/>
- Store and extensions: <https://store.shopkeeprr.com/>
- Documentation: <https://shopkeeprr.org/>

The core is free. Marketplace extensions may be free or paid.

## Highlights

- Store account linking from Admin → Marketplace with a one-time device code.
- Store catalog and owned-extension library loaded in Admin.
- One-click extension installs: server-side download, SHA-256 checksum
  verification, manifest validation, and modules installed disabled.
- Manual ZIP upload remains available for offline installs.
- Store site tokens are encrypted, masked and revocable; package download URLs
  are signed and expire.
- Marketplace scanner 1.1: critical execution findings block, warnings are
  allowed, and console/command shell findings are warnings. Per-file scan limit
  is 2 MB with a 32 MB read cap.
- shopkeeprr.com now uses **Download Now** and direct core downloads instead of
  a buy button.

## Requirements

- PHP 8.3 or newer.
- MySQL 8+ or SQLite 3.
- A web server whose document root points at `public/`.
- HTTPS for account and checkout pages.
- Composer 2 for command-line installation.
- Node 20+ only when rebuilding front-end assets.

## Upgrade from 1.0

Back up the database and files, replace the application files while preserving
`.env`, uploads and the database, then run:

```bash
php artisan migrate --force
php artisan optimize:clear
```

The 1.1 migration adds the `marketplace_connections` table. Existing modules,
themes and store data are unchanged.

## Verification

- Platform: 143 tests passed, 814 assertions.
- Store: 77 tests passed, 649 assertions.
- All changed PHP files pass `php -l`.

This repository remains documentation and examples. It does not contain the
application ZIP, `.env`, private extension packages, customer data or signing
material.
