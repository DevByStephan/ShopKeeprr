# ShopKeeprr 1.0.0

ShopKeeprr 1.0.0 is the first public release of the self-hosted commerce platform.

## Highlights

- Retail, B2B wholesale, subscription-box and multi-vendor store models.
- Four complete storefront themes: Editorial, Marketplace, Auction House and Global Bazaar.
- Drag-and-drop storefront builder with drafts, device previews, inline editing and safe input normalization.
- Module system with a manifest, service provider, routes, migrations, compatibility declarations and explicit trust confirmation.
- Storefront component registration through the block registry.
- Admin catalog, variants, inventory, orders, refunds, customers, coupons, shipping, tax and reports.
- Seller workspace with per-seller catalog ownership, commission and payout records.
- License keys, activation limits and private release downloads.
- Signed update system with manifest verification, database/file backups and rollback.
- Order, customer, seller, content and support tooling.
- PHPUnit coverage for the storefront, checkout, themes, modules, updates, inventory and multi-vendor flows.

## Requirements

- PHP 8.3 or newer.
- MySQL 8+ or SQLite 3.
- A web server whose document root points at `public/`.
- HTTPS for account and checkout pages.
- Composer 2 for command-line installation.
- Node 20+ only when rebuilding front-end assets.

## Installation

The licensed release is available from the [ShopKeeprr Store](https://store.shopkeeprr.com/). Follow [the installation guide](docs/installation.md) or use the retained [Installation & FAQ PDF](ShopKeeprr-Installation-and-FAQ.pdf).

## Updates

ShopKeeprr 1.0.0 supports signed updates from the admin or command line. Read [the update guide](docs/updates.md) before applying a release.

## Compatibility

Extensions should declare:

```json
{
  "requires": {
    "shopkeeprr": ">=1.0.0 <2.0.0",
    "module_api": "1.0"
  }
}
```

## Release package

This repository does not contain the application source, the paid release ZIP, update packages or license keys. The repository is the public documentation and example hub for version 1.0.0.
