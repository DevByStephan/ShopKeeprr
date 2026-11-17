# ShopKeeprr 1.1.2 — WooCommerce tax migration and Freeware

Released 16 November 2026.

ShopKeeprr 1.1.2 extends the 1.1 WooCommerce migration tool with tax rates and the tax options ShopKeeprr can represent. It is a patch release on the 1.1 line and introduces no database schema changes beyond 1.1.1.

## What is included

- **WooCommerce tax rates** are detected from the standard tax-rate CSV export and imported from the REST API.
- The storefront and admin **Powered by ShopKeeprr** attribution now links to [shopkeeprr.com](https://shopkeeprr.com/).
- Rates are matched by country, state and name, so re-running the migration updates the same rate instead of creating duplicates.
- The API migration reads `prices_include_tax` and `shipping_tax_class` and maps them to ShopKeeprr's **prices include tax** and **shipping taxable** settings.
- Dry runs report tax counts and supported tax options without writing either.
- The migration summary shows tax rates separately from products, customers and orders.

## Deliberate limits

ShopKeeprr uses a simpler tax model than WooCommerce. The migration reports these instead of importing data that would calculate the wrong tax:

- Rates limited to a postcode or city are skipped because ShopKeeprr rates apply to a whole country or state.
- Compound rates are skipped.
- Additional WooCommerce tax classes (for example reduced-rate or zero-rate) are skipped.
- When a location has more than one standard rate, all are imported but ShopKeeprr applies the highest-priority rate. The migration warns so the merchant can review combined state and local taxes.
- WooCommerce display, rounding and tax-address-base options are reported as unsupported where they differ from ShopKeeprr.

CSV imports bring in tax rates. The optional read-only API connection also brings in the supported tax options.

## Freeware positioning

The website now describes ShopKeeprr as **E-Commerce's New Freeware Innovation**. This is a distribution label: the core is free to download, run and modify, and the source is included. The licence still restricts redistribution and resale, so the site does not describe ShopKeeprr as OSI open source. The commitment page keeps the full explanation.

## Package refresh

The 1.1.2 package was rebuilt on 17 November 2026 to link the storefront and admin attribution to shopkeeprr.com. The release remains 1.1.2; use the current checksum published on the download site.

## Upgrade from 1.1.1

No new migration is required. Replace the application files while keeping `.env`, `storage/`, `modules/`, `public/uploads/`, custom theme files and `database/database.sqlite` (if SQLite), then run:

```bash
php artisan optimize:clear
```

If you are upgrading from 1.1.0 or earlier, follow the 1.1.1 upgrade steps and run `php artisan migrate --force` first.

## Verification

- WooCommerce migration feature suite: 9 tests, 171 assertions.
- All changed PHP files pass `php -l`.
- The release ZIP is checked with `unzip -t` and a fresh extraction before publication.
