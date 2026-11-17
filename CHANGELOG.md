# Changelog

## 1.1.2 — WooCommerce tax migration and Freeware

### Platform

- Added WooCommerce tax-rate migration from the standard tax-rate CSV export
  and from the read-only REST API.
- Rates are matched by country, state and name so re-running a migration updates
  the same rate instead of creating duplicates.
- Mapped WooCommerce `prices_include_tax` and `shipping_tax_class` settings and
  reported unsupported tax options without writing them.
- Added tax counts to dry runs and the migration summary.
- Linked the storefront and admin "Powered by ShopKeeprr" attribution to
  <https://shopkeeprr.com/>.
- Rebuilt the 1.1.2 package with the linked attribution; the version remains
  1.1.2 and the current checksum is published on the download site.

### Public documentation

- Updated the public reference, quick-start and examples to 1.1.2.
- Documented the Freeware distribution label and the 1.1.2 tax migration.

## 1.1.1 — WooCommerce migration

### Platform

- Added CSV/ZIP and read-only REST API migration for WooCommerce products,
  variations, customers and orders.
- Added dry runs, idempotent re-runs, downloadable error reports and migration
  history.
- Added `woocommerce_migrations` and `woocommerce_connections` tables and
  nullable WooCommerce address columns on users.

## 1.1.0 — Free launch and Store marketplace

### Platform

- Added Store account linking from Admin → Marketplace using a one-time device
  code. No Store password or client secret is stored in the self-hosted app.
- Added the Store catalog and connected-account library to the Admin marketplace.
- Added one-click installs with server-side download, SHA-256 verification,
  manifest validation and install-disabled defaults.
- Kept manual ZIP upload as the offline fallback.
- Added revocable, encrypted, masked Store site tokens and signed package URLs.
- Updated the marketplace scanner to 1.1: critical execution findings block,
  lower-risk warnings are allowed, and console/command shell findings are
  warnings. File scan limit is 2 MB with a 32 MB read cap.
- Changed the core launch to free with a direct download from shopkeeprr.com.

### Public documentation

- Updated the public reference to 1.1.0.
- Documented Store linking, one-click installs and the open marketplace review
  policy.
- Added the free core download and release-package guidance.

The paid extension archive and its signing material remain private to the Store.

## 1.0.0 — Initial public release

### Platform

- Added retail, B2B wholesale, subscription-box and multi-vendor store models.
- Added four storefront themes: Editorial, Marketplace, Auction House and Global Bazaar.
- Added the drag-and-drop storefront builder with drafts, device previews, inline editing and server-side normalization.
- Added the module system, manifest compatibility checks and storefront component registry.
- Added seller workspace, commission records and payout history.
- Added license keys, activation limits and private release downloads.
- Added signed updates with database/file backups and rollback.
- Added admin catalog, orders, refunds, customers, coupons, shipping, tax, reports, pages, support and audit tooling.
- Added PHPUnit coverage for storefront, checkout, themes, modules, updates, inventory and multi-vendor flows.

### Public documentation

- Added the installation, update, store-model, builder, theme, module, marketplace, release and production guides.
- Added the Hello ShopKeeprr module example.
- Added the Atelier Example theme template.
- Retained the 1.0.0 Installation & FAQ PDF for offline reference.
