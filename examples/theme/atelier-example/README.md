# Atelier Example theme

A small preset-style theme that demonstrates a warm editorial palette and a homepage override. It uses the core layout and product-card partial, so checkout and commerce markup stay untouched.

## Try it on staging

1. Copy `views/home.blade.php` to `resources/views/theme/presets/atelier-example/home.blade.php`.
2. Add the preset to `config/themes.php`.
3. Run the front-end build if you changed CSS or JavaScript.
4. Activate **Atelier Example** in **Admin → Theme**.
5. Test the homepage, shop, product, cart, checkout and account pages.

The `theme.json` file is marketplace metadata for the listing and review process. The installed application activates presets through `config/themes.php`, not by reading `theme.json` at runtime.

## Preset entry

```php
'atelier-example' => [
    'label' => 'Atelier Example',
    'tagline' => 'Editorial & warm',
    'description' => 'A typography-led storefront for curated catalogs.',
    'best_for' => 'Lifestyle stores and small-batch brands',
    'defaults' => [
        'primary_color' => '#0f766e',
        'accent_color' => '#d97706',
        'heading_font' => 'Fraunces',
        'body_font' => 'Inter',
        'product_columns' => 3,
    ],
    'palette' => [
        'brand' => '#0f766e',
        'link' => '#0f766e',
        'accent' => '#d97706',
        'ink' => '#17231f',
        'surface' => '#f6f3ec',
        'surface-2' => '#eef2ef',
        'card' => '#ffffff',
        'line' => '#e3e9e5',
        'price' => '#17231f',
        'sale' => '#9a3412',
    ],
    'card_radius' => '1rem',
    'button_radius' => '999px',
    'preview' => ['#f6f3ec', '#102522', '#0f766e', '#d97706'],
],
```

## Notes

- Do not change checkout logic in a theme.
- Keep the product-card partial and form markup intact unless you have tested the complete purchase flow.
- Do not include `node_modules`, compiled assets, customer data, license keys or `.env` files.
- This is a presentation template, not a release ZIP.
