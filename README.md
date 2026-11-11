<p align="center">
  <img src="assets/shopkeeprr-icon.png" width="88" alt="ShopKeeprr">
</p>

<h1 align="center">ShopKeeprr</h1>

<p align="center"><strong>Public documentation and developer examples for ShopKeeprr 1.0.0.</strong></p>

<p align="center">
  <a href="https://shopkeeprr.com/">Website</a> ·
  <a href="https://store.shopkeeprr.com/">Store</a> ·
  <a href="https://shopkeeprr.org/">Documentation site</a> ·
  <a href="https://demo.shopkeeprr.com/">Live demo</a>
</p>

---

## What is ShopKeeprr?

ShopKeeprr is a self-hosted commerce platform built on Laravel. A licensed installation includes a storefront, admin workspace, retail/B2B/subscription/multi-vendor store models, four storefront themes, a visual storefront builder, an extension system and signed updates.

This repository is the public developer and buyer reference for **version 1.0.0**. It is **documentation and examples only**. It does not contain the ShopKeeprr application source, the paid release archive, license keys, update packages or customer data. The licensed application and its updates are distributed through [ShopKeeprr Store](https://store.shopkeeprr.com/).

**Current release:** 1.0.0  
**Stack:** Laravel 13 · PHP 8.3+ · MySQL 8 / SQLite · Vite · Tailwind CSS · Alpine.js

## Start here

| Guide | What it covers |
| --- | --- |
| [Installation](docs/installation.md) | Requirements, browser installer, CLI install, first-run cleanup |
| [Updating](docs/updates.md) | Signed updates, backups, rollback and staging |
| [Store models](docs/store-models.md) | Retail, wholesale, subscriptions and multi-vendor |
| [Storefront builder](docs/storefront-builder.md) | Blocks, drafts, previews and safe input |
| [Themes](docs/themes.md) | Presets, template resolution, custom themes and the template editor |
| [Modules](docs/modules.md) | Manifests, providers, routes, migrations and compatibility |
| [Marketplace](docs/marketplace.md) | Listings, review, licensing, commission and payouts |
| [Packaging releases](docs/releases.md) | Versioning, ZIP contents, scanning and private downloads |
| [Production checklist](docs/production.md) | HTTPS, database, mail, backups and go-live checks |
| [Troubleshooting](docs/troubleshooting.md) | Common installation, module, email and checkout issues |

The full [Installation & FAQ PDF](ShopKeeprr-Installation-and-FAQ.pdf) is retained for offline buyer reference.

## Developer examples

- [Hello ShopKeeprr module](examples/plugin/hello-shopkeeprr/README.md) — a minimal, signed-in account page built as a module with a manifest, provider, route and Blade view.
- [Atelier Example theme](examples/theme/atelier-example/README.md) — a preset-style theme with palette metadata and storefront view overrides.

Examples are starting points. Test every extension on a staging store before installing it on a live shop.

## Repository contents

```text
docs/                 Maintained Markdown reference
examples/plugin/      Minimal module template
examples/theme/       Minimal theme template
.github/              Issue and pull-request templates
SECURITY.md           Trust boundaries and vulnerability reporting
CONTRIBUTING.md       Documentation and example contribution rules
CHANGELOG.md          Public release history
```

## Release packages

No application ZIP, update package or paid download is published in this repository. Those files are delivered privately through the customer's [ShopKeeprr Store account](https://store.shopkeeprr.com/account/downloads). Do not upload a buyer archive, `.env`, license signing key, customer export or database file to GitHub.

## Support

- Store and account: <https://store.shopkeeprr.com/>
- Documentation site: <https://shopkeeprr.org/>
- Email: <info@shopkeeprr.com>
- Security reports: see [SECURITY.md](SECURITY.md)

## License

Documentation and example templates are provided under the terms in [LICENSE](LICENSE). The ShopKeeprr application is commercial software and is governed by the license included with the buyer package.
