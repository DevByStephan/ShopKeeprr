# Store marketplace patch

These files are the Store-side half of the ShopKeeprr 1.1 marketplace. They are
copied from `NexusCart Store` at the same relative paths. Apply them to a Store
checkout, then run the new migration.

## Apply

1. Copy the files into the Store root, preserving paths.
2. Run `php artisan migrate --force`.
3. Run `php artisan optimize:clear`.
4. Run `php artisan route:list --path=marketplace` and confirm the site APIs,
   browser approval routes and the signed `api.v1.marketplace.package` route.
5. Run `php artisan test --compact --filter=MarketplaceSiteLinkTest`.

Do not copy `.env` or any local database into this patch.

## Routes

- `POST /api/v1/marketplace/link/start`
- `POST /api/v1/marketplace/link/poll`
- `POST /api/v1/marketplace/link/revoke`
- `GET /api/v1/marketplace/account`
- `GET /api/v1/marketplace/library`
- `POST /api/v1/marketplace/install`
- `GET /api/v1/marketplace/download/{release}/{link}` (signed, `package`)
- `GET /marketplace/link` and `POST /marketplace/link` for browser approval

## Files

The patch includes the site-link model and migration, bearer-token middleware,
API and browser link controllers, install/download controller, Store routes,
the approval views, the `User` relationship and the scanner 1.1 changes. The
focused feature test is included under `tests/Feature/`.

The Store is the source of truth for accounts, purchases and releases. The
platform only receives a scoped site token and a short-lived signed package URL.
