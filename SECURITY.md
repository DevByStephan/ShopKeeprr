# ShopKeeprr Security Notes

**Author:** Stephan Johnson (SJDesigns) · stephanjohnson109@gmail.com

This file explains the trust boundaries users should understand before extending a store. It is not a substitute for the full hardening checklist in the Installation & Setup Guide.

## Modules are trusted code

ShopKeeprr modules are PHP packages, not sandboxed plugins. When you install a module it runs with the same privileges as ShopKeeprr itself:

- It can read, change or delete any data in the store database, including customers, orders and payment settings.
- It can read files on the server, send email and call external services with your server's network access.
- Files under the module's `database/migrations` folder are executed automatically during installation. That code can alter the schema and run arbitrary SQL.
- Module routes and service providers are registered like application code.

Because of this, **only install modules from authors you trust** and keep a recent database backup before installing or updating one. The admin upload screen requires an explicit trust confirmation for this reason. Review module source code before installing it on a store that holds real customer data.

## Update integrity

Updates are delivered as signed release metadata and a ZIP package. The application pins the vendor's RSA public key in `config/nexuscart.php`. A release is accepted only when:

- The manifest is fetched over HTTPS (loopback is allowed for local testing only).
- The release metadata signature verifies with the pinned public key.
- The downloaded package matches the signed SHA-256 hash and byte size exactly.
- Every file inside the package matches the hash in the signed manifest.
- Paths stay inside the application; `.env`, `storage/`, `modules/`, uploads, custom theme templates, SQLite files, symlinks and absolute paths are rejected.

Do not replace the public key with one you did not generate or receive directly from the vendor. Whoever holds the matching private key can install code on every store that trusts that key.

Before changing files, the updater enables maintenance mode and writes a verified database dump plus a ZIP of every file it will replace. A failed step restores both. A successful step can be rolled back from **Admin → Updates**, but the database is restored to its pre-update state, so orders placed after the update are removed. See [`UPDATING.md`](UPDATING.md).

## Demo data

Demo customer accounts receive a random password generated at install time. It is written to `storage/app/demo-credentials.txt` (outside the web root, mode `0600`) and is never a fixed default. Delete demo customers and that file before going live.

## Reporting a vulnerability

If you find a security issue in ShopKeeprr, email **stephanjohnson109@gmail.com** with steps to reproduce and the affected version. Please do not publish details until a fix is available.

## Baseline hardening

- Serve only `public/` from the web root; keep `.env`, `storage/`, `database/` and `vendor/` outside it.
- Set `APP_ENV=production`, `APP_DEBUG=false`, a real `APP_URL` and `SESSION_SECURE_COOKIE=true`.
- Use a dedicated MySQL user limited to the store database.
- Point the mail transport at SMTP you control, and test password reset before launch.
- Put payment gateways in live mode only after testing the full checkout and webhook flow.
- Back up the database and `public/uploads/` on a schedule, and test restoration.
- Keep `storage/app/updates/backups` off-server as well as on the server, and verify a rollback on staging before a major release.
- If `opcache.validate_timestamps=0`, restart PHP-FPM after an update so other workers load the new code.
- Delete or rename `public/install.php` after installation.
- Remove unused administrator accounts and demo users.
