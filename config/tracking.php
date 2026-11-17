<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Installation tracking
    |--------------------------------------------------------------------------
    |
    | Tracking is on by default. The installer discloses what is sent and lets
    | the store owner opt out. When enabled, ShopKeeprr registers each
    | installation with the official Store and sends a daily heartbeat. The
    | payload contains the site URL, a pseudonymous install ID, version, store
    | mode, theme, PHP/database details and the administrator email entered
    | during setup. It never contains customers, orders, payment data or
    | product data. The install ID is not an anonymity guarantee because the
    | administrator email is part of the record. Set
    | SHOPKEEPRR_TRACKING_ENABLED=false to stop registration and heartbeats.
    |
    */

    'enabled' => (bool) shopkeeprr_env('TRACKING_ENABLED', true),

    'endpoint' => rtrim((string) shopkeeprr_env(
        'TRACKING_URL',
        'https://store.shopkeeprr.com/api/v1/installs'
    ), '/'),

    'timeout' => (int) shopkeeprr_env('TRACKING_TIMEOUT', 8),

    'file' => storage_path('app/install-tracking.json'),
];
