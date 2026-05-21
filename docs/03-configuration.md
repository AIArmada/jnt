---
title: Configuration
---

# Configuration

The package configuration is located at `config/jnt.php`. This page documents all available options.

## Database

Configure table names and prefixes:

```php
'database' => [
    // Table name prefix (default: jnt_)
    'table_prefix' => 'jnt_',
    
    // Override individual table names
    'tables' => [
        'orders' => null,         // Uses: jnt_orders
        'order_items' => null,    // Uses: jnt_order_items
        'order_parcels' => null,  // Uses: jnt_order_parcels
        'tracking_events' => null, // Uses: jnt_tracking_events
        'webhook_logs' => null,   // Uses: jnt_webhook_logs
    ],
    
    // JSON column type for database compatibility
    'json_column_type' => 'json',
],
```

### Custom Table Names

To use custom table names:

```php
'database' => [
    'table_prefix' => '',
    'tables' => [
        'orders' => 'shipping_orders',
        'order_items' => 'shipping_items',
        'tracking_events' => 'shipping_events',
    ],
],
```

## API Credentials

```php
'api_account' => env('JNT_API_ACCOUNT'),
'private_key' => env('JNT_PRIVATE_KEY'),
'customer_code' => env('JNT_CUSTOMER_CODE'),
'password' => env('JNT_PASSWORD'),

'environment' => env('JNT_ENVIRONMENT', 'testing'),

'base_urls' => [
    'testing' => 'https://uat-openapi.jtexpress.my/openplatformweb',
    'production' => 'https://openapi.jtexpress.my/openplatformweb',
],
```

### Environment Options

| Value | Description |
|-------|-------------|
| `testing` | Use sandbox API (UAT) |
| `production` | Use production API |
| `local` | Alias for testing |
| `development` | Alias for testing |

## Default Order Values

```php
'defaults' => [
    // Default express type for new orders
    'express_type' => \AIArmada\Jnt\Enums\ExpressType::DOMESTIC,
    
    // Default service type
    'service_type' => \AIArmada\Jnt\Enums\ServiceType::DOOR_TO_DOOR,
    
    // Default payment type
    'payment_type' => \AIArmada\Jnt\Enums\PaymentType::PREPAID_POSTPAID,
    
    // Default goods type
    'goods_type' => \AIArmada\Jnt\Enums\GoodsType::PACKAGE,
    
    // Country code for addresses
    'country_code' => 'MYS',
],
```

## Features

Toggle package features:

```php
'features' => [
    // Enable built-in notifications
    'notifications' => true,
    
    // Store orders in database
    'persist_orders' => true,
    
    // Log tracking updates
    'log_tracking' => true,
],
```

## HTTP Client

Configure API request behavior:

```php
'http' => [
    // Request timeout in seconds
    'timeout' => 30,
    
    // Connection timeout
    'connect_timeout' => 10,
    
    // Number of retry attempts
    'retry_times' => 3,
    
    // Delay between retries (milliseconds)
    'retry_sleep' => 1000,
],
```

### Retry Behavior

Requests are automatically retried on:
- Connection failures
- Server errors (5xx)
- Timeout errors

## Webhooks

Configure webhook handling:

```php
'webhooks' => [
    // Enable/disable webhook processing
    'enabled' => env('JNT_WEBHOOKS_ENABLED', true),
    
    // Webhook route path
    'route' => 'webhooks/jnt/status',
    
    // Route middleware
    'middleware' => ['api'],
    
    // Verify signature on incoming webhooks
    'verify_signature' => env('JNT_WEBHOOKS_VERIFY_SIGNATURE', true),
    
    // Log full webhook payloads (debugging only)
    'log_payloads' => env('JNT_WEBHOOK_LOG_PAYLOADS', false),
],
```

### Signature Verification

In production, always enable signature verification. To temporarily disable for debugging:

```env
JNT_WEBHOOKS_VERIFY_SIGNATURE=false
```

## Owner Scoping (Multi-tenancy)

Configure owner-based data isolation:

```php
'owner' => [
    // Enable multi-tenant scoping
    'enabled' => env('JNT_OWNER_ENABLED', false),
    
    // Include global (null owner) records in queries
    'include_global' => env('JNT_OWNER_INCLUDE_GLOBAL', false),
    
    // Auto-assign owner on record creation
    'auto_assign_on_create' => env('JNT_OWNER_AUTO_ASSIGN', true),
],
```

See [Multi-tenancy](09-multitenancy.md) for detailed usage.

## Logging

Configure logging behavior:

```php
'logging' => [
    // Enable/disable API logging
    'enabled' => true,
    
    // Log channel
    'channel' => 'stack',
    
    // Log level
    'level' => 'info',
],
```

### Sensitive Data Masking

The package automatically masks sensitive data in logs:
- Passwords and private keys
- Phone numbers (shows last 2 digits)
- Email addresses
- Physical addresses

