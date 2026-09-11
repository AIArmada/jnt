---
title: Events
---

# Events

J&T dispatches one canonical event for webhook tracking data and separate events for order and waybill outcomes. The old collection of parcel-specific tracking events is not part of the public API.

## Event summary

| Event | When it fires | Main data |
| --- | --- | --- |
| `OrderCreatedEvent` | An order is submitted successfully | `OrderData` in `$event->order` |
| `OrderCancelledEvent` | A cancellation response is received | order ID, reason, response |
| `WaybillPrintedEvent` | A waybill is generated | `PrintWaybillData` in `$event->waybill` |
| `TrackingUpdatedEvent` | A tracking webhook is processed | `TrackingData` in `$event->tracking` |
| `JntOrderStatusChanged` | A persisted order status changes | owner-aware order and status data |

## TrackingUpdatedEvent

`TrackingUpdatedEvent` is dispatched for both known and unknown local orders. It carries typed `TrackingData`, so consumers use the same vocabulary regardless of whether the webhook matched a local order.

```php
use AIArmada\Jnt\Events\TrackingUpdatedEvent;

final class RecordTrackingUpdate
{
    public function handle(TrackingUpdatedEvent $event): void
    {
        logger()->info('J&T tracking update', [
            'tracking_number' => $event->getTrackingNumber(),
            'order_id' => $event->getOrderId(),
            'status' => $event->getLatestStatus(),
            'description' => $event->getLatestDescription(),
        ]);
    }
}
```

Useful methods include `getTrackingNumber()`, `getOrderId()`, `getLatestStatus()`, `getLatestDescription()`, `getLatestLocation()`, `getDetails()`, `getDetailCount()`, `isDelivered()`, `isInTransit()`, `isCollected()`, and `hasProblems()`.

## JntOrderStatusChanged

This event is owner-aware and provides safe resolution back to the persisted `JntOrder`.

```php
use AIArmada\Jnt\Events\JntOrderStatusChanged;

final class NotifyOrderStatus
{
    public function handle(JntOrderStatusChanged $event): void
    {
        $order = $event->resolveOrder();

        if ($order === null) {
            return;
        }

        if ($event->isDelivered()) {
            // Trigger the application's delivery workflow.
        }
    }
}
```

The event exposes `currentStatus`, `previousStatusCode`, `ownerType`, `ownerId`, `getOrderId()`, and `getTrackingNumber()`. Predicate helpers include `isDelivered()`, `hasException()`, `isReturning()`, `requiresAttention()`, and `isTerminal()`.

## Order and waybill events

The remaining events wrap their typed data objects:

```php
use AIArmada\Jnt\Events\OrderCreatedEvent;
use AIArmada\Jnt\Events\OrderCancelledEvent;
use AIArmada\Jnt\Events\WaybillPrintedEvent;

final class JntEventListener
{
    public function orderCreated(OrderCreatedEvent $event): void
    {
        $trackingNumber = $event->getTrackingNumber();
    }

    public function orderCancelled(OrderCancelledEvent $event): void
    {
        $reason = $event->getReasonDescription();
    }

    public function waybillPrinted(WaybillPrintedEvent $event): void
    {
        if ($event->hasBase64Content()) {
            $pdf = $event->getPdfContent();
        }
    }
}
```

## Registering listeners

```php
use AIArmada\Jnt\Events\TrackingUpdatedEvent;

protected $listen = [
    TrackingUpdatedEvent::class => [
        \App\Listeners\RecordTrackingUpdate::class,
    ],
];
```

For expensive work, make the listener implement `ShouldQueue`. Webhook delivery itself is already handed to the queued `ProcessJntWebhook` job, whose retry count and backoff are configured under `jnt.webhooks.retry_times` and `jnt.webhooks.retry_backoff_seconds`.
