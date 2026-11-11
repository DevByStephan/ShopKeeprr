# ShopKeeprr

A self-hosted, multi-mode commerce platform built on Laravel. ShopKeeprr ships with retail, B2B wholesale, subscription and multi-vendor business models, a Stripe/PayPal checkout layer, an admin dashboard, a drag-and-drop storefront builder, an extensible module system and four production-ready storefront themes.

**Version:** 1.0.0  
**Author:** Stephan Johnson (DevByStephan) · stephanjohnson109@gmail.com or info@shopkeeprr.com
**Stack:** Laravel 13 · PHP 8.3+ · MySQL 8 / SQLite · Vite 8 · Tailwind CSS 4 · Alpine.js 3

> **Documentation:** the buyer package includes a full `documentation/ShopKeeprr-Installation-and-FAQ.pdf` covering installation, first-run setup, store models, themes, payments, email, legal pages, security and troubleshooting. See [`UPDATING.md`](UPDATING.md) for the signed update and rollback guide.

---

## Requirements

- PHP 8.3 or newer with `pdo`, `openssl`, `mbstring`, `fileinfo`, `zip`, `tokenizer`, `json`, `dom` and `libxml`
- MySQL 8+ or SQLite 3
- Composer 2
- Node 20+ and npm (only when rebuilding front-end assets)
- `proc_open` enabled for the one-click browser installer and clean-process migrations. If your host disables it, both the installer and the updater have documented fallbacks — the store runs normally either way.
- Outbound HTTPS access to the vendor's update host for lifetime updates, and a writable `storage/app` directory for database/file backups
- The PHP `zip` extension, used by the installer, module uploader and updater

The front-end build is committed in `public/build`, so buyers do not need Node to run the store.

---

## Installation

### Option A — browser installer (recommended)

1. Upload the project so the `public/` folder is the web root.
2. Create an empty database (MySQL) or make sure `database/` is writable (SQLite).
3. Open `https://your-store.com/install.php`.
4. Follow the four steps: server check → database → store and owner account → install.
5. The installer writes `.env`, generates `APP_KEY`, runs migrations, creates the administrator and optionally seeds the demo catalog.

The installer locks itself by writing `storage/app/installed.lock`. Delete that file only when you intentionally want to reinstall.

### Option B — command line (also the fallback when `proc_open` is disabled)

```bash
composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate

# Set APP_URL, DB_* and MAIL_* in .env first.
php artisan migrate --force
php artisan shopkeeprr:install \
  --store-name="My Store" \
  --admin-email=you@example.com \
  --admin-password="a-long-password" \
  --mode=retail \
  --demo
```

`--mode` accepts `retail`, `b2b`, `subscription` or `multivendor`. Add `--live` to open the storefront immediately, `--store-email=` for a different public contact address, and `--force` only when reinstalling over an existing lock.

If your host disables both `proc_open` and `exec`, the browser installer still collects your database and store details, then shows these commands instead of running them itself:

```bash
php artisan config:clear
php artisan migrate --force
php artisan shopkeeprr:install --data="/full/path/to/storage/app/install-payload.json"
```

### Demo data

The installer can seed a complete demo store (products, customers, orders, subscriptions, coupons and tax). Demo customer accounts receive a **random password generated at install time**, never a fixed default. It is printed once in the terminal and saved to `storage/app/demo-credentials.txt` (outside the web root). Set `SHOPKEEPRR_DEMO_PASSWORD` before seeding if you want to choose the value yourself.

Demo accounts exist for demonstration only. Delete them, or reset their passwords, and delete `storage/app/demo-credentials.txt` before going live.

---

## Updates

Every 1.0.0 store can receive signed lifetime updates from **Admin → Updates** or the command line. The updater:

1. Compares the installed version with the vendor's signed release manifest.
2. Verifies the package's RSA-SHA256 signature, SHA-256 hash and byte size before using it.
3. Enables maintenance mode, dumps the database and zips every file it will replace.
4. Applies files and runs pending migrations in a clean PHP process where possible.
5. Verifies the version, required tables and migration state, clears caches and brings the store back online.
6. Rolls both backups back automatically if any step fails.

```bash
php artisan shopkeeprr:update --check      # show available releases and notes
php artisan shopkeeprr:update              # install with confirmation
php artisan shopkeeprr:update --status     # current version and history
php artisan shopkeeprr:update --rollback   # restore the last update's backups
```

Release notes, module compatibility warnings, backup locations and the manual rollback procedure are documented in [`UPDATING.md`](UPDATING.md). The internal pre-release build predates the updater; it needs the one-time file upgrade described there.

### Installation tracking

