# Free launch checklist

The core release is free. The Store remains the place for accounts, marketplace
extensions, paid downloads and seller payouts.

## Website

1. Publish `ShopKeeprr Platform/site/` with `index.html` at the document root.
2. Upload `downloads/shopkeeprr-1.1.0.zip` beside it.
3. Confirm the three Download links resolve to
   `/downloads/shopkeeprr-1.1.0.zip` and the ZIP is served with
   `Content-Type: application/zip`.
4. Keep `downloads/SHA256SUMS` online so buyers can verify the package.
5. Clear the CDN cache for `/` and `/downloads/*`.
6. The expected page copy is “ShopKeeprr 1.1.0 · Free”, “Download free” and
   “Download ShopKeeprr 1.1”; there is no buy button.

The local source of the page is `ShopKeeprr Platform/site/index.html`. A copy is
kept at `ShopKeeprr 1.1/website-index.html` for review.

## GitHub public repository

1. Update the public docs to 1.1.0 and free pricing.
2. Add `RELEASE-NOTES-1.1.0.md`.
3. Attach the same `shopkeeprr-1.1.0.zip` to the GitHub release. Do not attach
   `.env`, the local database, logs, tests, the Store patch or signing material.
4. Do not commit the application ZIP, vendor archive or any customer/order data
   into the repository.
5. Keep extension packages and paid assets in the Store.

The public repository remains documentation and examples. The direct core ZIP
is the free download; paid or private extension packages stay behind Store
entitlements.

## Before announcing

- Verify the ZIP with `SHA256SUMS`.
- Install from the ZIP on a clean host, run the browser installer, and complete
  a test checkout.
- Connect the new install to the Store and install one free extension.
- Confirm the Store never exposes the platform `.env` or a site token.
- Keep a database and file backup of the previous public site.

## Do not do yet

No deployment or GitHub push is performed by the 1.1 build. Publishing requires
the site/GitHub release step to be run deliberately with production credentials.
