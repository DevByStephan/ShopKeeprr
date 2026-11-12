<p align="center">
  <img src="assets/shopkeeprr-icon.png" width="88" alt="ShopKeeprr">
</p>

<h1 align="center">ShopKeeprr</h1>

<p align="center"><strong>Public documentation and developer examples for ShopKeeprr 1.1.0.</strong></p>

<p align="center">
  <a href="https://shopkeeprr.com/">Website</a> ·
  <a href="https://store.shopkeeprr.com/">Store</a> ·
  <a href="https://shopkeeprr.org/">Documentation site</a> ·
  <a href="https://demo.shopkeeprr.com/">Live demo</a>
</p>

---

## What is ShopKeeprr?

ShopKeeprr is a free, self-hosted commerce platform built on Laravel. The core
includes a storefront, admin workspace, retail/B2B/subscription/multi-vendor
store models, four storefront themes, a visual storefront builder, an extension
system and signed updates.

This repository is the public developer and documentation reference for
**version 1.1.0**. It contains documentation and examples only. The free core
application is downloaded from [download.shopkeeprr.com](https://download.shopkeeprr.com/); this
repository does not contain the application archive, customer data or private
extension packages. Extensions are distributed through
[ShopKeeprr Store](https://store.shopkeeprr.com/).

**Current release:** 1.1.0 — free core
**Download:** <https://download.shopkeeprr.com/>
**Stack:** Laravel 13 · PHP 8.3+ · MySQL 8 / SQLite · Vite · Tailwind CSS · Alpine.js

## What is new in 1.1

- Store account linking from the Admin marketplace using a one-time device code.
- The Store catalog and the connected account's library appear in Admin.
- One-click installs verify the package SHA-256 checksum and install modules
  disabled until an administrator enables them.
- Manual ZIP upload remains available as the offline fallback.
- Marketplace scanner 1.1 keeps critical execution findings blocking while
  allowing lower-risk warnings, including console and command shell calls.
- The core is free at launch and is downloaded directly from shopkeeprr.com.

See [RELEASE-NOTES-1.1.0.md](RELEASE-NOTES-1.1.0.md) for the full release notes.

## Start here

| Guide | What it covers |
| --- | --- |
| [Installation](docs/installation.md) | Requirements, browser installer, CLI install, first-run cleanup |
| [Updating](docs/updates.md) | Signed updates, backups, rollback and staging |
| [Store models](docs/store-models.md) | Retail, wholesale, subscriptions and multi-vendor |
| [Storefront builder](docs/storefront-builder.md) | Blocks, drafts, previews and safe input |
| [Themes](docs/themes.md) | Presets, template resolution, custom themes and the template editor |
| [Modules](docs/modules.md) | Manifests, providers, routes, migrations and compatibility |
| [Marketplace](docs/marketplace.md) | Linking, one-click installs, listings, review and payouts |
| [Packaging releases](docs/releases.md) | Versioning, ZIP contents, scanning and private downloads |
| [Production checklist](docs/production.md) | HTTPS, database, mail, backups and go-live checks |
| [Troubleshooting](docs/troubleshooting.md) | Common installation, module, email and checkout issues |

The [Installation & FAQ PDF](ShopKeeprr-Installation-and-FAQ.pdf) is retained for
offline reference.

## Developer examples

- [Hello ShopKeeprr module](examples/plugin/hello-shopkeeprr/README.md) — a minimal, signed-in account page built as a module with a manifest, provider, route and Blade view.
- [Atelier Example theme](examples/theme/atelier-example/README.md) — a preset-style theme with palette metadata and storefront view overrides.

Examples are starting points. Test every extension on a staging store before
installing it on a live shop.

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

The free core ZIP is available from
[download.shopkeeprr.com](https://download.shopkeeprr.com/). No
application source, `.env`, signing key, customer export or database file is
published in this repository.

Marketplace extension packages remain private to the Store and are delivered
through the customer's [ShopKeeprr Store account](https://store.shopkeeprr.com/).
Do not upload an application archive, an extension package, credentials or customer
data to GitHub.

## Support

- Store and account: <https://store.shopkeeprr.com/>
- Documentation site: <https://shopkeeprr.org/>
- Email: <info@shopkeeprr.com>
- Security reports: see [SECURITY.md](SECURITY.md)

## License

The free core application is governed by the license included with its download.
Documentation and example templates are provided under the terms in
[LICENSE](LICENSE).
