<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Essential Credentials
    |--------------------------------------------------------------------------
    |
    | Only these are required. Everything else has sensible defaults.
    |
    | Testing credentials are provided by J&T Express for sandbox testing.
    | For production, you must provide your own credentials.
    |
    */
    'environment' => env('JNT_ENVIRONMENT', 'local'),

    // Testing environment uses J&T's official public credentials by default
    'api_account' => env('JNT_API_ACCOUNT',
        in_array(env('JNT_ENVIRONMENT', 'local'), ['local', 'testing', 'development'])
            ? '640826271705595946'  // J&T official testing account
            : null
    ),
    'private_key' => env('JNT_PRIVATE_KEY',
        in_array(env('JNT_ENVIRONMENT', 'local'), ['local', 'testing', 'development'])
            ? '8e88c8477d4e4939859c560192fcafbc'  // J&T official testing key
            : null
    ),

    'customer_code' => env('JNT_CUSTOMER_CODE'),
    'password' => env('JNT_PASSWORD'),

    /*
    |--------------------------------------------------------------------------
    | Advanced Configuration (Optional)
    |--------------------------------------------------------------------------
    |
    | Override defaults only if needed. Most users can ignore this section.
    |
    */
    'base_urls' => [
        'testing' => env('JNT_BASE_URL_TESTING', 'https://demoopenapi.jtexpress.my/webopenplatformapi'),
        'production' => env('JNT_BASE_URL_PRODUCTION', 'https://ylopenapi.jtexpress.my/webopenplatformapi'),
    ],

    'http' => [
        'timeout' => env('JNT_HTTP_TIMEOUT', 30),
        'connect_timeout' => env('JNT_HTTP_CONNECT_TIMEOUT', 10),
        'retry_times' => env('JNT_HTTP_RETRY_TIMES', 3),
        'retry_sleep' => env('JNT_HTTP_RETRY_SLEEP', 1000), // milliseconds
    ],

    'logging' => [
        'enabled' => env('JNT_LOGGING_ENABLED', true),
        'channel' => env('JNT_LOGGING_CHANNEL', 'stack'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhook Configuration
    |--------------------------------------------------------------------------
    |
    | Configure webhook endpoint for receiving tracking status updates from
    | J&T Express. The signature verification ensures webhooks are authentic.
    |
    */
    'webhooks' => [
        'enabled' => env('JNT_WEBHOOKS_ENABLED', true),
        'route' => env('JNT_WEBHOOK_ROUTE', 'webhooks/jnt/status'),
        'middleware' => ['api', 'jnt.verify.signature'],
        'log_payloads' => env('JNT_WEBHOOK_LOG_PAYLOADS', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Tables
    |--------------------------------------------------------------------------
    |
    | Configure the database tables used by the J&T package. Override any of
    | these values if the defaults conflict with existing schema naming.
    |
    */
    'database' => [
        'table_prefix' => env('JNT_TABLE_PREFIX', 'jnt_'),
        'tables' => [
            'orders' => env('JNT_ORDERS_TABLE', env('JNT_TABLE_PREFIX', 'jnt_').'orders'),
            'order_items' => env('JNT_ORDER_ITEMS_TABLE', env('JNT_TABLE_PREFIX', 'jnt_').'order_items'),
            'order_parcels' => env('JNT_ORDER_PARCELS_TABLE', env('JNT_TABLE_PREFIX', 'jnt_').'order_parcels'),
            'tracking_events' => env('JNT_TRACKING_EVENTS_TABLE', env('JNT_TABLE_PREFIX', 'jnt_').'tracking_events'),
            'webhook_logs' => env('JNT_WEBHOOK_LOGS_TABLE', env('JNT_TABLE_PREFIX', 'jnt_').'webhook_logs'),
        ],
    ],
];
