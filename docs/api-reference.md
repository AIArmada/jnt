---
title: API Reference
---

# API Reference

Complete method reference for the J&T Express Laravel package.

## Core Methods

### Create Order

```php
use AIArmada\Jnt\Facades\JntExpress;
use AIArmada\Jnt\Enums\{ExpressType, ServiceType, PaymentType, GoodsType};

$order = JntExpress::createOrderBuilder()
    ->orderId('ORDER-123')
    ->expressType(ExpressType::DOMESTIC)
    ->serviceType(ServiceType::DOOR_TO_DOOR)
    ->paymentType(PaymentType::PREPAID_POSTPAID)
    ->sender($senderAddress)
    ->receiver($receiverAddress)
    ->addItem($item)
    ->packageInfo($packageInfo)
    ->insurance(500.00)           // Optional
    ->cashOnDelivery(100.00)      // Optional
    ->remark('Handle with care')  // Optional
    ->build();

$result = JntExpress::createOrderFromArray($order);
```

**Returns:** `OrderData` with `trackingNumber` and `sortingCode`.

---

### Track Parcel

```php
// By order ID
$tracking = JntExpress::trackParcel(orderId: 'ORDER-123');

// Or by tracking number
$tracking = JntExpress::trackParcel(trackingNumber: 'JT630002864925');

echo $tracking->lastStatus;

foreach ($tracking->details as $detail) {
    echo "{$detail->scanTime}: {$detail->description}\n";
}
```

**Returns:** `TrackingData` with status history.

---

### Cancel Order

```php
use AIArmada\Jnt\Enums\CancellationReason;

JntExpress::cancelOrder(
    orderId: 'ORDER-123',
    reason: CancellationReason::OUT_OF_STOCK,
    trackingNumber: 'JT630002864925',  // Optional
);
```

---

### Print Waybill

```php
$label = JntExpress::printOrder(
    orderId: 'ORDER-123',
    trackingNumber: 'JT630002864925',
);

$pdfUrl = $label['urlContent'];
```

---

### Query Order

```php
$details = JntExpress::queryOrder('ORDER-123');

echo $details['orderStatus'];
echo $details['trackingNumber'];
```

---

## Batch Operations

Process multiple orders efficiently. See [batch-operations.md](batch-operations.md) for details.

```php
// Batch create
$results = JntExpress::batchCreateOrders([$order1, $order2, $order3]);

// Batch track
$results = JntExpress::batchTrackParcels(
    orderIds: ['ORDER-1', 'ORDER-2'],
    trackingNumbers: ['JT123', 'JT456'],
);

// Batch cancel
$results = JntExpress::batchCancelOrders(
    orderIds: ['ORDER-1', 'ORDER-2'],
    reason: CancellationReason::OUT_OF_STOCK,
);

// Batch print
$results = JntExpress::batchPrintWaybills(
    orderIds: ['ORDER-1'],
    trackingNumbers: ['JT123'],
);
```

All batch methods return:
```php
[
    'successful' => [...],
    'failed' => [
        ['orderId' => 'ORDER-X', 'error' => 'Message', 'exception' => $e],
    ],
]
```

---

## Data Objects

### AddressData

```php
use AIArmada\Jnt\Data\AddressData;

$address = new AddressData(
    name: 'John Doe',
    phone: '60123456789',
    address: '123 Main Street',
    postCode: '50000',
    state: 'Kuala Lumpur',
    city: 'KL',
    area: 'Bukit Bintang',     // Optional
    email: 'john@example.com', // Optional
);
```

### ItemData

```php
use AIArmada\Jnt\Data\ItemData;

$item = new ItemData(
    itemName: 'Product Name',
    quantity: 2,
    weight: 500,          // In grams
    unitPrice: 99.90,
    description: 'Desc',  // Optional
    currency: 'MYR',      // Optional
);
```

### PackageInfoData

```php
use AIArmada\Jnt\Data\PackageInfoData;
use AIArmada\Jnt\Enums\GoodsType;

$package = new PackageInfoData(
    quantity: 1,
    weight: 1.5,           // In kilograms
    declaredValue: 199.90,
    goodsType: GoodsType::PACKAGE,
    length: 30,            // Optional, in cm
    width: 20,             // Optional
    height: 15,            // Optional
);
```

