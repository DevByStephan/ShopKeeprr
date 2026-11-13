# Updating ShopKeeprr

ShopKeeprr 1.0.0 and newer include a signed update system. It downloads a release from the vendor's update server, verifies its SHA-256 hash and RSA-SHA256 signature, backs up the database and every file it is about to replace, puts the storefront into maintenance mode, applies the files and migrations, verifies the installation, and then brings the store back online.

Your orders, customers, products, pages, settings, theme choices, custom templates, uploaded files, modules and `.env` are never part of an update package.

---

## Before you update

1. **Check your backup.** The updater makes its own backups, but keep an independent copy as well. In cPanel or your host's file manager, download `storage/app/updates/backups` after an update if you want an off-server copy.
2. **Read the release notes.** The Updates screen shows exactly what changed, the number of files, the download size and any module compatibility warnings before the install button is enabled.
3. **Check your modules.** Modules are not sandboxed and are not updated by ShopKeeprr. If a module declares that it supports a different ShopKeeprr version, the updater warns you. A blocking warning must be acknowledged on the confirmation screen.
4. **Use a staging copy for large changes.** For a major release, restore a backup to a staging domain, update there, and test checkout before updating the live store.

---

## One-click update from the admin

1. Sign in to **Admin → Updates**.
2. Click **Check for updates**.
3. Review the release notes, file count and compatibility warnings.
4. Click **Review & install**.
5. Read the release notes and tick the confirmation checkbox.
6. Click **Install now**. Do not close the tab while the page is working.

What happens next:

| Step | What ShopKeeprr does |
| --- | --- |
| 1 | Downloads the package over HTTPS and verifies its SHA-256 hash and signature |
| 2 | Enables maintenance mode (customers see a friendly page; the update screen stays reachable) |
| 3 | Dumps the database and zips every file that will be replaced |
| 4 | Writes the new files and removes files that the release retired |
| 5 | Runs pending migrations in a clean PHP process, or in-process when the host blocks `proc_open` |
| 6 | Verifies the new version, required tables and migration state |
| 7 | Clears compiled configuration, routes, views and caches, then disables maintenance mode |
| 8 | Records the run in **Admin → Updates** and writes an audit-log entry |

A backup is also a tested recovery point. The newest five backup sets are kept by default (`SHOPKEEPRR_UPDATE_BACKUP_KEEP`).

### If the host has no `proc_open`

The updater detects this. It runs migrations inside the same PHP request instead of spawning a new process, and clears caches by deleting the compiled files directly. The update is slower but works. The browser installer has the same fallback, documented in the installation guide.

### Time limits

A large database dump or migration can take longer than a shared host's 30–60 second PHP limit. If the request times out, **the update does not silently continue**. Reload **Admin → Updates**:

- If the run says `Installed`, it finished.
- If it says `Rolled back`, the backup was restored and the store is on the old version.
- If it says `Rolling back` or `Rollback failed`, use **Admin → Updates → Roll back**, or run the CLI command below, then check the error message.

For large stores, use the command line instead.

---

## Command line

```bash
# Check and show release notes without changing anything
php artisan shopkeeprr:update --check

# Install the newest release, with confirmation
php artisan shopkeeprr:update

# Install a specific version from the available chain
php artisan shopkeeprr:update --install=1.1.1

# Show version and recent runs
php artisan shopkeeprr:update --status

# Roll back the last completed update
php artisan shopkeeprr:update --rollback

# Roll back a specific run
php artisan shopkeeprr:update --rollback --run=12
```

`--force` skips the confirmation prompt. It does **not** skip signature verification or blocking module warnings; add `--allow-incompatible` only after reviewing the warning.

The CLI is the most reliable option on shared hosting because there is no web-server timeout.

---

## What is backed up

Each run writes to `storage/app/updates/backups/{run-id}/`:

| File | Contents |
| --- | --- |
| `database.sqlite` | A consistent SQLite copy made with `VACUUM INTO` (SQLite stores) |
| `database.sql` | A `mysqldump` export when the host has the MySQL CLI tools |
| `database.jsonl.gz` | A portable, row-by-row database dump used when `mysqldump` is unavailable |
| `files.zip` | Every existing file the release replaces or removes |
| `backup.json` | File hashes, added files and removed files for the restore step |

The database backup is verified (SQLite integrity check or non-empty dump) before any file is touched.

---

## Rolling back

**Admin → Updates → Roll back**, or:

```bash
php artisan shopkeeprr:update --rollback
```

A rollback restores **both** the application files and the database to the moment before that update. It is intentionally a full restore:

- Orders, customers, reviews or other data created **after** the update are removed.
- Uploaded files and module folders are not touched.
- The storefront is put into maintenance mode during the restore.
- If the restore fails, maintenance mode stays on so customers cannot use a half-restored store. The error tells you which backup files to use for manual recovery.

Automatic rollback happens without asking when an update fails after files have been changed. A successful update never rolls itself back.

