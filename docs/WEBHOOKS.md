# J&T Express Webhooks

Complete guide to receiving and processing J&T tracking status updates via webhooks.

## Quick Setup

### 1. Configure Environment

```env
JNT_PRIVATE_KEY=your_private_key
JNT_WEBHOOKS_ENABLED=true
JNT_WEBHOOK_LOG_PAYLOADS=false  # Enable for debugging only
```

### 2. Create Event Listener

```bash
php artisan make:listener UpdateOrderTracking
```

```php
<?php

namespace App\Listeners;

use App\Models\Order;
use AIArmada\Jnt\Events\TrackingStatusReceived;

class UpdateOrderTracking
{
    public function handle(TrackingStatusReceived $event): void
    {
        $order = Order::where('tracking_number', $event->trackingNumber)->first();
        
        if (!$order) {
            return;
        }

        $order->update([
            'tracking_status' => $event->lastStatus,
            'tracking_time' => $event->scanTime,
        ]);
    }
}
```

### 3. Register Listener

```php
// app/Providers/EventServiceProvider.php
protected $listen = [
    \AIArmada\Jnt\Events\TrackingStatusReceived::class => [
        \App\Listeners\UpdateOrderTracking::class,
    ],
];
```

### 4. Configure J&T Dashboard

Set webhook URL in J&T dashboard:
```
https://yourdomain.com/webhooks/jnt/status
```

## Event Data

The `TrackingStatusReceived` event provides:

```php
$event->trackingNumber;      // J&T tracking number
$event->orderId;             // Your order ID
$event->lastStatus;          // Latest status description
$event->scanTime;            // Timestamp of update
$event->allStatuses;         // Array of all status updates
$event->isDelivered();       // true if delivered
$event->hasProblem();        // true if issue detected
```

## Common Patterns

### Send Customer Notifications

```php
class NotifyCustomer
{
    public function handle(TrackingStatusReceived $event): void
    {
        $order = Order::where('tracking_number', $event->trackingNumber)->first();
        
        if (!$order) return;

        match(true) {
            $event->isDelivered() => 
                $order->user->notify(new OrderDelivered($order)),
            $event->hasProblem() => 
                $order->user->notify(new OrderHasProblem($order)),
            default => null
        };
    }
}
```

### Queue Processing

```php
use Illuminate\Contracts\Queue\ShouldQueue;

class ProcessWebhook implements ShouldQueue
{
    public $queue = 'webhooks';
    
    public function handle(TrackingStatusReceived $event): void
    {
        // Heavy processing here
    }
}
```

### Log Tracking History

```php
class LogTrackingHistory
{
    public function handle(TrackingStatusReceived $event): void
    {
        foreach ($event->allStatuses as $status) {
            TrackingEvent::create([
                'order_id' => $event->orderId,
                'tracking_number' => $event->trackingNumber,
                'status' => $status['description'],
                'timestamp' => $status['scanTime'],
                'location' => $status['scanNetworkCity'] ?? null,
            ]);
        }
    }
}
```

## Testing

### Local Testing with Tunnels

```bash
# Using Cloudflare Tunnel (recommended)
cloudflared tunnel run your-tunnel

# Or using ngrok
ngrok http 8000

# Or using Expose
expose share http://localhost:8000
```

Configure tunnel URL in J&T dashboard.

### Manual Testing

```bash
# Generate signature
BIZCONTENT='{"billCode":"TEST123","details":[{"scanTime":"2024-01-15 10:00:00","desc":"Test"}]}'
PRIVATE_KEY="your_private_key"
SIGNATURE=$(echo -n "${BIZCONTENT}${PRIVATE_KEY}" | openssl dgst -md5 -binary | base64)

# Send request
curl -X POST https://yourdomain.com/webhooks/jnt/status \
  -H "Content-Type: application/json" \
  -d "{\"digest\":\"${SIGNATURE}\",\"bizContent\":${BIZCONTENT}}"
```

## Troubleshooting

