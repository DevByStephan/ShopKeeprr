# Production checklist

Use this list before opening a ShopKeeprr store to customers.

## Server and application

- Serve only `public/` as the web root.
- Keep `.env`, `storage/`, `database/` and `vendor/` outside direct web access.
- Set `APP_ENV=production`, `APP_DEBUG=false` and the real `APP_URL`.
- Use HTTPS and secure session cookies.
- Create a database user limited to the store database.
- Keep `storage/`, `bootstrap/cache/`, the SQLite database and `public/uploads/` writable by PHP.
- Run the production cache command after deployment and clear caches after configuration changes.
- Remove or protect `public/install.php`.
- Delete demo users, demo products and `storage/app/demo-credentials.txt`.

## Payments and checkout

- Configure the payment provider in **Admin → Settings → Payments**.
- Test create, capture, cancel, refund and webhook reconciliation in sandbox mode.
- Confirm the webhook endpoint and signing secret are saved.
- Put the account into live mode only after the full checkout flow is verified.
- Do not store payment credentials in source control.

## Mail

- Configure SMTP in **Admin → Settings → Mail**.
- Send a test message and verify SPF, DKIM and DMARC for the sending domain.
- Test password reset and order receipts.

## Data and recovery

- Back up the database, `.env`, `storage/app/` and `public/uploads/` on a schedule.
- Keep an off-server copy and test a restore.
- Check the update backup retention and available disk space.
- Confirm the cron entry for `php artisan schedule:run` is active where scheduled work is used.

## Storefront

- Test all four themes or the active custom theme on mobile and desktop.
- Check the homepage, shop, product, cart, checkout, account and auth pages.
- Test an empty catalog, an out-of-stock product and a long product title.
- Verify legal pages, support links and the license agreement.
- Check keyboard focus, reduced motion and meaningful alt text.
- Confirm the GitHub and documentation links point to the public resources.

## Access

- Use unique administrator accounts; do not share one login.
- Require verified email for seller and account-sensitive actions.
- Remove unused administrators and seller accounts.
- Review the audit log and update history.
- Keep the server, PHP and dependencies patched.

## Before handoff

- Change temporary passwords.
- Remove test orders and test customer records where appropriate.
- Verify backups and document who can restore them.
- Give the store owner the support and security contacts.
- Keep the license private key and release credentials out of the repository.
