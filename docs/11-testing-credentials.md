---
title: Testing Credentials
---

# Testing Credentials

## Overview

The J&T Express package does not bundle or automatically inject API credentials. Testing and production environments both read credentials from explicit configuration so applications cannot silently rely on shared or stale accounts.

## Testing Environment

Configure credentials issued for your J&T sandbox account:

```env
JNT_ENVIRONMENT=testing
JNT_API_ACCOUNT=your_testing_api_account
JNT_PRIVATE_KEY=your_testing_private_key
JNT_CUSTOMER_CODE=your_testing_customer_code
JNT_PASSWORD=your_testing_password
```

The default testing endpoint is configured by `JNT_BASE_URL_TESTING`. Override it only when J&T provides a different endpoint for your account.

## Production Environment

Production also requires explicit credentials:

```env
JNT_ENVIRONMENT=production
JNT_API_ACCOUNT=your_production_api_account
JNT_PRIVATE_KEY=your_production_private_key
JNT_CUSTOMER_CODE=your_production_customer_code
JNT_PASSWORD=your_production_password
```

Never reuse sandbox credentials in production or commit active credentials to version control.

## Verification

Run the package configuration check after updating the environment:

```bash
php artisan jnt:config:check
```

Then clear cached configuration when applicable:

```bash
php artisan config:clear
```

## Configuration Source

The package reads these values directly from `packages/jnt/config/jnt.php`:

```php
'api_account' => env('JNT_API_ACCOUNT'),
'private_key' => env('JNT_PRIVATE_KEY'),
'customer_code' => env('JNT_CUSTOMER_CODE'),
'password' => env('JNT_PASSWORD'),
```

No fallback credential values are defined.