### Webhooks Not Received

**Check route:**
```bash
php artisan route:list | grep jnt
# Expected: POST | webhooks/jnt/status
```

**Verify config:**
```bash
php artisan tinker
>>> config('jnt.webhooks.enabled')
=> true
```

**Test endpoint:**
```bash
curl -X POST https://yourdomain.com/webhooks/jnt/status \
  -H "Content-Type: application/json" \
  -d '{"bizContent": "{}"}'
# Expected: 401 or 422 (not 404)
```

### Signature Verification Fails

**Verify private key:**
```bash
php artisan tinker
>>> config('jnt.private_key')
# Should match J&T dashboard value
```

**Common issues:**
- Extra whitespace in `.env`
- Wrong environment (sandbox vs production key)
- Extra quotes around value

**Fix:**
```env
# Correct
JNT_PRIVATE_KEY=your_key_here

# Wrong
JNT_PRIVATE_KEY="your_key_here"  # Extra quotes
JNT_PRIVATE_KEY= your_key_here    # Extra space
```

**Clear config after fixing:**
```bash
php artisan config:clear
```

### Events Not Firing

**Verify listener registered:**
```php
// EventServiceProvider.php must have:
\AIArmada\Jnt\Events\TrackingStatusReceived::class => [
    \App\Listeners\YourListener::class,
],
```

**Clear cache:**
```bash
php artisan event:clear
php artisan cache:clear
```

**Enable debug logging:**
```env
JNT_WEBHOOK_LOG_PAYLOADS=true
```

Check logs:
```bash
tail -f storage/logs/laravel.log | grep "J&T"
```

### Performance Issues

**Use queued listeners:**
```php
class ProcessWebhook implements ShouldQueue
{
    // Processing happens in background
}
```

**Limit database queries:**
```php
// Bad - N+1 queries
foreach ($event->allStatuses as $status) {
    TrackingEvent::create($status);
}

// Good - Bulk insert
TrackingEvent::insert($event->allStatuses);
```

## Security

### Signature Verification

Automatic signature verification via middleware. To disable (not recommended):

```php
// config/jnt.php
'webhooks' => [
    'verify_signature' => false,  // Not recommended for production
],
```

### IP Whitelisting

```php
// bootstrap/app.php
->withMiddleware(function (Middleware $middleware) {
    $middleware->web(append: [
        \App\Http\Middleware\WhitelistJntIPs::class,
    ]);
})
```

```php
// app/Http/Middleware/WhitelistJntIPs.php
class WhitelistJntIPs
{
    protected $whitelist = [
        // Add J&T IP addresses from documentation
    ];

    public function handle($request, $next)
    {
        if ($request->is('webhooks/jnt/*') && 
            !in_array($request->ip(), $this->whitelist)) {
            abort(403);
        }

        return $next($request);
    }
}
```

## Best Practices

1. **Always verify signatures** - Don't disable in production
2. **Use queued listeners** - For heavy processing
3. **Log webhook payloads** - Only in debugging, contains sensitive data
4. **Handle idempotency** - Same webhook may be sent multiple times
5. **Return 200 quickly** - Process in background if needed
6. **Monitor webhook failures** - Set up alerts for failed webhooks

## Configuration Reference

```php
// config/jnt.php
'webhooks' => [
    'enabled' => env('JNT_WEBHOOKS_ENABLED', true),
    'route' => env('JNT_WEBHOOK_ROUTE', 'webhooks/jnt/status'),
    'middleware' => ['api', 'jnt.verify.signature'],
    'verify_signature' => env('JNT_WEBHOOK_VERIFY_SIGNATURE', true),
    'log_payloads' => env('JNT_WEBHOOK_LOG_PAYLOADS', false),
],
```

## Related Documentation

- [README.md](../README.md) - Package overview and basic usage
- [API_REFERENCE.md](API_REFERENCE.md) - Complete API documentation
- [BATCH_OPERATIONS.md](BATCH_OPERATIONS.md) - Batch processing guide
