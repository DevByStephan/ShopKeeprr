# Themes

ShopKeeprr includes four production storefront themes. Activate one in **Admin → Theme**. The active theme applies a curated palette and typography; products, orders, customers and saved overrides are untouched.

| Theme | Positioning | Palette |
| --- | --- | --- |
| Editorial | Boutique, typography-led catalogs | Cream, deep teal, amber |
| Marketplace | Dense, search-first high-SKU stores | Navy, amber, yellow CTAs, blue links |
| Auction House | Listings, deals and discovery | White, marketplace blue, yellow/green accents |
| Global Bazaar | Promotion-led discount retail | White, signal red, warm orange |

## What a theme includes

Each preset can provide its own header, footer, homepage and product card, plus responsive navigation and account menus. Shared pages inherit the active theme's colour tokens and typography.

The palette is split into semantic tokens such as `--brand`, `--link`, `--surface`, `--line`, `--price` and `--sale`. Use the tokens rather than hard-coded colours so every commerce screen stays legible.

## Template resolution

For each storefront view, the first match wins:

1. An administrator override in `resources/views/theme/custom/{view}.blade.php`.
2. A preset override in `resources/views/theme/presets/{preset}/{view}.blade.php`.
3. The shared default in `resources/views/theme/{view}.blade.php`.

The active theme is resolved by the storefront's theme service. CSS custom properties are printed by the theme layout.

## Adding a preset theme

1. Copy an existing preset folder under `resources/views/theme/presets/` and give it a unique key.
2. Add an entry to `config/themes.php` with the label, palette, defaults and preview swatches.
3. Override only the views the theme needs: `home`, `partials.header`, `partials.footer`, `partials.product-card`, `shop.index`, and so on.
4. Run the front-end build if you changed CSS or JavaScript.
5. Activate the theme in **Admin → Theme** and test the full purchase flow.

A theme should change presentation, not checkout logic. Preserve the commerce markup, form fields, accessibility behaviour and account actions.

## Template editor

**Admin → Theme → Templates** edits a copy of the shared templates under `resources/views/theme/custom`. Blade is compiled before saving, so syntax errors are rejected. Backups are written to `storage/app/theme-backups`. **Reset to default** removes the override.

## Packaging a theme

A marketplace theme package should contain:

```text
theme.json
views/
  home.blade.php
  product/show.blade.php
  partials/header.blade.php
  partials/footer.blade.php
  partials/product-card.blade.php
README.md
```

`theme.json` is metadata for the listing and review process. The installed storefront still activates the theme through `config/themes.php` and the preset view folder. The [Atelier Example theme](../examples/theme/atelier-example/README.md) shows the layout.

Do not include compiled assets, `node_modules`, customer data, license keys or `.env` files. Version the package semantically and include setup notes in the release notes.

## Theme review

Themes receive a manual review because a template mistake can expose customer-facing regressions. Test:

- Homepage, shop, product, cart and checkout.
- Mobile navigation and account menus.
- Empty catalog, out-of-stock products and long titles.
- Keyboard focus and reduced-motion preferences.
- The four store models if the theme is sold for all of them.
