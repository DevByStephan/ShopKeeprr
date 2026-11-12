<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Installation tracking
    |--------------------------------------------------------------------------
    |
    | Tracking is off by default. When enabled, ShopKeeprr can register each
    | installation with the official Store and send a daily heartbeat. The
    | payload contains the site URL, a pseudonymous install ID, version, store
    | mode, theme, PHP/database details and the administrator email entered
    | during setup. It never contains customers, orders, payment data or
    | product data. The install ID is not an anonymity guarantee because the
    | administrator email is part of the record.
    |
    */

    'enabled' => (bool) shopkeeprr_env('TRACKING_ENABLED', false),

    'endpoint' => rtrim((string) shopkeeprr_env(
        'TRACKING_URL',
        'https://store.shopkeeprr.com/api/v1/installs'
    ), '/'),

    'timeout' => (int) shopkeeprr_env('TRACKING_TIMEOUT', 8),

    'file' => storage_path('app/install-tracking.json'),
];
