---
title: Tracking
---

# Tracking

Track parcels and synchronize tracking data with your database.

## Basic Tracking

### Track by Order ID

```php
use AIArmada\Jnt\Facades\JntExpress;

$tracking = JntExpress::trackParcel(orderId: 'ORDER-2024-001');

echo $tracking->trackingNumber;   // J&T tracking number
echo $tracking->orderId;          // Your order ID

// Check delivery status
if ($tracking->isDelivered()) {
    echo 'Package delivered!';
}

// Get latest status
$latest = $tracking->getLatestDetail();
echo $latest->description;        // e.g., "Parcel delivered"
echo $latest->scanNetworkName;    // Location name
echo $latest->scanTime;           // Timestamp
```

### Track by Tracking Number

```php
$tracking = JntExpress::trackParcel(trackingNumber: 'JT630002864925');
```

## Tracking Data Structure

The `TrackingData` object contains:

```php
use AIArmada\Jnt\Data\TrackingData;

$tracking = JntExpress::trackParcel(orderId: 'ORDER-2024-001');

// Main properties
$tracking->trackingNumber;  // J&T tracking number
$tracking->orderId;         // Your order reference
$tracking->details;         // DataCollection of TrackingDetailData

// Helper methods
$tracking->getLatestDetail();   // Most recent event
$tracking->getLatestStatus();   // Latest scan type
$tracking->getLatestLocation(); // Latest network name
$tracking->isDelivered();       // true if delivered
```

## Tracking Detail Data

Each tracking event is a `TrackingDetailData` object:

```php
use AIArmada\Jnt\Data\TrackingDetailData;

foreach ($tracking->details as $detail) {
    echo $detail->scanTime;           // "2024-01-15 10:30:00"
    echo $detail->description;        // "Parcel picked up"
    echo $detail->scanType;           // "PICKUP", "SIGN", etc.
    echo $detail->scanTypeCode;       // "10", "100", etc.
    echo $detail->scanTypeName;       // Human-readable type
    
    // Location info
    echo $detail->scanNetworkName;    // "KL Distribution Center"
    echo $detail->scanNetworkCity;    // "Kuala Lumpur"
    echo $detail->scanNetworkProvince; // "Wilayah Persekutuan"
    echo $detail->getLocation();      // Formatted location string
    
    // Staff info
    echo $detail->staffName;          // Courier name
    echo $detail->staffContact;       // Courier contact
    
    // Coordinates (if available)
    if ($detail->hasCoordinates()) {
        echo $detail->latitude;
        echo $detail->longitude;
    }
    
    // Status checks
    $detail->isDelivered();   // true for SIGN/SIGN_STATION
    $detail->isInTransit();   // true for transit events
}
```

## Normalized Tracking Status

The package normalizes J&T scan types to a unified `TrackingStatus` enum:

```php
use AIArmada\Jnt\Enums\TrackingStatus;
use AIArmada\Jnt\Services\JntStatusMapper;

$mapper = app(JntStatusMapper::class);
$status = $mapper->fromCode('100');  // ScanTypeCode for "signed"

// TrackingStatus values
TrackingStatus::Pending           // Initial state
TrackingStatus::PickedUp          // Collected by courier
TrackingStatus::InTransit         // In transit
TrackingStatus::AtHub             // At sorting facility
TrackingStatus::OutForDelivery    // Out for delivery
TrackingStatus::DeliveryAttempted // Delivery attempted
TrackingStatus::Delivered         // Successfully delivered
TrackingStatus::ReturnInitiated   // Return started
TrackingStatus::Returned          // Returned to sender
TrackingStatus::Exception         // Problem occurred

// Status helper methods
$status->label();          // "Delivered"
$status->icon();           // Heroicon name
$status->color();          // Filament color
$status->isTerminal();     // Delivery complete or failed?
$status->isSuccessful();   // Delivered successfully?
$status->isInProgress();   // Still being processed?
$status->isReturn();       // Return in progress?
$status->requiresAttention(); // Needs action?
```

## JntTrackingService

The `JntTrackingService` provides high-level tracking operations:

```php
use AIArmada\Jnt\Services\JntTrackingService;

$trackingService = app(JntTrackingService::class);

// Get normalized tracking data
$result = $trackingService->track(trackingNumber: 'JT630002864925');

echo $result['tracking_number'];  // Tracking number
echo $result['order_id'];         // Order ID
echo $result['current_status'];   // TrackingStatus enum

foreach ($result['events'] as $event) {
    echo $event['status'];       // TrackingStatus
    echo $event['description'];  // Event description
    echo $event['location'];     // Formatted location
    echo $event['occurred_at'];  // Carbon datetime
}
```

## Syncing to Database

Sync tracking events to your database:

```php
use AIArmada\Jnt\Models\JntOrder;
use AIArmada\Jnt\Services\JntTrackingService;

$trackingService = app(JntTrackingService::class);

// Sync a single order
$order = JntOrder::where('order_id', 'ORDER-2024-001')->first();
$updatedOrder = $trackingService->syncOrderTracking($order);

// Check results
echo $updatedOrder->last_status;       // Latest status description
echo $updatedOrder->last_status_code;  // Latest scan type code
echo $updatedOrder->last_tracked_at;   // Sync timestamp
echo $updatedOrder->delivered_at;      // Delivery timestamp (if delivered)
echo $updatedOrder->has_problem;       // true if issues detected
```

