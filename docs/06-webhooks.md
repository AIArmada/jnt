---
title: Webhooks
---

# Webhooks

Receive real-time tracking status updates from J&T Express.

## Quick Setup

### 1. Configure Environment

```env
JNT_PRIVATE_KEY=your_private_key
JNT_WEBHOOKS_ENABLED=true
JNT_WEBHOOK_LOG_PAYLOADS=false  # Enable for debugging only
```

### 2. Create Event Listener

```php
namespace App\Listeners;

use App\Models\Order;
use AIArmada\Jnt\Events\TrackingUpdated;

class UpdateOrderTracking
{
    public function handle(TrackingUpdated $event): void
    {
        $order = Order::where('tracking_number', $event->billcode)->first();
        
        if (!$order) {
            return;
        }

        $order->update([
            'tracking_status' => $event->eventType,
            'tracking_payload' => $event->payload,
        ]);
    }
}
```

### 3. Register Listener

```php
// app/Providers/EventServiceProvider.php
protected $listen = [
    \AIArmada\Jnt\Events\TrackingUpdated::class => [
        \App\Listeners\UpdateOrderTracking::class,
    ],
];
```

### 4. Configure J&T Dashboard

Set your webhook URL:

```
https://yourdomain.com/webhooks/jnt/status
```

---

## Event Data

The generic `TrackingUpdated` event is always dispatched, even when the shipment is not yet known locally:

```php
$event->billcode;   // J&T tracking number
$event->eventType;  // scanType / derived webhook event type
$event->payload;    // Decoded bizContent payload
```

When the webhook can be matched to a shipment, the processor also dispatches status-specific events:

- `ParcelPickedUp`
- `ParcelInTransit`
- `ParcelOutForDelivery`
- `ParcelDelivered`

Those status-specific events expose the shipment model directly:

```php
$event->shipment;           // JntOrder model
$event->getShipmentId();    // Shipment primary key
$event->getTrackingNumber();
```

## Webhook log records

Every delivery is written to `JntWebhookLog`, which uses the shared `webhook_calls` table under the hood. During processing the package updates the log row with:

- `tracking_number`
- `order_reference`
- `order_id` when the shipment is known
- `digest`
- `processing_status` (`pending`, `processed`, or `failed`)
- `processing_error`
- `processed_at`

Unknown shipments still update the webhook log metadata and dispatch `TrackingUpdated`, which makes webhook logs useful even before a local order record exists.

---

## Common Patterns

### Customer Notifications

```php
use AIArmada\Jnt\Events\ParcelDelivered;

class NotifyCustomer
{
    public function handle(ParcelDelivered $event): void
    {
        $shipment = $event->shipment;

        if (! $shipment->relationLoaded('order')) {
            $shipment->loadMissing('order');
        }

        $order = $shipment->order;

        if ($order === null) {
            return;
        }

        $order->user?->notify(new OrderDelivered($order));
    }
}
```

### Queue Processing

```php
use Illuminate\Contracts\Queue\ShouldQueue;

class ProcessWebhook implements ShouldQueue
{
    public string $queue = 'webhooks';
    
    public function handle(TrackingUpdated $event): void
    {
        // Heavy processing runs in background
    }
}
```

### Log Tracking History

```php
class LogTrackingHistory
{
    public function handle(TrackingUpdated $event): void
    {
        foreach (($event->payload['details'] ?? []) as $status) {
            TrackingEvent::create([
                'tracking_number' => $event->billcode,
                'status' => $status['desc'] ?? $status['scanTypeName'] ?? $status['scanType'] ?? 'unknown',
                'timestamp' => $status['scanTime'],
                'location' => $status['scanNetworkCity'] ?? null,
            ]);
        }
    }
}
```

---

## Testing Locally

### Using Tunnels

```bash
# Cloudflare Tunnel
cloudflared tunnel run your-tunnel

# Or ngrok
ngrok http 8000

# Or Expose
expose share http://localhost:8000
```

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

---

## Troubleshooting

### Webhooks Not Received

**Check route exists:**
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
JNT_PRIVATE_KEY="your_key_here"
JNT_PRIVATE_KEY= your_key_here
```

**Clear config:**
```bash
php artisan config:clear
```

### Events Not Firing

**Verify listener registered:**
```php
// EventServiceProvider.php must have:
\AIArmada\Jnt\Events\TrackingUpdated::class => [
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

---

## Security

### Signature Verification

Handled by the package's webhook verification flow. To disable (not recommended):

```php
// config/jnt.php
'webhooks' => [
    'verify_signature' => false,
],
```

### IP Whitelisting

```php
// app/Http/Middleware/WhitelistJntIPs.php
class WhitelistJntIPs
{
    protected array $whitelist = [
        // Add J&T IP addresses
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

---

## Configuration Reference

```php
// config/jnt.php
'webhooks' => [
    'enabled' => env('JNT_WEBHOOKS_ENABLED', true),
    'route' => env('JNT_WEBHOOK_ROUTE', 'webhooks/jnt/status'),
    'middleware' => ['api'],
    'verify_signature' => env('JNT_WEBHOOKS_VERIFY_SIGNATURE', true),
    'log_payloads' => env('JNT_WEBHOOK_LOG_PAYLOADS', false),
],
```

---

## Best Practices

1. **Always verify signatures** in production
2. **Use queued listeners** for heavy processing
3. **Log payloads** only for debugging
4. **Handle idempotency** – webhooks may be sent multiple times
5. **Return 200 quickly** – process in background
6. **Monitor failures** – set up alerts
