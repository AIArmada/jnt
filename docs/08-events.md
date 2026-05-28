---
title: Events
---

# Events

The JNT package dispatches Laravel events at key points in the shipping lifecycle. Use these events to trigger notifications, update your order system, integrate with other packages, and build custom workflows.

---

## Event Summary

| Event | When Fired | Key Properties |
|-------|------------|----------------|
| `OrderCreatedEvent` | Order submitted to J&T | `order`, tracking number |
| `OrderCancelledEvent` | Order cancelled | `orderId`, `reason` |
| `WaybillPrintedEvent` | Waybill generated | `waybill`, PDF content |
| `TrackingUpdated` | Webhook tracking update | `billcode`, `eventType`, `payload` |
| `JntOrderStatusChanged` | Order status changes | `currentStatus`, `previousStatusCode` |
| `ParcelPickedUp` | Parcel collected | `shipment` |
| `ParcelInTransit` | Parcel in transit | `shipment` |
| `ParcelOutForDelivery` | Out for delivery | `shipment` |
| `ParcelDelivered` | Parcel delivered | `shipment` |

---

## Order Events

### OrderCreatedEvent

Fired when an order is successfully submitted to J&T Express.

```php
use AIArmada\Jnt\Events\OrderCreatedEvent;

class HandleOrderCreated
{
    public function handle(OrderCreatedEvent $event): void
    {
        $orderId = $event->getOrderId();
        $trackingNumber = $event->getTrackingNumber();
        
        if ($event->hasTrackingNumber()) {
            // Update your order with tracking number
            Order::where('reference', $orderId)->update([
                'tracking_number' => $trackingNumber,
                'status' => 'shipped',
            ]);
        }
        
        // Access full order data
        $orderData = $event->order;
        $senderName = $orderData->sender->name;
        $receiverName = $orderData->receiver->name;
    }
}
```

### OrderCancelledEvent

Fired when an order is cancelled.

```php
use AIArmada\Jnt\Events\OrderCancelledEvent;

class HandleOrderCancelled
{
    public function handle(OrderCancelledEvent $event): void
    {
        $orderId = $event->getOrderId();
        $reason = $event->getReason();
        $description = $event->getReasonDescription();
        
        if ($event->wasSuccessful()) {
            Order::where('reference', $orderId)->update([
                'status' => 'cancelled',
                'cancellation_reason' => $description,
            ]);
            
            // Notify customer
            $this->notifyCustomer($orderId, $description);
        } else {
            // Handle failure
            logger()->error('Order cancellation failed', [
                'orderId' => $orderId,
                'message' => $event->getMessage(),
            ]);
        }
    }
}
```

### WaybillPrintedEvent

Fired when a waybill/label is generated.

```php
use AIArmada\Jnt\Events\WaybillPrintedEvent;

class HandleWaybillPrinted
{
    public function handle(WaybillPrintedEvent $event): void
    {
        $orderId = $event->getOrderId();
        $trackingNumber = $event->getTrackingNumber();
        
        // Save PDF to storage
        if ($event->hasBase64Content()) {
            $path = "waybills/{$trackingNumber}.pdf";
            $event->savePdf(storage_path("app/{$path}"));
        }
        
        // Or get download URL
        if ($event->hasUrlContent()) {
            $downloadUrl = $event->getDownloadUrl();
        }
        
        // Get file info
        $template = $event->getTemplateName();
        $fileSize = $event->getFileSize();
    }
}
```

---

## Tracking Events

### TrackingUpdated

Fired when a tracking update is received via webhook. This generic event is dispatched for both known and unknown shipments.

```php
use AIArmada\Jnt\Events\TrackingUpdated;

class HandleWebhookTracking
{
    public function handle(TrackingUpdated $event): void
    {
        $billcode = $event->billcode;
        $eventType = $event->eventType;
        $payload = $event->payload;

        $latestDetail = collect($payload['details'] ?? [])->last();
        $latestStatus = is_array($latestDetail)
            ? ($latestDetail['desc'] ?? $latestDetail['scanTypeName'] ?? $latestDetail['scanType'] ?? null)
            : null;

        logger()->info('Tracking update received', [
            'billcode' => $billcode,
            'event_type' => $eventType,
            'latest_status' => $latestStatus,
        ]);
    }
}
```

