# Updating ShopKeeprr

ShopKeeprr 1.1.0 includes signed update tooling, but updates are off by default until the vendor publishes a manifest host. When enabled, a release is downloaded from the update service, verified, backed up and applied with a rollback path. Core update packages are not committed to this repository; extension updates remain in the Store.

## Before updating

1. Download an independent backup of the database and `public/uploads/`.
2. Read the release notes and compatibility warnings.
3. Check that installed modules support the target release.
4. For a large or risky change, update a staging copy first and test checkout, email and account flows.

## What the updater does

1. Fetches release metadata over HTTPS.
2. Verifies the manifest signature, package SHA-256 hash and byte size.
3. Enables maintenance mode.
4. Dumps the database and zips every file that will be replaced.
5. Applies package files and runs pending migrations in a clean PHP process where available.
6. Verifies the version, required tables and migration state.
7. Clears compiled configuration, routes, views and caches.
8. Brings the store back online and records the run.

Orders, customers, products, pages, settings, theme choices, uploaded files, modules and `.env` are not part of the update package.

## Admin update

Open **Admin → Updates → Check for updates**, review the release notes and compatibility warnings, then choose **Review & install**. Do not close the tab while the update is running.

If the request times out, reload the screen. It reports whether the run was installed, rolled back, or is still in progress. Do not start a second update until the first run has a final state.

## Command line

```bash
php artisan shopkeeprr:update --check
php artisan shopkeeprr:update
php artisan shopkeeprr:update --status
php artisan shopkeeprr:update --rollback
```

Use the command line for large databases or restrictive shared hosts. `--force` skips the confirmation prompt; it does not skip signature checks or blocking module warnings.

## Rollback

The updater restores the database and replaced files together. Rolling back also removes orders and other changes created after the update, so export anything that must be retained before rolling back.

If a rollback fails, stop making changes, preserve the update logs and backups, and contact support. Do not manually delete `storage/app/updates/` while a run is incomplete.

## Module compatibility

Modules are not updated by ShopKeeprr. A module can declare the ShopKeeprr and PHP versions it supports. A declared incompatibility is shown as a blocking warning and must be acknowledged before installation. Test the module on staging or disable it until a compatible release is available.

## Release archives

Core updates can be delivered by the vendor's signed update service once that host is published. Extension updates remain available through your ShopKeeprr Store account. This repository intentionally contains no update ZIP, signing key or release asset.
