---
title: Troubleshooting
---

# Troubleshooting

This guide covers common issues, error codes, and debugging techniques for the JNT package.

---

## Configuration Check

Run the built-in configuration check command:

```bash
php artisan jnt:config-check
```

This verifies:
- Environment (testing/production)
- API credentials
- Signature generation
- API connectivity

---

## Common Issues

### 1. "API account does not exist"

**Error**: `API_ACCOUNT_NOT_EXISTS` (145003010)

**Cause**: Invalid `JNT_API_ACCOUNT` credential.

**Solution**:
1. Verify your API account in the J&T Express Console
2. Check `.env` for typos:
   ```env
   JNT_API_ACCOUNT=your_actual_api_account
   ```
3. Ensure you're using the correct environment:
   ```env
   JNT_ENVIRONMENT=testing  # or production
   ```

---

### 2. "Signature verification failed"

**Error**: `SIGNATURE_VERIFICATION_FAILED` (145003030)

**Cause**: Request signature doesn't match J&T's verification.

**Solution**:
1. Verify your private key is correct:
   ```env
   JNT_PRIVATE_KEY=your_private_key
   ```
2. Ensure timestamp is in UTC+8 milliseconds
3. Check that `bizContent` is properly JSON-encoded
4. Run the config check:
   ```bash
   php artisan jnt:config-check
   ```

---

### 3. "Customer code is required"

**Error**: `CUSTOMER_CODE_REQUIRED` (999001010)

**Cause**: Missing customer code in configuration.

**Solution**:
```env
JNT_CUSTOMER_CODE=your_customer_code
```

---

### 4. "Data cannot be found"

**Error**: `DATA_NOT_FOUND` (999001030)

**Cause**: Order ID or tracking number doesn't exist.

**Solution**:
1. Verify the order was successfully created
2. Check for typos in order ID/tracking number
3. In testing environment, data may be cleaned periodically

---

### 5. "Order status cannot be cancelled"

**Error**: `ORDER_CANNOT_BE_CANCELLED` (999002010)

**Cause**: Order has progressed beyond cancellable status.

**Solution**:
- Only pending orders can be cancelled
- Once picked up, orders cannot be cancelled via API
- Contact J&T support for manual intervention

---

### 6. Webhook Not Receiving Data

**Symptoms**: Webhooks configured but not receiving updates.

**Checklist**:
1. Verify webhook URL is publicly accessible:
   ```bash
   curl -X POST https://yourdomain.com/api/jnt/webhook
   ```

2. Check webhook secret matches:
   ```env
   JNT_WEBHOOK_SECRET=your_webhook_secret
   ```

3. Verify routes are published:
   ```bash
   php artisan route:list --name=jnt
   ```

4. Check webhook logs:
   ```php
   JntWebhookLog::latest()->take(10)->get();
   ```

5. Ensure queue worker is running:
   ```bash
   php artisan queue:work
   ```

---

### 7. Tracking Not Updating

**Symptoms**: Tracking status is stale or not syncing.

**Solutions**:

1. Manual sync:
   ```bash
   php artisan jnt:track ORDER-123
   ```

2. Check last sync time:
   ```php
   $order = JntOrder::where('order_id', 'ORDER-123')->first();
   echo $order->last_synced_at;
   ```

3. Force sync via API:
   ```php
   JntExpress::trackParcel('JT123456');
   ```

4. Enable webhooks for real-time updates

---

### 8. Connection Timeout

**Symptoms**: Requests timing out.

**Solutions**:

1. Increase timeout in config:
   ```php
   // config/jnt.php
   'http' => [
       'timeout' => 60, // Increase from 30
   ],
   ```

2. Add retry logic:
   ```php
   'http' => [
       'retry_times' => 5,
       'retry_delay' => 500, // milliseconds
   ],
   ```

3. Check network connectivity to J&T servers:
   ```bash
   curl -v https://uat-open.jtexpress.my
   ```

---

### 9. Invalid Postal Code

**Error**: Validation fails on postal codes.

**Cause**: Malaysian postal codes must be exactly 5 digits.

**Solution**:
```php
use AIArmada\Jnt\Data\AddressData;

$address = AddressData::from([
    'name' => 'John Doe',
    'phone' => '60123456789',
    'address' => '123 Jalan Example',
    'postcode' => '50000', // Must be 5 digits
    'city' => 'Kuala Lumpur',
    'state' => 'Kuala Lumpur',
    'country' => 'Malaysia',
]);
```

---

### 10. Owner Scope Issues

**Symptoms**: Missing data or cross-tenant visibility.

**Solutions**:

1. Verify owner is set:
   ```php
   use AIArmada\CommerceSupport\Support\OwnerContext;
   
   $owner = OwnerContext::get();
   dd($owner); // Should not be null
   ```

2. Check owner configuration:
   ```php
   config('jnt.owner.enabled'); // Should be true
   ```

3. Bypass scope for debugging (temporarily):
   ```php
   $allOrders = JntOrder::query()
       ->withoutGlobalScope(\AIArmada\CommerceSupport\Scopes\OwnerScope::class)
       ->get();
   ```

---

## API Error Codes Reference

### Authentication Errors

| Code | Name | Solution |
|------|------|----------|
| 145003010 | API_ACCOUNT_NOT_EXISTS | Verify API account credentials |
| 145003012 | API_ACCOUNT_NO_PERMISSION | Request API access from J&T |
| 145003030 | SIGNATURE_VERIFICATION_FAILED | Check private key and signature |
| 145003051 | API_ACCOUNT_EMPTY | Add `JNT_API_ACCOUNT` to .env |
| 145003052 | DIGEST_EMPTY | Signature not being generated |
| 145003053 | TIMESTAMP_EMPTY | Timestamp header missing |

