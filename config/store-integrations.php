<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Store Platform
    |--------------------------------------------------------------------------
    |
    | This option controls the default store platform that will be used when
    | no specific platform is requested. Currently supported: "shopify"
    |
    */

    'default' => env('STORE_INTEGRATION_DEFAULT', 'shopify'),

    /*
    |--------------------------------------------------------------------------
    | Sync Settings
    |--------------------------------------------------------------------------
    |
    | Configure how often store connections are synced and other sync-related
    | settings that apply across all platforms.
    |
    */

    'sync' => [
        'interval_minutes' => env('STORE_SYNC_INTERVAL', 15),
        'batch_size' => env('STORE_SYNC_BATCH_SIZE', 100),
        'timeout_seconds' => env('STORE_SYNC_TIMEOUT', 300),
        'max_retries' => env('STORE_SYNC_MAX_RETRIES', 3),
    ],

    /*
    |--------------------------------------------------------------------------
    | Platform Configurations
    |--------------------------------------------------------------------------
    |
    | Here you may configure each store platform that your application supports.
    | Each platform has its own OAuth credentials and API settings.
    |
    */

    'platforms' => [

        'shopify' => [
            'client_id' => env('SHOPIFY_CLIENT_ID'),
            'client_secret' => env('SHOPIFY_CLIENT_SECRET'),
            'api_version' => env('SHOPIFY_API_VERSION', '2025-10'),
            'scopes' => [
                'read_products',
                'read_inventory',
            ],
        ],

        // Future platforms can be added here:
        // 'woocommerce' => [
        //     'consumer_key' => env('WOOCOMMERCE_CONSUMER_KEY'),
        //     'consumer_secret' => env('WOOCOMMERCE_CONSUMER_SECRET'),
        // ],
        //
        // 'bigcommerce' => [
        //     'client_id' => env('BIGCOMMERCE_CLIENT_ID'),
        //     'client_secret' => env('BIGCOMMERCE_CLIENT_SECRET'),
        // ],

    ],

];