### JntOrderStatusChanged

Fired when a `JntOrder` model's status changes. This event is owner-aware for multi-tenant applications.

```php
use AIArmada\Jnt\Events\JntOrderStatusChanged;

class HandleStatusChanged
{
    public function handle(JntOrderStatusChanged $event): void
    {
        $orderId = $event->getOrderId();
        $trackingNumber = $event->getTrackingNumber();
        $currentStatus = $event->currentStatus;
        $previousStatus = $event->previousStatusCode;
        
        // Check status conditions
        if ($event->isDelivered()) {
            $this->markOrderComplete($orderId);
        }
        
        if ($event->hasException()) {
            $this->createSupportTicket($orderId);
        }
        
        if ($event->isReturning()) {
            $this->handleReturn($orderId);
        }
        
        if ($event->requiresAttention()) {
            $this->alertOperations($orderId, $currentStatus);
        }
        
        // Check terminal status
        if ($event->isTerminal()) {
            $this->closeOrderTracking($orderId);
        }
        
        // Multi-tenant: resolve owner
        $owner = $event->owner();
        if ($owner !== null) {
            $this->notifyOwner($owner, $orderId, $currentStatus);
        }
        
        // Resolve the JntOrder model
        $jntOrder = $event->resolveOrder();
    }
}
```

### TrackingUpdated (Simple)

A lightweight event for tracking updates.

```php
use AIArmada\Jnt\Events\TrackingUpdated;

class HandleSimpleTrackingUpdate
{
    public function handle(TrackingUpdated $event): void
    {
        $billcode = $event->billcode;
        $eventType = $event->eventType;
        $payload = $event->payload;
        
        logger()->info("Tracking update: {$eventType}", [
            'billcode' => $billcode,
            'data' => $payload,
        ]);
    }
}
```

---

## Parcel Lifecycle Events

These events track the physical journey of parcels. They receive a shipment model and optional payload.

### ParcelPickedUp

Fired when a parcel is collected from the sender.

```php
use AIArmada\Jnt\Events\ParcelPickedUp;

class HandlePickedUp
{
    public function handle(ParcelPickedUp $event): void
    {
        $shipment = $event->shipment;
        $payload = $event->payload;
        
        // Update order status
        $shipment->update(['status' => 'picked_up']);
        
        // Notify customer
        $this->sendPickupNotification($shipment);
    }
}
```

### ParcelInTransit

Fired when a parcel is in transit.

```php
use AIArmada\Jnt\Events\ParcelInTransit;

class HandleInTransit
{
    public function handle(ParcelInTransit $event): void
    {
        $shipment = $event->shipment;
        
        $shipment->update(['status' => 'in_transit']);
    }
}
```

### ParcelOutForDelivery

Fired when a parcel is out for delivery.

```php
use AIArmada\Jnt\Events\ParcelOutForDelivery;

class HandleOutForDelivery
{
    public function handle(ParcelOutForDelivery $event): void
    {
        $shipment = $event->shipment;
        
        $shipment->update(['status' => 'out_for_delivery']);
        
        // Send SMS to customer
        $this->sendDeliveryAlert($shipment);
    }
}
```

### ParcelDelivered

Fired when a parcel is delivered.

```php
use AIArmada\Jnt\Events\ParcelDelivered;

class HandleDelivered
{
    public function handle(ParcelDelivered $event): void
    {
        $shipmentId = $event->getShipmentId();
        $trackingNumber = $event->getTrackingNumber();
        
        $event->shipment->update([
            'status' => 'delivered',
            'delivered_at' => now(),
        ]);
        
        // Trigger post-delivery workflows
        $this->requestReview($event->shipment);
    }
}
```

---

## Registering Event Listeners

### EventServiceProvider

