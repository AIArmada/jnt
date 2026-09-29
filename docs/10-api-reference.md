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
    ->insurance(50000)            // Optional: MYR minor units
    ->cashOnDelivery(10000)       // Optional: MYR minor units
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

echo $tracking->getLatestStatus();  // Latest scan type, e.g. "SIGN"

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

Process multiple orders efficiently. See [batch operations guide](07-batch-operations.md) for details.

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
    templateName: null,
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
    name: 'Product Name',
    quantity: 2,
    weight: 500,          // In grams
    priceMinor: 9990,     // MYR minor units (sen)
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
    valueMinor: 19990,     // MYR minor units (sen)
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
$tracking->trackingNumber;        // J&T tracking number
$tracking->orderId;               // Your order reference
$tracking->details;               // DataCollection of TrackingDetailData
$tracking->getLatestDetail();     // Most recent TrackingDetailData, or null
$tracking->getLatestStatus();     // Latest scan type, e.g. "SIGN"
$tracking->getLatestLocation();   // Latest network name
$tracking->isDelivered();         // Check if delivered
```

> **info**
> `TrackingData` has no `hasProblem()` helper. Exemption is derived per event with `$detail->isInTransit()` / `isDelivered()`, or from the persisted order via `JntOrder::hasProblem()`.

---

## Enums

### ExpressType

| Enum | API Value | Description |
|------|-----------|-------------|
| `DOMESTIC` | `EZ` | Standard delivery |
| `NEXT_DAY` | `EX` | Express next day |
| `FRESH` | `FD` | Cold chain delivery |
| `DOOR_TO_DOOR` | `DO` | Door to door |
| `SAME_DAY` | `JS` | Same day |

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
CancellationReason::CUSTOMER_REQUEST
CancellationReason::CUSTOMER_CHANGED_MIND
CancellationReason::CUSTOMER_ORDERED_BY_MISTAKE
CancellationReason::CUSTOMER_FOUND_BETTER_PRICE
CancellationReason::OUT_OF_STOCK
CancellationReason::INCORRECT_PRICING
CancellationReason::UNABLE_TO_FULFILL
CancellationReason::DUPLICATE_ORDER
CancellationReason::INCORRECT_ADDRESS
CancellationReason::ADDRESS_NOT_SERVICEABLE
CancellationReason::DELIVERY_NOT_AVAILABLE
CancellationReason::PAYMENT_FAILED
CancellationReason::PAYMENT_PENDING_TOO_LONG
CancellationReason::SYSTEM_ERROR
CancellationReason::OTHER
```

`cancelOrder()` and `batchCancelOrders()` accept either the enum or a free-form
string reason.

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
    $errors = $e->errors;   // array<string, array<string>> keyed by field
    $field = $e->field;
} catch (JntApiException $e) {
    $errorCode = $e->errorCode;   // e.g. 'AUTH_ERROR', 'INVALID_RESPONSE'
    $response = $e->apiResponse; // raw decoded J&T payload
    $endpoint = $e->endpoint;
} catch (JntNetworkException $e) {
    Log::error('Network error', ['exception' => $e]);
} catch (JntException $e) {
    Log::error('JNT error', ['exception' => $e]);
}
```

Exception detail is exposed as public readonly properties, not getters.

### Common Error Codes

J&T reports failures as a business `code` in the response body, not as HTTP
status codes. The package maps them onto `AIArmada\Jnt\Enums\ErrorCode`:

| `ErrorCode` | Meaning | Solution |
|------|---------|----------|
| `145003010` / `145003012` | API account missing or unauthorised | Check `JNT_API_ACCOUNT` and its interface permissions |
| `145003030` | Signature verification failed | Check `JNT_PRIVATE_KEY` and the `digest` header |
| `145003052` / `145003053` | Missing `digest` / `timestamp` header | Both headers are sent by `JntClient` |
| `145003050` | Illegal parameters | Review the `bizContent` payload |
| `999001010` / `999001011` / `999001012` | Missing `customerCode` / `password` / `txlogisticId` | Check `JNT_CUSTOMER_CODE`, `JNT_PASSWORD`, and the order ID |
| `999001030` / `999002000` | Order or tracking number not found | Verify the reference |
| `999002010` | Order cannot be cancelled in its current state | Only pending orders can be cancelled |

A non-`1` body code raises `JntApiException`. Genuine HTTP 4xx/5xx responses
raise `JntNetworkException` with the status on `$e->httpStatus`.

---

## Events

### TrackingUpdatedEvent

Dispatched when the webhook processor receives a tracking update.

```php
use AIArmada\Jnt\Events\TrackingUpdatedEvent;

public function handle(TrackingUpdatedEvent $event): void
{
    $event->getTrackingNumber(); // J&T tracking number
    $event->getOrderId();        // Local order reference, when supplied
    $event->getLatestStatus();   // Latest typed scan status
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
| `priceMinor` | `itemValue` | Price per item in MYR minor units, emitted as a J&T decimal string |
| `valueMinor` | `packageValue` | Declared value in MYR minor units, emitted as a J&T decimal string |
| `chargeableWeight` | `packageChargeWeight` | Billable weight |

The package handles translation automatically.
