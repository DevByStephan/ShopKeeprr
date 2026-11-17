# ShopKeeprr 1.1.1 — WooCommerce migration

Released 16 November 2026.

ShopKeeprr 1.1.1 adds the WooCommerce migration tool to the 1.1 admin. It is available under **Admin → Sell → WooCommerce migration** (`/admin/migrations/woocommerce`).

## What is included

- **CSV / ZIP migration** for WooCommerce products, variations, customers and orders.
- **Read-only WooCommerce REST API connection** for stores that expose the API. Credentials are encrypted at rest and are never written to logs.
- Dry runs before writing, idempotent re-runs, warnings/errors with a downloadable CSV and a migration history.
- Products are matched by SKU, customers by email and orders by WooCommerce order number.
- Imported customers receive an unusable random password and must use the storefront password reset before signing in.
- Imported orders keep their original dates, totals, addresses, line items, payments, refunds and a WooCommerce status-history entry.

## Database changes

Run migrations after replacing the application files:

```bash
php artisan migrate --force
php artisan optimize:clear
```

This adds `woocommerce_migrations`, `woocommerce_connections` and nullable billing/shipping address columns on `users`. Take a verified database backup before running a migration.

## Upgrade from 1.1.0

1. Back up the database and `public/uploads/`.
2. Replace the application files while keeping `.env`, `storage/`, `modules/`, `public/uploads/`, custom theme files and `database/database.sqlite` (if SQLite).
3. Run `php artisan migrate --force`.
4. Run `php artisan optimize:clear`.
5. Sign in and open **Admin → Sell → WooCommerce migration**.

Coupons, subscriptions, reviews and serialised plugin data are not imported. The admin demo remains read-only, so upload/import actions are intentionally blocked there.
