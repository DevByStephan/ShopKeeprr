<?php

/*
| A demo build ships a DEMO_BUILD marker in the application root. The marker
| keeps demo mode on even when the installer writes a fresh .env file. Set
| SHOPKEEPRR_DEMO_MODE=false to override it on a normal installation. The
| historical NEXUSCART_* names remain supported for existing deployments.
*/
$demoMarker = __DIR__.'/../DEMO_BUILD';
$demoLabel = is_file($demoMarker) ? trim((string) file_get_contents($demoMarker)) : '';

return [

    /*
    |--------------------------------------------------------------------------
    | ShopKeeprr Version
    |--------------------------------------------------------------------------
    |
    | This is the version of the application code on disk. The updater compares
    | it with the signed release manifest. Do not edit it by hand on a live
    | store; releases change it for you.
    |
    */

    'version' => shopkeeprr_env('VERSION', '1.1.0'),

    /*
    |--------------------------------------------------------------------------
    | Multi-vendor defaults
    |--------------------------------------------------------------------------
    |
    | These values are used when a vendor does not override the platform
    | commission and when a new vendor is created. Merchants can change every
    | value from Settings → Marketplace; the settings table wins at runtime.
    |
    */

    'vendors' => [
        'commission_type' => shopkeeprr_env('COMMISSION_TYPE', 'percentage'),
        'commission_percent' => (float) shopkeeprr_env('COMMISSION_PERCENT', 10),
        'commission_fixed' => (float) shopkeeprr_env('COMMISSION_FIXED', 0),
        'payout_hold_days' => (int) shopkeeprr_env('PAYOUT_HOLD_DAYS', 7),
        'minimum_payout' => (float) shopkeeprr_env('MINIMUM_PAYOUT', 25),
    ],

    /*
    |--------------------------------------------------------------------------
    | Module API
    |--------------------------------------------------------------------------
    |
    | Modules declare which module API they were built against. Bump this only
    | when an update intentionally changes the module contract.
    |
    */

    'module_api' => shopkeeprr_env('MODULE_API', '1.0'),

    /*
    |--------------------------------------------------------------------------
    | Demo build
    |--------------------------------------------------------------------------
    |
    | A public demo keeps the Stripe and PayPal settings visible but locked,
    | so visitors can inspect the configuration without changing the keys
    | behind the demo checkout. Invoices stay editable for testing.
    |
    */

    'demo' => [
        'enabled' => (bool) shopkeeprr_env('DEMO_MODE', $demoLabel !== ''),
        'label' => shopkeeprr_env('DEMO_LABEL', $demoLabel !== '' ? $demoLabel : 'Demo build'),

        /*
         | Public demo credentials. These are intentionally visible on the
         | demo landing page and the admin login screen. They are only used
         | when demo mode is enabled; a normal installation never exposes
         | them. Change them in .env if this demo is reused elsewhere.
         */
        'admin_email' => shopkeeprr_env('DEMO_ADMIN_EMAIL', 'admin@shopkeeprr.test'),
        'admin_password' => shopkeeprr_env('DEMO_ADMIN_PASSWORD', 'ShopKeeprr2026!'),
        'shopper_email' => shopkeeprr_env('DEMO_SHOPPER_EMAIL', 'maya@example.com'),
        'shopper_password' => shopkeeprr_env('DEMO_SHOPPER_PASSWORD', 'ShopKeeprrShopper2026!'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Update channel
    |--------------------------------------------------------------------------
    |
    | The manifest is a small JSON document hosted by the vendor. Each release
    | carries a SHA-256 hash and an RSA-SHA256 signature that this application
    | verifies before a single file is downloaded or replaced.
    |
    | SHOPKEEPRR_UPDATE_URL must point at the vendor's manifest. HTTPS is
    | required except for loopback development. The historical NEXUSCART_
    | prefix is still accepted.
    |
    */

    'updates' => [
        'enabled' => (bool) shopkeeprr_env('UPDATES_ENABLED', true),

        'channel' => shopkeeprr_env('UPDATE_CHANNEL', 'stable'),

        'manifest_url' => shopkeeprr_env(
            'UPDATE_URL',
            'https://updates.shopkeeprr.com/manifest.json'
        ),

        /*
         | Base64-encoded SubjectPublicKeyInfo (DER) for the RSA public key.
         | The matching private key is held by the vendor, never shipped.
         */
        'public_key' => shopkeeprr_env('UPDATE_PUBLIC_KEY', 'MIIBojANBgkqhkiG9w0BAQEFAAOCAY8AMIIBigKCAYEAoygX9UwePQMJNRIMeCMLeZyjtE/HHty6RjfAU/OSo09a7z7S7AMzphZQ7wiJKaT2VJMDUZrdI+F2Go1QSM+VXV38tO4N/HA2LhMZakeIlnTqsKP11EcL+8pfYkJYGBUSVXx6IejOR4gSEpx0/PPDwlqPdYcQ21S/ZjDMwW9pKjRDpmzRynx+HdshZuh7vEIwyagZQHojttJdHwhkwNL7vTY8VBDaYf+9TPZEq0EiHSb1cQjMHgEMnfM52VOQttqOzQDIX+A+F0g8RHIpH3HQwNuJ0qDQiPG9Du4hwwtFfTP578SokVur+hwYtnvo2jBNKhak/2cp8RC/zj9S/H6aE7E19o8aA+l0FwpOpuLrVtQaKsq/tYPGOf9OSKc09Xk65+MuEaHZdO0N4UfQfCuK5uiIMMVIPToKnOQO+3k224+V3mWOgP21ZF+sltuFq72aU9yKDJiSzWNTSBXEkB8q6gvgn1uqGmGMj0cTaYczFBphEIhpCQSuhwSzxgdTn8rFAgMBAAE='),

        'timeout' => (int) shopkeeprr_env('UPDATE_TIMEOUT', 20),

        'download_timeout' => (int) shopkeeprr_env('UPDATE_DOWNLOAD_TIMEOUT', 600),

        'max_package_mb' => (int) shopkeeprr_env('UPDATE_MAX_MB', 150),

        'backup_keep' => (int) shopkeeprr_env('UPDATE_BACKUP_KEEP', 5),

        /*
         | Path to the PHP CLI binary used to run migrations in a clean process.
         | Leave empty to auto-detect; the updater falls back to running the
         | migrator in-process when no CLI binary can be executed.
         */
        'php_binary' => shopkeeprr_env('PHP_BINARY'),

        /*
         | Intended for local development and the upgrade test only. Never
         | enable this on a customer store.
         */
        'allow_http' => (bool) shopkeeprr_env('UPDATE_ALLOW_HTTP', false),

        /*
         | The filesystem root the updater operates on. Defaults to the app.
         | Exposed so the automated upgrade test can target a disposable copy.
         */
        'root' => shopkeeprr_env('UPDATE_ROOT'),
    ],

];