### Validation Errors

| Code | Name | Solution |
|------|------|----------|
| 145003050 | ILLEGAL_PARAMETERS | Check request format |
| 999001010 | CUSTOMER_CODE_REQUIRED | Add `JNT_CUSTOMER_CODE` to .env |
| 999001011 | PASSWORD_REQUIRED | Add `JNT_PASSWORD` to .env |
| 999001012 | TX_LOGISTIC_ID_REQUIRED | Include order ID in request |

### Data Errors

| Code | Name | Solution |
|------|------|----------|
| 999001030 | DATA_NOT_FOUND | Verify order/tracking exists |
| 999002000 | DATA_NOT_FOUND_CANCEL | Order doesn't exist |
| 999002010 | ORDER_CANNOT_BE_CANCELLED | Order already processed |

---

## Debugging Techniques

### Enable HTTP Logging

```php
// config/jnt.php
'logging' => [
    'enabled' => true,
    'channel' => 'jnt',
],
```

Create log channel:
```php
// config/logging.php
'channels' => [
    'jnt' => [
        'driver' => 'daily',
        'path' => storage_path('logs/jnt.log'),
        'level' => 'debug',
        'days' => 14,
    ],
],
```

### Inspect API Requests

```php
$order = JntOrder::first();

// View request payload
dd($order->request_payload);

// View response payload
dd($order->response_payload);
```

### Test API Connectivity

```php
use AIArmada\Jnt\Facades\JntExpress;

try {
    $tracking = JntExpress::trackParcel('JT123456789');
    dd($tracking);
} catch (\AIArmada\Jnt\Exceptions\JntApiException $e) {
    dd([
        'message' => $e->getMessage(),
        'code' => $e->getCode(),
        'response' => $e->getResponse(),
    ]);
}
```

### Webhook Debugging

```php
// Check recent webhooks
$logs = JntWebhookLog::query()
    ->latest()
    ->take(10)
    ->get(['id', 'bill_code', 'processed_at', 'exception']);

foreach ($logs as $log) {
    echo "{$log->bill_code}: " . ($log->exception ?? 'OK') . "\n";
}
```

### Signature Verification Test

```php
use AIArmada\Jnt\Http\JntClient;

$client = app(JntClient::class);

// Generate a test signature
$bizContent = json_encode(['test' => 'data']);
$timestamp = (string) (int) (microtime(true) * 1000);

// The signature is generated automatically by the client
// Check logs for the actual signature values
```

---

## Testing Environment

### Sandbox Limitations

The J&T testing environment has some limitations:

1. **Data cleanup**: Test data may be periodically deleted
2. **Limited tracking**: Tracking updates may not reflect real scenarios
3. **Rate limits**: More restrictive than production
4. **Webhook delays**: Updates may be delayed or unavailable

### Testing Credentials

Ensure you have separate testing credentials:

```env
# Testing
JNT_ENVIRONMENT=testing
JNT_API_ACCOUNT=test_account
JNT_PRIVATE_KEY=test_private_key
JNT_CUSTOMER_CODE=test_customer
JNT_PASSWORD=test_password
```

### Mock API for Unit Tests

```php
use AIArmada\Jnt\Facades\JntExpress;

it('handles API errors gracefully', function () {
    JntExpress::fake([
        'createOrder' => JntExpress::response([
            'code' => 999001010,
            'msg' => 'Customer code is required',
        ]),
    ]);
    
    expect(fn() => JntExpress::createOrder($data))
        ->toThrow(JntApiException::class);
});
```

---

## Performance Issues

### Slow Batch Operations

**Symptoms**: Batch operations taking too long.

**Solutions**:

1. Reduce batch size:
   ```php
   $batches = array_chunk($orders, 25); // Smaller batches
   ```

2. Use queued jobs:
   ```php
   foreach ($batches as $batch) {
       ProcessJntBatch::dispatch($batch);
   }
   ```

3. Enable concurrency:
   ```php
   // Uses Laravel's Concurrency facade internally
   JntExpress::batchCreateOrders($orders);
   ```

### Database Query Optimization

```php
// Eager load relationships
$orders = JntOrder::query()
    ->with(['items', 'parcels', 'trackingEvents'])
    ->get();

// Use select for specific columns
$orders = JntOrder::query()
    ->select(['id', 'order_id', 'tracking_number', 'status'])
    ->get();
```

---

## Getting Help

### Log Collection

When reporting issues, collect:

1. Error message and stack trace
2. Request/response payloads
3. Configuration (without secrets)
4. Laravel and package versions

```php
// Collect debug info
$debug = [
    'php_version' => PHP_VERSION,
    'laravel_version' => app()->version(),
    'jnt_environment' => config('jnt.environment'),
    'error' => $exception->getMessage(),
    'trace' => $exception->getTraceAsString(),
];
```

### Support Channels

1. **Package Issues**: GitHub Issues on the repository
2. **J&T API Issues**: Contact J&T Express support
3. **Credential Issues**: J&T Express Console or account manager

---

## Checklist

Before going to production, verify:

- [ ] Correct environment set (`production`)
- [ ] Production credentials configured
- [ ] Webhook URL publicly accessible
- [ ] HTTPS enabled for webhooks
- [ ] Queue workers running
- [ ] Error logging configured
- [ ] Owner scoping enabled (if multi-tenant)
- [ ] Config check passes: `php artisan jnt:config-check`