---

## Updating from the pre-release NexusCart 1.1.0 build (one-time)

ShopKeeprr 1.0.0 is the first public release. The internal NexusCart 1.1.0 build predates it, and because 1.0.0 is numerically lower the updater will not offer it as an upgrade. If you run that pre-release build:

1. Take a full backup: export the database and download `.env`, `storage/`, `modules/`, `public/uploads/` and `resources/views/theme/custom/`.
2. Download the **ShopKeeprr 1.0.0 full package**.
3. Upload and extract it on the server, next to the existing installation.
4. Copy the new application files over the old installation, **keeping** these from the old install:
   - `.env`
   - `storage/` (the entire folder)
   - `modules/`
   - `public/uploads/`
   - `resources/views/theme/custom/`
   - `database/database.sqlite` (SQLite stores)
   - any custom `.htaccess` / `public/.htaccess` rules
5. From the installation root, run:

   ```bash
   php artisan migrate --force
   php artisan optimize:clear
   ```

6. Sign in and open **Admin → Updates**. It should show version **1.0.0**. From this point every future release is one-click or `php artisan shopkeeprr:update`.

If you cannot run commands on the host, ask your host to run those two lines, or use cPanel → Terminal. This is a one-time step; 1.0.0 and newer update themselves.

---

## Update integrity

- The manifest URL is pinned in `config/nexuscart.php` and must use HTTPS.
- Every release is signed with the vendor's RSA private key. The matching public key ships in the application; only the vendor can sign a release that your store will accept.
- The package must also match the signed SHA-256 hash and byte size exactly.
- Package paths are checked against an allowlist. `.env`, `storage/`, `modules/`, `public/uploads/`, custom theme templates and SQLite database files are refused even if a signed manifest somehow lists them.
- ZIP entries with `..`, absolute paths, symbolic links or encryption are rejected.

Do not add an unknown public key to `SHOPKEEPRR_UPDATE_PUBLIC_KEY`. That would let whoever holds the matching private key install code on your store.

---

## Existing 1.0 stores and the rotated update key (one-time)

The 1.0 build pinned an update public key whose private half was not retained. The 1.1 package uses a newly generated key. A 1.0 store still holding the old pinned key cannot verify a manifest signed with the new key, so choose one of these one-time paths:

**Keep the one-click updater**

1. Back up `.env`.
2. Copy the `public_key` value from `config/nexuscart.php` in the 1.1 package into `.env` as `SHOPKEEPRR_UPDATE_PUBLIC_KEY=<value>`, replacing any old value.
3. Run `php artisan optimize:clear` so the old configuration cache is discarded.
4. Open **Admin → Updates**, or run `php artisan shopkeeprr:update --check`, and install 1.1.0 normally. The 1.1 package pins the same new key, so this override is only needed for the transition.

**Full install instead**

Download the 1.1 full package and replace the application files while keeping `.env`, `storage/`, `modules/`, `public/uploads/`, custom theme files and `database/database.sqlite`. Then run `php artisan migrate --force` and `php artisan optimize:clear`.

Only use the public key from the official 1.1 package. Do not accept a key sent by a third party.

---

## Troubleshooting

**"Could not reach the update server"** — your host may block outbound HTTPS. Allow `updates.shopkeeprr.com` (or the vendor's configured host), or run the CLI. The store is unchanged.

**"The update package does not match its signed SHA-256 hash"** — the download was corrupted or intercepted. Try again; if it repeats, contact support. The store is unchanged.

**"Another update is already running"** — wait for the other run to finish. The lock is released automatically when the process ends.

**"The store is already in maintenance mode"** — an earlier attempt stopped before cleanup. Run `php artisan up`, check **Admin → Updates** for the failed run, then retry.

**"storage/app is not writable"** — the updater cannot make a backup. Fix permissions on `storage/` and `bootstrap/cache/` (usually `755` for folders, `644` for files, owned by the PHP user), then retry.

**"These migrations did not run"** — the release was rolled back automatically. Check the run's error. The usual cause is a PHP version or database permission problem.

**A module stopped working after an update** — disable it in **Admin → Plugins**, check for a module update from its author, or restore the pre-update database and files from `storage/app/updates/backups`. The core update does not modify module files.

**The admin update page is blank during maintenance** — refresh it. Update routes and compiled assets are exempt from maintenance mode; the login page is too.

**PHP OPcache serves old code** — most hosts validate file timestamps. If yours has `opcache.validate_timestamps=0`, restart PHP-FPM after an update or ask your host to do it. The updater invalidates the files it writes in the current process, but it cannot restart other workers.

---

## Support information to include

When contacting support about an update, include:

- The version before and after (`Admin → Updates`).
- The run ID and status.
- The exact error text from the run.
- Your PHP version and database type (MySQL or SQLite).
- Whether `proc_open` is enabled.
- The contents of `storage/logs/laravel.log` around the run.

Never send your `.env` file, database dump, customer data or payment keys.