```php
// app/Providers/EventServiceProvider.php

use AIArmada\Jnt\Events\OrderCreatedEvent;
use AIArmada\Jnt\Events\OrderCancelledEvent;
use AIArmada\Jnt\Events\TrackingUpdated;
use AIArmada\Jnt\Events\JntOrderStatusChanged;
use AIArmada\Jnt\Events\ParcelDelivered;
use AIArmada\Jnt\Events\WaybillPrintedEvent;

protected $listen = [
    OrderCreatedEvent::class => [
        \App\Listeners\UpdateOrderWithTracking::class,
        \App\Listeners\SendShipmentConfirmation::class,
    ],
    
    OrderCancelledEvent::class => [
        \App\Listeners\HandleCancellation::class,
    ],
    
    TrackingUpdated::class => [
        \App\Listeners\ProcessWebhookTracking::class,
    ],
    
    JntOrderStatusChanged::class => [
        \App\Listeners\UpdateOrderStatus::class,
        \App\Listeners\SendStatusNotification::class,
    ],
    
    ParcelDelivered::class => [
        \App\Listeners\MarkOrderDelivered::class,
        \App\Listeners\RequestCustomerReview::class,
    ],
    
    WaybillPrintedEvent::class => [
        \App\Listeners\StoreWaybillPdf::class,
    ],
];
```

### Attribute-Based Listeners (Laravel 11+)

```php
use Illuminate\Events\Attributes\AsListener;
use AIArmada\Jnt\Events\ParcelDelivered;

#[AsListener]
class HandleDelivery
{
    public function __invoke(ParcelDelivered $event): void
    {
        // Handle delivery
    }
}
```

---

## Queued Listeners

For better performance, queue your event listeners:

```php
use Illuminate\Contracts\Queue\ShouldQueue;
use AIArmada\Jnt\Events\TrackingUpdated;

class ProcessWebhookTracking implements ShouldQueue
{
    public string $queue = 'jnt-tracking';
    
    public function handle(TrackingUpdated $event): void
    {
        // This runs in the background
    }
    
    public function failed(TrackingUpdated $event, \Throwable $exception): void
    {
        logger()->error('Failed to process webhook', [
            'billCode' => $event->billcode,
            'error' => $exception->getMessage(),
        ]);
    }
}
```

---

## Event Broadcasting

To broadcast events to frontend clients via WebSockets:

```php
use AIArmada\Jnt\Events\JntOrderStatusChanged;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class BroadcastStatusChange implements ShouldBroadcast
{
    public function __construct(
        public readonly JntOrderStatusChanged $event
    ) {}
    
    public function broadcastOn(): Channel
    {
        return new Channel("orders.{$this->event->orderReference}");
    }
    
    public function broadcastAs(): string
    {
        return 'status.updated';
    }
    
    public function broadcastWith(): array
    {
        return [
            'orderId' => $this->event->getOrderId(),
            'trackingNumber' => $this->event->getTrackingNumber(),
            'status' => $this->event->currentStatus->value,
            'isDelivered' => $this->event->isDelivered(),
        ];
    }
}
```

---

## Testing Events

### Assert Events Dispatched

```php
use AIArmada\Jnt\Events\OrderCreatedEvent;
use Illuminate\Support\Facades\Event;

it('dispatches event on order creation', function () {
    Event::fake([OrderCreatedEvent::class]);
    
    JntExpress::createOrder($orderData);
    
    Event::assertDispatched(OrderCreatedEvent::class, function ($event) {
        return $event->getOrderId() === 'ORDER-123';
    });
});
```

### Test Event Listeners

```php
use AIArmada\Jnt\Events\ParcelDelivered;

it('marks order as delivered', function () {
    $order = Order::factory()->create(['status' => 'shipped']);
    $shipment = JntOrder::factory()->create(['order_id' => $order->id]);
    
    event(new ParcelDelivered($shipment));
    
    expect($order->fresh()->status)->toBe('delivered');
});
```

### Fake Events in Tests

```php
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    Event::fake();
});

it('creates order without firing events', function () {
    $order = JntExpress::createOrder($data);
    
    // Events were captured, not executed
    Event::assertDispatched(OrderCreatedEvent::class);
});
```

---

## Summary

| Event Type | Use Case |
|------------|----------|
| **Order Events** | Update your order system, send confirmations |
| **Tracking Events** | Sync tracking data, update statuses |
| **Parcel Events** | Track physical journey, send notifications |
| **Webhook Events** | Handle real-time updates from J&T |

Events provide a clean, decoupled way to extend the JNT package's functionality without modifying core code.