A completed installation registers with the official ShopKeeprr store and sends a daily heartbeat. This lets the vendor compare distribution with paid sales and see which domains are running older versions. The first successful setup writes `storage/app/install-tracking.json` with an anonymous install ID, the store URL, administrator email entered during setup, version, store model, theme, PHP version, database driver and enabled module count. It never sends customers, orders, payment data or product data.

Change the endpoint in `.env` (`SHOPKEEPRR_TRACKING_URL`) and disable the heartbeat completely with:

```dotenv
SHOPKEEPRR_TRACKING_ENABLED=false
```

The Store admin sees the install list, active counts and installs-per-sale comparison under **Admin → Installations**. The endpoint is public by necessity; it accepts registration and heartbeat payloads only, rate-limits both routes and stores the administrator email encrypted.

---

## Store models

Choose a model in **Admin → Settings**. It can be changed later without migrations.

| Model | What changes |
| --- | --- |
| Retail | Standard catalog and checkout |
| B2B wholesale | Trade applications, tier pricing, hidden guest prices and minimum order quantities |
| Subscription box | Recurring plans on products, customer subscription management and renewal state |
| Multi-vendor marketplace | Approved sellers, a seller portal, per-seller commission and automatic split payouts through Stripe Connect or PayPal Payouts |

### Seller portal

Create sellers in **Admin → Vendors**, link each one to a customer account and connect their payout account. Sellers sign in at `/vendor` to manage their own catalog, see their orders and request a payout. Commission can be a percentage, a fixed fee per seller order or both, and each seller can inherit the platform rule or override it. ShopKeeprr takes commission from the discounted item subtotal; shipping and tax stay with the platform. A seller's products disappear from the storefront the moment the account is suspended or unapproved.

Stripe Connect is the recommended path for automatic splits: sellers finish Stripe-hosted onboarding, and each paid order is transferred with separate charges and transfers (the supported pattern for a cart containing more than one seller). Transfers are reversed automatically when an order is refunded. PayPal cannot split a standard checkout, so PayPal orders are collected by the store and sellers are paid through the PayPal Payouts API after the hold period; the store's PayPal app must have Payouts approved. Manual payouts are available and all three methods are recorded in the seller ledger.

Payments live in **Admin → Settings → Payments**. Stripe and PayPal credentials are encrypted at rest. Use the sandbox/test toggle while integrating. The same screen prints the exact webhook endpoints to paste into each provider dashboard, together with whether the signing secret or webhook ID has been saved:

- `POST /webhooks/stripe` — signature checked against the Stripe signing secret with a five-minute tolerance
- `POST /webhooks/paypal` — signature checked through PayPal's verification endpoint

Deliveries are idempotent (replays never double-charge or double-refund), amounts and currencies are matched against the order before it is marked paid, and refunds issued from the Stripe or PayPal dashboard are reconciled back onto the order automatically. A payment that completes after the abandonment task cancelled the order reopens the order and re-reserves stock instead of silently keeping the money. Refunds can be started from the admin order screen; the gateway refund and the order totals update together, with an idempotency token preventing duplicate refunds from a double-click.

---

## Storefront builder

**Admin → Storefront builder** replaces the theme homepage with a page assembled from sections. Drag a block from the library onto the live preview, or click it to append. Reorder by dragging on the page or in the Layers panel. Click any editable text in the preview to change it in place, and use the right panel for images, links, layout, spacing, background and dividers.

Built-in sections: hero banner, image carousel, featured products, category tiles, promo banner, testimonials, newsletter, text section, image, countdown banner and reassurance bar. Product sections read the live catalog, category tiles count visible products, and every block inherits the active theme's tokens and product cards.

- **Drafts are automatic.** Every change is saved to a draft; shoppers keep seeing the last published layout until an administrator presses **Publish**.
- **Device previews.** Switch between desktop, tablet and mobile widths without leaving the builder.
- **Reversible.** **Use theme homepage** returns to the active theme's own homepage while keeping the published layout saved; **Starter sections** restores a sensible default page.
- **Safe input.** Every save is normalised server-side: unknown block types are dropped, links are restricted to `http(s)`, `/` or `#`, images are limited to uploads or absolute URLs, text is escaped and a page is capped at 80 sections and 512 KB.

The block catalogue lives in [`config/storefront_blocks.php`](config/storefront_blocks.php) and is validated by [`app/Services/Storefront/BlockRegistry.php`](app/Services/Storefront/BlockRegistry.php). Modules can register their own blocks from a service provider with `BlockRegistry::register()`, which is how third-party storefront components are distributed. See the developer documentation on the marketplace for a complete component example.

---

## Themes

ShopKeeprr includes four complete storefront themes. Switch them in **Admin → Theme**. Activating a theme applies its curated palette and typography instantly; products, orders, customers and saved template overrides are untouched.

