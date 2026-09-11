---
title: Webhooks
---

# Webhooks

J&T status webhooks are signature-verified at the HTTP boundary, stored in the shared `webhook_calls` table, and processed by the queued `ProcessJntWebhook` job.

## Configuration

```env
JNT_PRIVATE_KEY=your_private_key
JNT_WEBHOOKS_ENABLED=true
JNT_WEBHOOKS_VERIFY_SIGNATURE=true
JNT_WEBHOOK_LOG_PAYLOADS=false
JNT_WEBHOOK_RETRY_TIMES=3
JNT_WEBHOOK_RETRY_BACKOFF_SECONDS=60
```

The endpoint is `POST /webhooks/jnt/status` by default. Configure the route with `JNT_WEBHOOK_ROUTE` when the application needs a different path.

## Tracking event

After the webhook is accepted, the queued processor dispatches `TrackingUpdatedEvent` with typed `TrackingData`. The event is dispatched for known and unknown local orders alike.

```php
use AIArmada\Jnt\Events\TrackingUpdatedEvent;

final class UpdateOrderTracking
{
    public function handle(TrackingUpdatedEvent $event): void
    {
        logger()->info('J&T tracking update', [
            'tracking_number' => $event->getTrackingNumber(),
            'order_id' => $event->getOrderId(),
            'status' => $event->getLatestStatus(),
        ]);
    }
}
```

Register the listener in the application event provider:

```php
use AIArmada\Jnt\Events\TrackingUpdatedEvent;

protected $listen = [
    TrackingUpdatedEvent::class => [
        \App\Listeners\UpdateOrderTracking::class,
    ],
];
```

`TrackingUpdatedEvent` exposes `getTrackingNumber()`, `getOrderId()`, `getLatestStatus()`, `getLatestDescription()`, `getLatestLocation()`, and typed tracking details through `getDetails()`.

## Webhook log records

`JntWebhookLog` reads only rows named `jnt.webhooks.status` from the shared table. It records the tracking number, order reference, matched order ID, digest, processing status, processing error, and processed timestamp. Its `order_id` write path validates the order against the current owner context.

Unknown orders remain valid webhook records; they receive the typed tracking event but cannot resolve a cross-owner local order.

## Queue retries

Network and processing failures are retried by the queue worker. There is no request-thread sleep or retry loop in the J&T HTTP client. Configure the queued retry policy:

```php
'webhooks' => [
    'retry_times' => 3,
    'retry_backoff_seconds' => 60,
],
```

Run a worker while testing locally:

```bash
php artisan queue:work
```

## Local verification

Generate a signature by hashing the exact JSON `bizContent` followed by the private key with MD5, then base64-encode the binary digest:

```bash
BIZCONTENT='{"billCode":"TEST123","details":[]}'
PRIVATE_KEY="your_private_key"
SIGNATURE=$(echo -n "${BIZCONTENT}${PRIVATE_KEY}" | openssl dgst -md5 -binary | base64)

curl -X POST https://yourdomain.com/webhooks/jnt/status \
  -H "Content-Type: application/json" \
  -H "digest: ${SIGNATURE}" \
  -d "{\"bizContent\":${BIZCONTENT}}"
```

Expected failures are `401` for a missing or invalid signature and `422` for malformed webhook content.
