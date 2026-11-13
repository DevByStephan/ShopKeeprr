<?php

return [

    /*
    |--------------------------------------------------------------------------
    | ShopKeeprr Store
    |--------------------------------------------------------------------------
    |
    | The Store is the source of truth for marketplace accounts, purchases,
    | releases and download entitlements. Keep this URL fixed in configuration:
    | never accept a Store URL from a request, or the admin marketplace becomes
    | an SSRF and malicious-package vector.
    |
    */

    'store_url' => rtrim((string) shopkeeprr_env('STORE_URL', 'https://store.shopkeeprr.com'), '/'),

    /*
    | Local development only. The Store itself still decides whether it will
    | issue a link for a localhost site.
    */
    'allow_http' => (bool) shopkeeprr_env('STORE_ALLOW_HTTP', false),

    'timeout' => (int) shopkeeprr_env('STORE_TIMEOUT', 20),

    'catalog_ttl' => (int) shopkeeprr_env('STORE_CATALOG_TTL', 300),

    'max_package_mb' => (int) shopkeeprr_env('STORE_MAX_PACKAGE_MB', 20),

    /*
    | The platform identifies itself when starting a link. The Store shows this
    | version to the account holder before they approve the site.
    */
    'user_agent' => 'ShopKeeprr/'.(string) shopkeeprr_env('VERSION', '1.1.0'),

];