### Batch Sync

Sync multiple orders at once:

```php
$orders = JntOrder::whereNull('delivered_at')
    ->whereNotNull('tracking_number')
    ->get();

$results = $trackingService->batchSyncTracking($orders);

// Successful syncs
foreach ($results['successful'] as $order) {
    echo "Synced: {$order->order_id}";
}

// Failed syncs
foreach ($results['failed'] as $failure) {
    echo "Failed: {$failure['order']->order_id} - {$failure['error']}";
}
```

### Automatic Updates

Get orders that need tracking updates:

```php
// Get orders needing updates (not tracked in last hour)
$orders = $trackingService->getOrdersNeedingTrackingUpdate(limit: 100);

// Sync them
$results = $trackingService->batchSyncTracking($orders);
```

## Scheduling Updates

Set up scheduled tracking sync in your scheduler:

```php
// app/Console/Kernel.php
protected function schedule(Schedule $schedule): void
{
    $schedule->call(function () {
        $trackingService = app(JntTrackingService::class);
        
        $orders = $trackingService->getOrdersNeedingTrackingUpdate(100);
        $trackingService->batchSyncTracking($orders);
        
    })->hourly();
}
```

## Tracking Events

When tracking is synced, events are stored in `jnt_tracking_events`:

```php
use AIArmada\Jnt\Models\JntTrackingEvent;
use AIArmada\Jnt\Models\JntOrder;

// Get events for an order
$order = JntOrder::find($orderId);
$events = $order->trackingEvents()->latest('scan_time')->get();

foreach ($events as $event) {
    echo $event->scan_time;           // Event timestamp
    echo $event->description;         // Event description
    echo $event->scan_type_code;      // J&T scan code
    echo $event->getLocation();       // Formatted location
    echo $event->getNormalizedStatus(); // TrackingStatus enum
    
    // Status checks
    $event->isDelivered();    // true if delivered
    $event->isCollected();    // true if picked up
    $event->hasProblem();     // true if problem detected
}
```

## Scan Type Codes

The `ScanTypeCode` enum maps J&T codes:

```php
use AIArmada\Jnt\Enums\ScanTypeCode;

// Normal flow
ScanTypeCode::PARCEL_PICKUP      // '10' - Picked up
ScanTypeCode::OUTBOUND_SCAN      // '20' - Outbound
ScanTypeCode::ARRIVAL            // '30' - Arrived at hub
ScanTypeCode::DELIVERY_SCAN      // '94' - Out for delivery
ScanTypeCode::PARCEL_SIGNED      // '100' - Delivered

// Problems
ScanTypeCode::PROBLEMATIC_SCANNING // '110' - Issue found
ScanTypeCode::RETURN_SCAN         // '172' - Return initiated
ScanTypeCode::RETURN_SIGN         // '173' - Returned

// Terminal states
ScanTypeCode::DAMAGE_PARCEL       // '201' - Damaged
ScanTypeCode::LOST_PARCEL         // '300' - Lost
ScanTypeCode::DISPOSE_PARCEL      // '301' - Disposed

// Helper methods
$code = ScanTypeCode::PARCEL_SIGNED;
$code->getDescription();     // "Parcel Signed"
$code->isTerminalState();    // true for final states
$code->isSuccessfulDelivery(); // true only for signed
$code->isProblem();          // true for issues
$code->isReturn();           // true for returns
$code->isCustoms();          // true for customs events
$code->getCategory();        // "Normal Flow", etc.
```

## Status Change Events

When order status changes, the package dispatches `JntOrderStatusChanged`:

```php
use AIArmada\Jnt\Events\JntOrderStatusChanged;

class OrderStatusListener
{
    public function handle(JntOrderStatusChanged $event): void
    {
        $order = $event->order;
        $newStatus = $event->status;        // TrackingStatus
        $previousCode = $event->previousStatusCode;
        
        // Notify customer
        if ($newStatus === TrackingStatus::Delivered) {
            $order->customer->notify(new OrderDeliveredNotification($order));
        }
    }
}
```

## CLI Tracking

Track orders via command line:

```bash
# Track by order ID
php artisan jnt:order:track ORDER-2024-001

# Track by tracking number
php artisan jnt:order:track JT630002864925 --tracking-number

# Output
Tracking order: ORDER-2024-001
✓ Tracking Information Found
+---------------------+----------------+-----------------------------+
| Time                | Status         | Description                 |
+---------------------+----------------+-----------------------------+
| 2024-01-15 10:30:00 | PICKUP         | Parcel picked up            |
| 2024-01-15 14:00:00 | ARRIVED        | Arrived at KL Hub           |
| 2024-01-16 09:00:00 | DELIVERY_SCAN  | Out for delivery            |
| 2024-01-16 11:30:00 | SIGN           | Delivered to recipient      |
+---------------------+----------------+-----------------------------+
```

## Integration with Shipping Package

The package implements `StatusMapperInterface` for unified tracking:

```php
use AIArmada\Shipping\Contracts\StatusMapperInterface;
use AIArmada\Jnt\Services\JntStatusMapper;

$mapper = app(JntStatusMapper::class);

// Maps to shipping package's normalized status
$normalizedStatus = $mapper->map('100'); // Returns NormalizedTrackingStatus::SignedFor
```
