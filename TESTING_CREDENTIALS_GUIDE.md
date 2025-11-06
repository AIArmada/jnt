# Testing Credentials Auto-Configuration

## Overview
The J&T Express package now automatically configures testing credentials when running in testing environment, eliminating the need to manually copy/paste API credentials during development.

## How It Works

### For Testing Environment
When `JNT_ENVIRONMENT=testing`, the package automatically uses J&T's official public testing credentials:

- **API Account**: `640826271705595946`
- **Private Key**: `8e88c8477d4e4939859c560192fcafbc`

These are **public credentials** published by J&T Express for sandbox testing.

### For Production Environment
When `JNT_ENVIRONMENT=production`, you **must** explicitly provide your credentials:

```env
JNT_ENVIRONMENT=production
JNT_API_ACCOUNT=your_production_api_account
JNT_PRIVATE_KEY=your_production_private_key
```

## Quick Start

### Minimal Testing Setup
Create your `.env` file with just these required fields:

```env
JNT_ENVIRONMENT=testing

# Required credentials (get these from your J&T Distribution Partner)
JNT_CUSTOMER_CODE=your_customer_code
JNT_PASSWORD=your_password
```

That's it! The API Account and Private Key are automatically configured.

### Optional: Override Testing Credentials
If you need different testing credentials, you can still override them:

```env
JNT_ENVIRONMENT=testing

# Override defaults with custom credentials
JNT_API_ACCOUNT=custom_testing_account
JNT_PRIVATE_KEY=custom_testing_key

JNT_CUSTOMER_CODE=your_customer_code
JNT_PASSWORD=your_password
```

## Benefits

✅ **Faster Onboarding**: Start testing immediately with minimal configuration  
✅ **Fewer Errors**: No copy/paste mistakes with long credential strings  
✅ **Better Documentation**: README focuses on what developers actually need  
✅ **Secure by Default**: Production still requires explicit credentials  

## Implementation Details

### Configuration Logic
The conditional logic in `config/jnt.php`:

```php
'api_account' => env('JNT_API_ACCOUNT', 
    env('JNT_ENVIRONMENT', 'testing') === 'testing' 
        ? '640826271705595946'  // J&T official testing account
        : null  // Require explicit value in production
),

'private_key' => env('JNT_PRIVATE_KEY',
    env('JNT_ENVIRONMENT', 'testing') === 'testing'
        ? '8e88c8477d4e4939859c560192fcafbc'  // J&T official testing key
        : null  // Require explicit value in production
),
```

### Verification
Run the configuration check command to verify your setup:

```bash
php artisan jnt:config-check
```

## Migration Guide

### If You're Already Using This Package

**No changes required!** Existing `.env` files will continue to work exactly as before.

### If You Want to Simplify Your Configuration

1. Ensure `JNT_ENVIRONMENT=testing` is set
2. Remove these lines from your `.env`:
   ```env
   # JNT_API_ACCOUNT=640826271705595946
   # JNT_PRIVATE_KEY=8e88c8477d4e4939859c560192fcafbc
   ```
3. Clear config cache: `php artisan config:clear`

## Security Notes

- Testing credentials are **public information** from J&T's official documentation
- These credentials only work with J&T's sandbox environment
- Production credentials must **always** be explicitly configured
- Never commit production credentials to version control

## Reference

- **Source**: J&T Express Official API Documentation
- **Environment**: Sandbox/Testing Only
- **Configuration File**: `packages/jnt/config/jnt.php`
- **Documentation**: See README.md Configuration section

---

**Last Updated**: 2025-06-09  
**Feature Added**: Auto-configuration for testing credentials
