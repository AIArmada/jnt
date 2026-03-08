<?php

declare(strict_types=1);

$tablePrefix = env('JNT_TABLE_PREFIX', 'jnt_');

$tables = [
    'orders' => env('JNT_ORDERS_TABLE', $tablePrefix . 'orders'),
    'order_items' => env('JNT_ORDER_ITEMS_TABLE', $tablePrefix . 'order_items'),
    'order_parcels' => env('JNT_ORDER_PARCELS_TABLE', $tablePrefix . 'order_parcels'),
    'tracking_events' => env('JNT_TRACKING_EVENTS_TABLE', $tablePrefix . 'tracking_events'),
    'webhook_logs' => env('JNT_WEBHOOK_LOGS_TABLE', $tablePrefix . 'webhook_logs'),
];

return [
    /*
    |--------------------------------------------------------------------------
    | Database
    |--------------------------------------------------------------------------
    */
    'database' => [
        'table_prefix' => $tablePrefix,
        'json_column_type' => env('JNT_JSON_COLUMN_TYPE', env('COMMERCE_JSON_COLUMN_TYPE', 'json')),
        'tables' => $tables,
    ],

    /*
    |--------------------------------------------------------------------------
    | Credentials & API
    |--------------------------------------------------------------------------
    */
    'environment' => env('JNT_ENVIRONMENT', 'testing'),
    'api_account' => env('JNT_API_ACCOUNT'),
    'private_key' => env('JNT_PRIVATE_KEY'),
    'customer_code' => env('JNT_CUSTOMER_CODE'),
    'password' => env('JNT_PASSWORD'),
    'base_urls' => [
        'testing' => env('JNT_BASE_URL_TESTING', 'https://demoopenapi.jtexpress.my/webopenplatformapi'),
        'production' => env('JNT_BASE_URL_PRODUCTION', 'https://ylopenapi.jtexpress.my/webopenplatformapi'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Features
    |--------------------------------------------------------------------------
    |
    | When enabled, orders are automatically scoped to the current owner.
    */
    'owner' => [
        'enabled' => env('JNT_OWNER_ENABLED', false),
        'include_global' => env('JNT_OWNER_INCLUDE_GLOBAL', false),
        'auto_assign_on_create' => env('JNT_OWNER_AUTO_ASSIGN', true),
    ],

    'notifications' => [
        'enabled' => env('JNT_NOTIFICATIONS_ENABLED', true),
        'queue' => env('JNT_NOTIFICATIONS_QUEUE', true),
        'support_contact' => env('JNT_SUPPORT_CONTACT'),
    ],

    'shipping' => [
        'origin' => [
            'name' => env('JNT_ORIGIN_NAME'),
            'phone' => env('JNT_ORIGIN_PHONE'),
            'address' => env('JNT_ORIGIN_ADDRESS'),
            'post_code' => env('JNT_ORIGIN_POSTCODE'),
            'country_code' => env('JNT_ORIGIN_COUNTRY', 'MYS'),
            'state' => env('JNT_ORIGIN_STATE'),
            'city' => env('JNT_ORIGIN_CITY'),
        ],

        'base_rate' => env('JNT_SHIPPING_BASE_RATE', 800),
        'per_kg_rate' => env('JNT_SHIPPING_PER_KG_RATE', 200),
        'min_charge' => env('JNT_SHIPPING_MIN_CHARGE', 800),

        'default_estimated_days' => env('JNT_ESTIMATED_DAYS', 3),
        'east_malaysia_extra_days' => env('JNT_EAST_MALAYSIA_EXTRA_DAYS', 2),

        'default_service_name' => env('JNT_SERVICE_NAME', 'J&T Express'),
        'default_service_type' => env('JNT_SERVICE_TYPE', 'EZ'),

        'region_multipliers' => [
            'sabah' => 1.5,
            'sarawak' => 1.5,
            'labuan' => 1.5,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Integrations
    |--------------------------------------------------------------------------
    */
    'cart' => [
        'register_manager_proxy' => env('JNT_CART_REGISTER_PROXY', true),
        'quote_ttl_minutes' => env('JNT_QUOTE_TTL', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | HTTP
    |--------------------------------------------------------------------------
    */
    'http' => [
        'timeout' => env('JNT_HTTP_TIMEOUT', 30),
        'connect_timeout' => env('JNT_HTTP_CONNECT_TIMEOUT', 10),
        'retry_times' => env('JNT_HTTP_RETRY_TIMES', 3),
        'retry_sleep' => env('JNT_HTTP_RETRY_SLEEP', 1000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhooks
    |--------------------------------------------------------------------------
    */
    'webhook' => [
        'url' => env('JNT_WEBHOOK_URL'),
    ],

    'webhooks' => [
        'enabled' => env('JNT_WEBHOOKS_ENABLED', true),
        'route' => env('JNT_WEBHOOK_ROUTE', 'webhooks/jnt/status'),
        'middleware' => ['api'],
        'log_payloads' => env('JNT_WEBHOOK_LOG_PAYLOADS', false),
        'verify_signature' => env('JNT_WEBHOOKS_VERIFY_SIGNATURE', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    */
    'logging' => [
        'enabled' => env('JNT_LOGGING_ENABLED', true),
        'channel' => env('JNT_LOGGING_CHANNEL', 'stack'),
        'level' => env('JNT_LOGGING_LEVEL', 'info'),
    ],
];
