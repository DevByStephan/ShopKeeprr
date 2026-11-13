# ShopKeeprr Live Demo

**Permanent public URL:** https://demo.shopkeeprr.com

**Local URL:** http://127.0.0.1:8080

The public demo is served directly from BlueHost over HTTPS. It no longer depends on a Cloudflare Quick Tunnel, so the link is stable.

## Open the demo

| Destination | URL |
| --- | --- |
| Storefront | https://demo.shopkeeprr.com/ |
| Demo guide and credentials | https://demo.shopkeeprr.com/demo |
| Admin sign-in | https://demo.shopkeeprr.com/admin/login |

## Sign in

**Administrator (read-only)**

- Email: `admin@shopkeeprr.test`
- Password: `ShopKeeprr2026!`

**Shopper**

- Email: `maya@example.com`
- Password: `ShopKeeprrShopper2026!`

The admin can inspect every screen. Settings, products, orders, customers, themes, plugins, updates and store-mode changes are blocked so the demo stays consistent. Registration and password resets are closed. Checkout uses a manual demo invoice; no card is charged and mail is written to the log only.

## Hosted deployment

The permanent demo runs from `/home2/xwlwrcmy/demo.shopkeeprr.com` on BlueHost, with its own SQLite database at `database/database.sqlite`. Its root `.htaccess` bridges requests to `public/`; the application is still a normal Laravel app. The demo includes the in-page marketplace details dialog so the listing states can be inspected without leaving the marketplace.

The hosted environment uses:

- `APP_URL=https://demo.shopkeeprr.com`
- `NEXUSCART_STORE_URL=https://store.shopkeeprr.com`
- `DB_CONNECTION=sqlite`
- `APP_ENV=production`, `APP_DEBUG=false`
- The read-only demo guard and `noindex, nofollow`
- The uploaded demo SQLite data, separate from the Store's customer database

## Start the demo locally

Run [start-demo.bat](start-demo.bat), or from this folder:

```bat
..\.tools\php\php.exe artisan serve --host=127.0.0.1 --port=8080
```

The public demo does not require a tunnel. Only bind the local development server to port `8080`; do not expose any other local service.

## Reset the local demo

Run [reset-demo.bat](reset-demo.bat), or:

```bat
..\.tools\php\php.exe artisan shopkeeprr:demo:reset
```

This restores `storage/app/demo-snapshot.sqlite` over the live database, clears sessions and password-reset tokens, and signs everyone out. The hosted demo uses the uploaded database; reset it by restoring the prepared snapshot through the hosting account rather than exposing a public reset button.

## Rebuild the snapshot

If you intentionally change the demo data, update the snapshot:

```bat
copy database\database.sqlite storage\app\demo-snapshot.sqlite
```

Do not copy a database that contains changes you do not want visitors to see.

## Security posture

- Admin write requests are blocked by `EnsureDemoReadOnly` for every `admin/*`, `vendor/*` and `account/*` mutation, including module admin routes.
- Customer registration (`/register`) and password reset (`/forgot-password`) are closed: the pages return 404 and write attempts are blocked.
- The installer (`public/install.php`) has been removed and `/install` returns 404.
- Stripe and PayPal are disabled. Manual invoice is the only checkout method.
- Mail uses the `log` driver; no email leaves the server.
- Installation tracking and the signed updater are disabled.
- `APP_DEBUG=false`, `APP_ENV=production`, and responses are marked `noindex, nofollow`.
- Sessions and password-reset tokens are cleared whenever the local demo is reset.
- The demo has no real customer, order or payment data.

## Release

The current public release is ShopKeeprr 1.1.0:

```text
https://download.shopkeeprr.com/
```

SHA-256: `edf1d2ca7ee8f09011d92015a7a46601a3007184415bd739c52642ee53dfd9a7`

The buyer archive is built without tests, local databases, logs, demo credentials, the Store patch or the local demo configuration.

Do not ship this demo folder, its `.env`, its SQLite database or `storage/app/demo-credentials.txt` to buyers.