| Theme | Positioning | Palette |
| --- | --- | --- |
| **Editorial** | Boutique, typography-led catalogs | Cream, deep teal, amber |
| **Marketplace** | Dense, search-first high-SKU stores | Navy, amber, yellow CTAs, blue links |
| **Auction House** | Listings, deals and discovery | White, marketplace blue, yellow/green accents |
| **Global Bazaar** | Promotion-led discount retail | White, signal red, warm orange |

Each preset includes its own header, footer, homepage and product card, plus responsive navigation and account menus. Shared pages (shop, product, cart, checkout, account, auth) inherit the active theme's colour tokens and typography.

### How theme resolution works

1. An administrator override in `resources/views/theme/custom/{view}.blade.php` wins.
2. A preset override in `resources/views/theme/presets/{preset}/{view}.blade.php` is used next.
3. The shared default in `resources/views/theme/{view}.blade.php` is the fallback.

Theme metadata, palettes, fonts, default copy and preview swatches live in [`config/themes.php`](config/themes.php). The active preset is resolved by `App\Services\ThemeService`; CSS custom properties are printed by [`resources/views/theme/layouts/app.blade.php`](resources/views/theme/layouts/app.blade.php).

### Adding another theme

1. Copy an existing preset folder under `resources/views/theme/presets/` and rename it to your theme key.
2. Add an entry to `config/themes.php` with a label, palette, defaults and preview colours.
3. Add any views the theme should override (`home`, `partials.header`, `partials.footer`, `partials.product-card`, `shop.index`, ...).
4. Run `npm run build`, then activate the theme in **Admin → Theme**.

Palettes are split into semantic tokens (`--brand`, `--link`, `--surface`, `--line`, `--price`, `--sale`, ...) so themes stay legible across every commerce screen instead of relying on one accent colour.

### Template editor

**Admin → Theme → Templates** edits a copy of the shared templates under `resources/views/theme/custom`. Blade is compiled before saving, so syntax errors are rejected. Backups are written to `storage/app/theme-backups`, and **Reset to default** removes the override.

---

## Admin features

- Dashboard with revenue, orders, customers and recent activity
- Order, refund and fulfilment management with status history
- Catalog, variants, tier prices, inventory movements, categories, coupons and pages
- Customer and wholesale approval management
- Subscription plans and customer subscriptions
- Shipping zones/methods, tax rates and reports with CSV export
- Settings for store, checkout, payments, mail and theme
- Drag-and-drop storefront builder with drafts, inline editing, device previews and one-click publishing
- Module manager for dropping in payment processors and other extensions
- Signed one-click updates with release notes, module compatibility warnings, database/file backups and rollback
- Audit log of admin actions and update runs

---

## Modules

Modules are self-contained packages under `modules/{slug}` with a `module.json` manifest. Upload a ZIP in **Admin → Plugins**, confirm that you trust the author, then enable it. Manifests declare a namespace, provider and type. Keep module code outside `app/` so upgrades stay clean.

Modules can declare their compatibility so the updater can warn before a core release:

```json
{
  "requires": {
    "shopkeeprr": ">=1.0.0 <2.0.0",
    "module_api": "1.0"
  }
}
```

A declared incompatibility is a blocking warning that an administrator must acknowledge. A module without a declaration still works, but the updater can only warn that it was not tested against the new release.

> **Security:** a module is trusted, executable code — it is not sandboxed. It runs with the same privileges as ShopKeeprr, can read or change your database and files, and any migrations bundled in its `database/migrations` folder are executed automatically during installation. Only install modules from authors you trust, and take a database backup first. See [`SECURITY.md`](SECURITY.md).

ShopKeeprr no longer ships a marketplace browser. Plugins, themes and components are distributed by the vendor's own store; download a package there and upload the ZIP under **Admin → Plugins**. This keeps the application self-contained, avoids a second catalog inside the admin panel and removes the risk of offering another copy of ShopKeeprr over the running site. Every uploaded module still shows the trust warning below before it can run.

The free launch modules are bundled in the buyer archive. Paid modules are sold separately by ShopKeeprr Store and are packaged with `tools/publish-module-releases.php`:

