# Installation

ShopKeeprr is a Laravel application. Download the licensed release from [ShopKeeprr Store](https://store.shopkeeprr.com/), then install it on a server you control. No application ZIP is published in this repository.

## Requirements

- PHP 8.3 or newer with `pdo`, `openssl`, `mbstring`, `fileinfo`, `zip`, `tokenizer`, `json`, `dom` and `libxml`.
- MySQL 8+ for a production store, or SQLite for a local demo or a small installation.
- Apache, LiteSpeed or Nginx. The document root must point at `public/`, never the project root.
- HTTPS and a writable `storage/` directory.
- Composer 2 and Node 20+ only when rebuilding dependencies or front-end assets.
- Outbound HTTPS for updates. The browser installer and clean-process migrations use `proc_open`; documented fallbacks exist when a host disables it.
- A writable `bootstrap/cache/` and a database user limited to the store database.

The buyer package includes the built front-end assets, so Node is not required to run the store.

## Browser installer

1. Upload the licensed release so `public/` is the web root.
2. Create an empty MySQL database, or make `database/` writable for SQLite.
3. Open `https://your-store.com/install.php`.
4. Complete the server check, database step and store/owner account step.
5. Let the installer write `.env`, generate `APP_KEY`, run migrations and create the administrator.

The installer writes `storage/app/installed.lock` when it finishes. Do not delete that file unless you intentionally want to reinstall.

## Command-line install

```bash
composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate

# Set APP_URL, DB_* and MAIL_* in .env first.
php artisan migrate --force
php artisan shopkeeprr:install \
  --store-name="My Store" \
  --admin-email=you@example.com \
  --admin-password="a-long-random-password" \
  --mode=retail
```

`--mode` accepts `retail`, `b2b`, `subscription` or `multivendor`. Add `--live` when the storefront should open immediately. Add `--demo` only when you intentionally want demo catalog data. Use `--force` only when intentionally reinstalling over an existing lock.

If `proc_open` and `exec` are unavailable, the browser installer can still collect the database and store details, then show the equivalent migration and install commands to run from the command line.

## Demo data

Demo stores may include products, customers, orders, subscriptions, coupons and tax. Demo customer passwords are generated at install time, not fixed defaults. They are printed once and stored in `storage/app/demo-credentials.txt` outside the web root.

Before going live:

- Delete demo customers and demo products.
- Delete `storage/app/demo-credentials.txt`.
- Reset any account whose password was printed.
- Remove or protect `public/install.php`.
- Keep `.env`, `storage/`, `database/` and `vendor/` outside direct web access.

## Payments and checkout

Configure the payment provider in **Admin → Settings → Payments**. Use sandbox or test mode until the complete checkout, refund and webhook flow has been tested. The settings screen prints the webhook endpoints and shows whether the signing secret or webhook identifier is saved.

Do not place live credentials in this repository, a screenshot or a support ticket.

## Mail

Configure SMTP in **Admin → Settings → Mail** and send a test message before launch. Verify password reset and order email delivery from the same domain you use in production.

## After installation

- Set `APP_ENV=production`, `APP_DEBUG=false` and the real `APP_URL`.
- Point the document root at `public/`.
- Enable HTTPS and secure session cookies.
- Run the [production checklist](production.md).
- Back up the database, `.env`, `storage/app/` and `public/uploads/`.

If the browser installer cannot run, use the command-line path and include the error text when contacting support. Never send an application key, database password or license private key.
