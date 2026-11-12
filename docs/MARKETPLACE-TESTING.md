# Marketplace testing

## Automated checks

From `ShopKeeprr 1.1`:

```bash
php artisan test --compact
```

Expected on this release: **143 passed (814 assertions)**.

From `NexusCart Store`:

```bash
php artisan test --compact
```

Expected on this release: **77 passed (649 assertions)**.

Focused marketplace files:

```bash
# Platform
php artisan test --compact --filter=MarketplaceTest

# Store
php artisan test --compact --filter=MarketplaceSiteLinkTest
```

The platform test covers link start, encrypted connection storage, a
checksum-verified one-click install and a checksum mismatch. The Store test
covers link start → browser approval → token issuance, account lookup, a free
install and paid entitlement rejection.

## Local setup

The 1.1 app needs a local `.env` and an absolute SQLite path:

```dotenv
APP_ENV=local
APP_DEBUG=true
DB_CONNECTION=sqlite
DB_DATABASE="C:/absolute/path/to/ShopKeeprr 1.1/database/database.sqlite"
SHOPKEEPRR_STORE_URL=https://store.shopkeeprr.com
```

The Store URL must use HTTPS. `SHOPKEEPRR_STORE_ALLOW_HTTP=true` is only for a
loopback development Store.

Run `php artisan migrate --force`, then `php artisan route:list
--path=admin/marketplace`. The expected platform routes are index, connect,
link, status, disconnect, refresh and install.

## Manual connection and install

1. Start the Store locally or use `https://store.shopkeeprr.com`.
2. Sign in to the platform as an administrator.
3. Open **Admin → Marketplace → Connect Store**.
4. Follow the one-time code to the Store, sign in, and approve the site.
5. The platform polls once; the connection card should show the masked token
   and account email.
6. Open a free extension and choose **Install**. The module must appear in
   Plugins as disabled.
7. Enable it from Plugins and verify its admin/storefront surfaces.
8. Disconnect the Store account and confirm the token is gone.

For a paid extension, use an account that does not own it and confirm the
Store returns `purchase_required`; purchase with a test account, then retry.
Never use a real card or production payment data in local tests.

## Security checks

- The package URL host must match the connected Store origin.
- The response checksum must match the downloaded bytes before extraction.
- The module slug in the manifest must match the requested product.
- The module is installed disabled; never auto-enable downloaded code.
- Store tokens are encrypted and only the last four characters are displayed.
- A failed/revoked link must not be able to call account, library, install or
  download endpoints.
- The signed package URL expires after ten minutes and is bound to the release
  and site link.

## Known boundaries

- The Store is the source of truth for purchases and releases.
- The marketplace is open at launch. Critical scanner findings still block;
  warnings are allowed so legitimate developer tooling is not rejected.
- The scanner is a safety net, not a sandbox. Installed modules are trusted code.