### OrderData (Response)

```php
$order->orderId;           // Your order reference
$order->trackingNumber;    // J&T tracking number
$order->sortingCode;       // Warehouse sorting code
$order->chargeableWeight;  // Billable weight
```

### TrackingData (Response)

```php
$tracking->trackingNumber;  // J&T tracking number
$tracking->orderId;         // Your order reference
$tracking->lastStatus;      // Latest status
$tracking->scanTime;        // Latest timestamp
$tracking->details;         // All tracking events
$tracking->isDelivered();   // Check if delivered
$tracking->hasProblem();    // Check for issues
```

---

## Enums

### ExpressType

| Enum | API Value | Description |
|------|-----------|-------------|
| `DOMESTIC` | `EZ` | Standard delivery |
| `NEXT_DAY` | `EX` | Express next day |
| `FRESH` | `FD` | Cold chain delivery |

### ServiceType

| Enum | API Value | Description |
|------|-----------|-------------|
| `DOOR_TO_DOOR` | `1` | Pickup from sender |
| `WALK_IN` | `6` | Drop-off at counter |

### PaymentType

| Enum | API Value | Description |
|------|-----------|-------------|
| `PREPAID_POSTPAID` | `PP_PM` | Prepaid by merchant |
| `PREPAID_CASH` | `PP_CASH` | Cash prepaid |
| `COLLECT_CASH` | `CC_CASH` | Cash on delivery |

### GoodsType

| Enum | API Value | Description |
|------|-----------|-------------|
| `DOCUMENT` | `ITN2` | Documents |
| `PACKAGE` | `ITN8` | Parcels |

### CancellationReason

```php
CancellationReason::OUT_OF_STOCK
CancellationReason::CUSTOMER_CANCELLED
CancellationReason::WRONG_ADDRESS
CancellationReason::DUPLICATE_ORDER
CancellationReason::PRICE_ERROR
```

---

## Error Handling

### Exception Types

```php
use AIArmada\Jnt\Exceptions\{
    JntException,            // Base exception
    JntApiException,         // API errors (4xx/5xx)
    JntNetworkException,     // Network failures
    JntValidationException,  // Validation errors
    JntConfigurationException,
};

try {
    $order = JntExpress::createOrderFromArray($data);
} catch (JntValidationException $e) {
    $errors = $e->getErrors();
} catch (JntApiException $e) {
    $statusCode = $e->getStatusCode();
    $response = $e->getResponseData();
} catch (JntNetworkException $e) {
    Log::error('Network error', ['exception' => $e]);
} catch (JntException $e) {
    Log::error('JNT error', ['exception' => $e]);
}
```

### Common Error Codes

| Code | Meaning | Solution |
|------|---------|----------|
| 401 | Invalid credentials | Check API account and private key |
| 422 | Validation failed | Review request data |
| 500 | J&T server error | Retry with exponential backoff |

---

## Events

### TrackingStatusReceived

Dispatched when webhook receives a tracking update.

```php
use AIArmada\Jnt\Events\TrackingStatusReceived;

public function handle(TrackingStatusReceived $event): void
{
    $event->trackingNumber;   // J&T tracking number
    $event->orderId;          // Your order ID
    $event->lastStatus;       // Latest status
    $event->scanTime;         // Timestamp
    $event->allStatuses;      // All updates
    $event->isDelivered();    // true if delivered
    $event->hasProblem();     // true if issue
}
```

---

## Property Mappings

| Clean Name | API Name | Description |
|------------|----------|-------------|
| `orderId` | `txlogisticId` | Your order reference |
| `trackingNumber` | `billCode` | J&T tracking number |
| `state` | `prov` | State/province |
| `quantity` | `number` | Item quantity |
| `unitPrice` | `itemValue` | Price per item |
| `declaredValue` | `packageValue` | Declared value |
| `chargeableWeight` | `packageChargeWeight` | Billable weight |

The package handles translation automatically.