## Shipping Rates

Configure local rate calculation:

```php
'shipping' => [
    // Origin address for shipments
    'origin' => [
        'name' => 'Your Store',
        'phone' => '60123456789',
        'address' => '123 Store Address',
        'post_code' => '50000',
        'city' => 'Kuala Lumpur',
        'state' => 'Kuala Lumpur',
        'country_code' => 'MYS',
    ],
    
    // Base shipping rate (in cents)
    'base_rate' => 800,
    
    // Rate per additional kg (in cents)
    'per_kg_rate' => 200,
    
    // Minimum shipping charge (in cents)
    'min_charge' => 800,
    
    // Default estimated days
    'default_estimated_days' => 3,
    
    // Extra days for East Malaysia
    'east_malaysia_extra_days' => 2,
    
    // Region multipliers
    'region_multipliers' => [
        'sabah' => 1.5,
        'sarawak' => 1.5,
        'labuan' => 1.5,
    ],
    
    // Default service details
    'default_service_name' => 'J&T Express',
    'default_service_type' => 'EZ',
],
```

## Complete Configuration Example

```php
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Database
    |--------------------------------------------------------------------------
    */
    'database' => [
        'table_prefix' => 'jnt_',
        'tables' => [],
        'json_column_type' => 'json',
    ],

    /*
    |--------------------------------------------------------------------------
    | API Credentials
    |--------------------------------------------------------------------------
    */
    'api_account' => env('JNT_API_ACCOUNT'),
    'private_key' => env('JNT_PRIVATE_KEY'),
    'customer_code' => env('JNT_CUSTOMER_CODE'),
    'password' => env('JNT_PASSWORD'),
    'environment' => env('JNT_ENVIRONMENT', 'testing'),
    
    'base_urls' => [
        'testing' => 'https://uat-openapi.jtexpress.my/openplatformweb',
        'production' => 'https://openapi.jtexpress.my/openplatformweb',
    ],

    /*
    |--------------------------------------------------------------------------
    | Defaults
    |--------------------------------------------------------------------------
    */
    'defaults' => [
        'express_type' => \AIArmada\Jnt\Enums\ExpressType::DOMESTIC,
        'service_type' => \AIArmada\Jnt\Enums\ServiceType::DOOR_TO_DOOR,
        'payment_type' => \AIArmada\Jnt\Enums\PaymentType::PREPAID_POSTPAID,
        'goods_type' => \AIArmada\Jnt\Enums\GoodsType::PACKAGE,
        'country_code' => 'MYS',
    ],

    /*
    |--------------------------------------------------------------------------
    | Features
    |--------------------------------------------------------------------------
    */
    'features' => [
        'notifications' => true,
        'persist_orders' => true,
        'log_tracking' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | HTTP Client
    |--------------------------------------------------------------------------
    */
    'http' => [
        'timeout' => 30,
        'connect_timeout' => 10,
        'retry_times' => 3,
        'retry_sleep' => 1000,
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhooks
    |--------------------------------------------------------------------------
    */
    'webhooks' => [
        'enabled' => env('JNT_WEBHOOKS_ENABLED', true),
        'route' => 'webhooks/jnt/status',
        'middleware' => ['api'],
        'verify_signature' => env('JNT_WEBHOOKS_VERIFY_SIGNATURE', true),
        'log_payloads' => env('JNT_WEBHOOK_LOG_PAYLOADS', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Owner Scoping
    |--------------------------------------------------------------------------
    */
    'owner' => [
        'enabled' => env('JNT_OWNER_ENABLED', false),
        'include_global' => env('JNT_OWNER_INCLUDE_GLOBAL', false),
        'auto_assign_on_create' => env('JNT_OWNER_AUTO_ASSIGN', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    */
    'logging' => [
        'enabled' => true,
        'channel' => 'stack',
        'level' => 'info',
    ],

    /*
    |--------------------------------------------------------------------------
    | Shipping
    |--------------------------------------------------------------------------
    */
    'shipping' => [
        'origin' => [
            'name' => env('JNT_ORIGIN_NAME', 'Store'),
            'phone' => env('JNT_ORIGIN_PHONE', ''),
            'address' => env('JNT_ORIGIN_ADDRESS', ''),
            'post_code' => env('JNT_ORIGIN_POSTCODE', ''),
            'city' => env('JNT_ORIGIN_CITY', ''),
            'state' => env('JNT_ORIGIN_STATE', ''),
            'country_code' => 'MYS',
        ],
        'base_rate' => 800,
        'per_kg_rate' => 200,
        'min_charge' => 800,
        'default_estimated_days' => 3,
        'east_malaysia_extra_days' => 2,
        'region_multipliers' => [
            'sabah' => 1.5,
            'sarawak' => 1.5,
            'labuan' => 1.5,
        ],
    ],
];
```
