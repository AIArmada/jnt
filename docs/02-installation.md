---
title: Installation
---

# Installation

## Requirements

- PHP 8.4+
- Laravel 11+
- Valid J&T Express API credentials

## Package Installation

Install via Composer:

```bash
composer require aiarmada/jnt
```

## Publish Configuration

Publish the configuration file:

```bash
php artisan vendor:publish --provider="AIArmada\Jnt\JntServiceProvider" --tag="config"
```

This creates `config/jnt.php` with all configurable options.

## Run Migrations

Run database migrations to create required tables:

```bash
php artisan migrate
```

This creates five tables:

| Table | Purpose |
|-------|---------|
| `jnt_orders` | Main shipping order records |
| `jnt_order_items` | Individual items in shipments |
| `jnt_order_parcels` | Multi-parcel tracking |
| `jnt_tracking_events` | Tracking event history |
| `jnt_webhook_logs` | Webhook request logging |

## Environment Variables

Add the following to your `.env` file:

```env
# Required - API Credentials
JNT_API_ACCOUNT=your_api_account
JNT_PRIVATE_KEY=your_private_key
JNT_CUSTOMER_CODE=your_customer_code
JNT_PASSWORD=your_password

# Environment (testing or production)
JNT_ENVIRONMENT=testing

# Optional - Webhooks
JNT_WEBHOOKS_ENABLED=true
JNT_WEBHOOKS_VERIFY_SIGNATURE=true
JNT_WEBHOOK_LOG_PAYLOADS=false

# Optional - Owner Scoping (for multi-tenant apps)
JNT_OWNER_ENABLED=false
JNT_OWNER_INCLUDE_GLOBAL=false
JNT_OWNER_AUTO_ASSIGN=true

# Optional - HTTP Settings
JNT_HTTP_TIMEOUT=30
JNT_HTTP_RETRY_TIMES=3
```

## Obtaining API Credentials

1. Register for a J&T Express merchant account at [J&T Express Malaysia](https://www.jtexpress.my)
2. Apply for API access through your account manager
3. You will receive:
   - **API Account** - Your unique API identifier
   - **Private Key** - For request signing
   - **Customer Code** - Your merchant identifier
   - **Password** - API password

### Testing vs Production

J&T provides separate environments:

| Environment | Base URL |
|-------------|----------|
| Testing | `https://uat-openapi.jtexpress.my/openplatformweb` |
| Production | `https://openapi.jtexpress.my/openplatformweb` |

The package automatically uses the correct URL based on `JNT_ENVIRONMENT`.

## Verify Installation

Run the configuration check command:

```bash
php artisan jnt:config:check
```

Expected output:

```
J&T Express Configuration Check
+----------------+--------+--------------------------------+
| Configuration  | Status | Details                        |
+----------------+--------+--------------------------------+
| API Account    | ✓      | Configured                     |
| Private Key    | ✓      | Valid hex string key           |
| Environment    | ✓      | Testing                        |
| Base URLs      | ✓      | Configured for testing         |
+----------------+--------+--------------------------------+
Testing API connectivity...
✓ All checks passed! J&T Express is properly configured.
```

## Webhook Setup

If you want to receive real-time tracking updates:

### 1. Configure Webhook Route

The package automatically registers a webhook route at:

```
POST /webhooks/jnt/status
```

### 2. Configure J&T Dashboard

In your J&T merchant dashboard, set the webhook URL:

```
https://yourdomain.com/webhooks/jnt/status
```

### 3. Local Development

For local testing, use a tunnel service:

```bash
# Cloudflare Tunnel
cloudflared tunnel run your-tunnel

# Or ngrok
ngrok http 8000

# Or Laravel Herd
herd share
```

## Optional: Filament Integration

For admin panel integration, install the Filament package:

```bash
composer require aiarmada/filament-jnt
```

Add the plugin to your Filament panel:

```php
use AIArmada\FilamentJnt\FilamentJntPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        // ...
        ->plugins([
            FilamentJntPlugin::make(),
        ]);
}
```

## Optional: Cart Integration

If using the `cart` package, the JNT shipping calculator is automatically registered when both packages are installed.

Configure your origin address in `config/jnt.php`:

```php
'shipping' => [
    'origin' => [
        'name' => 'Your Store Name',
        'phone' => '60123456789',
        'address' => '123 Store Address',
        'post_code' => '50000',
        'city' => 'Kuala Lumpur',
        'state' => 'Kuala Lumpur',
        'country_code' => 'MYS',
    ],
    'base_rate' => 800,    // RM8.00 in cents
    'per_kg_rate' => 200,  // RM2.00 per kg
],
```

## Next Steps

- [Configuration](03-configuration.md) - Detailed configuration options
- [Usage](04-usage.md) - Creating and managing orders
- [Tracking](05-tracking.md) - Tracking parcels
- [Webhooks](06-webhooks.md) - Real-time updates