| Module | Type | Distribution | What it adds |
| --- | --- | --- | --- |
| Product Reviews & Ratings | Plugin | Bundled free | Verified reviews, star ratings, photos, helpful votes and moderation |
| Customer Wishlist | Plugin | Bundled free | Per-account saved products and a private wishlist page |
| Announcement & Promotion Bars | Plugin | Bundled free | Builder blocks for announcement bars and countdown promotions |
| Abandoned Cart Recovery | Plugin | ShopKeeprr Store | Timed unpaid-checkout emails, signed return links and recovery metrics |
| Product Bundles & Upsells | Plugin | ShopKeeprr Store | Frequently-bought-together sets with proportional bundle discounts and stock checks |
| Loyalty & Rewards | Plugin | ShopKeeprr Store | Points on paid orders, a customer ledger and single-use reward coupons |
| Email Marketing Hub | Plugin | ShopKeeprr Store | Consent-aware Mailchimp, Klaviyo, Brevo, MailerLite and Constant Contact sync |

---

## Production checklist

- Set `APP_ENV=production`, `APP_DEBUG=false` and a real `APP_URL`.
- Point the web root at `public/`, never the project root.
- Protect `.env`, `storage/`, `database/` and `vendor/` from direct web access.
- Use HTTPS and set secure session cookies (`SESSION_SECURE_COOKIE=true`).
- Create a MySQL user limited to the store database; do not use `root`.
- Run `php artisan optimize` after deployment and clear caches after config changes.
- Keep `storage/`, `bootstrap/cache/`, `database/` (SQLite) and `public/uploads/` writable by PHP.
- Configure real SMTP in **Admin → Settings → Mail** and send a test order email.
- Configure Stripe/PayPal live keys only after testing the full checkout and webhook flow.
- Add a cron entry for `php artisan schedule:run` so abandoned unpaid orders are cancelled hourly (`shopkeeprr:orders:prune-abandoned`) and enabled modules can run their own schedules, then extend it for any scheduled modules.
- Decide whether installation tracking should stay on. Set `SHOPKEEPRR_TRACKING_ENABLED=false` to opt out; the disclosure is in the README and starter Privacy Policy.
- **Settings → Checkout → Cancel unpaid orders after** controls the cancellation window. **Keep abandoned orders on the order screen for (days)** controls how long unpaid orders remain visible; paid orders are always listed.
- Back up the database and `public/uploads/` on a schedule.
- Remove demo users, demo products and the installer lock before handing the store to a client.
- Review the privacy policy and terms pages against the data you actually collect.

---

## Tests

The test suite ships with the release archive, so buyers and reviewers can run it too:

```bash
php artisan test
```

Coverage includes the offline storefront screen, all four theme presets and admin theme activation/validation, checkout and payment flows (manual invoice, Stripe, PayPal, subscription activation, refunds), webhook rejection, B2B guest rules, the module trust confirmation, install tracking and registration, inventory integrity (including the last-unit checkout race), version constraints, signed-manifest verification, module compatibility warnings and maintenance-mode routing. Multi-vendor coverage exercises percentage, fixed and combined commission, discount allocation, seller suspension, portal access isolation, Stripe Connect onboarding and transfers, PayPal Payouts batches and refund-driven transfer reversals. The seller-side upgrade harness (`tools/test-upgrade.php`) additionally installs a populated previous-release store, applies a signed update, forces a migration failure, verifies automatic rollback and then manually rolls back while checking every order, customer, setting, theme, module and upload.

The `nexus` compatibility key is still accepted for modules published before the ShopKeeprr rebrand; new packages should use `shopkeeprr`.

---

## Packaging for a marketplace listing

Do not ship `.env`, `node_modules`, local SQLite databases, logs, generated caches or demo credentials. A clean release contains:

```
app/  bootstrap/  config/  database/  public/  resources/  routes/  storage/  tests/  vendor/
artisan  composer.json  composer.lock  package.json  package-lock.json  phpunit.xml  README.md  .env.example
documentation/
```

Build assets with `npm run build` before zipping. The included `public/build` and `public/uploads/demo` images make the demo store work out of the box. The buyer archive includes `LICENSE.txt` (the commercial Codester Regular/Extended license terms); the Laravel framework itself remains MIT licensed. `tools/package-nexuscart.php --with-docs` builds the buyer archive, including `tests/` and `phpunit.xml`, and excludes local databases, logs, caches and credentials.

---

## Structure

```
app/Console/Commands       shopkeeprr:install and other CLI commands
app/Http/Controllers/Admin Admin screens and actions
app/Http/Controllers/Storefront Catalog, cart, checkout, account
app/Services               Cart, orders, payments, subscriptions, themes, modules
app/Services/Updates       Manifest, signature, backup, apply, verify and rollback
UPDATING.md                Buyer-facing update, backup and recovery guide
config/themes.php          Theme presets and palettes
resources/views/theme      Shared storefront templates
resources/views/theme/presets/{key}  Theme-specific overrides
resources/views/admin      Admin dashboard
routes/storefront.php      Public routes
routes/admin.php           Authenticated admin routes
routes/webhooks.php        Stripe and PayPal webhooks
```
