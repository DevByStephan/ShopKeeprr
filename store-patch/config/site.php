<?php

return [

    'name' => env('APP_NAME', 'ShopKeeprr'),

    'tagline' => env('SITE_TAGLINE', 'Your Store. Your Code. Your Rules.'),

    'author' => 'Stephan Johnson (SJDesigns)',

    'support_email' => env('SITE_SUPPORT_EMAIL', 'info@shopkeeprr.com'),

    'currency' => env('SITE_CURRENCY', 'USD'),

    // PHP CLI binary used in the crontab example on Admin -> Crons.
    // Set CRON_PHP_BINARY when the host does not expose `php` on PATH.
    'cron_php' => env('CRON_PHP_BINARY', 'php'),

    'copyright_year' => 2026,

    // Current ShopKeeprr release. Marketplace packages are checked against this.
    'shopkeeprr_version' => shopkeeprr_env('VERSION', '1.1.0'),

    // Legacy alias kept for signed packages and older deployments.
    'nexuscart_version' => shopkeeprr_env('VERSION', '1.1.0'),

    'demo_url' => env('SITE_DEMO_URL', ''),

    'docs_url' => env('SITE_DOCS_URL', ''),

    'social' => [
        'x' => env('SITE_SOCIAL_X', ''),
        'github' => env('SITE_SOCIAL_GITHUB', 'https://github.com/DevByStephan/ShopKeeprr'),
        'youtube' => env('SITE_SOCIAL_YOUTUBE', ''),
    ],

    'mail' => [
        // Order receipts are best-effort: a broken mail server must never
        // block a paid order or a license.
        'send_receipts' => (bool) env('SITE_SEND_RECEIPTS', true),
    ],

];
