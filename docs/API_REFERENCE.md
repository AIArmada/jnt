# J&T Express API Reference

Complete method reference for the J&T Express Laravel package.

## Quick Links

- [Core Methods](#core-methods)
- [Batch Operations](#batch-operations)  
- [Data Objects](#data-objects)
- [Enums](#enums)
- [Events](#events)
- [Commands](#artisan-commands)

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
    ->cashOnDelivery(100.00)      // Optional for COD
    ->remark('Fragile items')     // Optional
    ->build();

$result = JntExpress::createOrderFromArray($order);
```

**Returns:** `OrderData` with tracking number and sorting code

### Track Parcel

```php
// Track by your order ID
$tracking = JntExpress::trackParcel(orderId: 'ORDER-123');

// Or by J&T tracking number
$tracking = JntExpress::trackParcel(trackingNumber: 'JT630002864925');

// Access details
echo $tracking->lastStatus;
foreach ($tracking->details as $detail) {
    echo "{$detail->scanTime}: {$detail->description}\n";
}
```

**Returns:** `TrackingData` with status history

### Cancel Order

```php
use AIArmada\Jnt\Enums\CancellationReason;

JntExpress::cancelOrder(
    orderId: 'ORDER-123',
    reason: CancellationReason::OUT_OF_STOCK,
    trackingNumber: 'JT630002864925'  // Optional
);
```

**Returns:** `array` with cancellation confirmation

### Print Waybill

```php
$label = JntExpress::printOrder(
    orderId: 'ORDER-123',
    trackingNumber: 'JT630002864925'
);

// Get PDF URL
$pdfUrl = $label['urlContent'];
```

**Returns:** `array` with PDF URL

### Query Order

```php
$details = JntExpress::queryOrder('ORDER-123');

echo $details['orderStatus'];     // Order status
echo $details['trackingNumber'];  // J&T tracking number
```

**Returns:** `array` with order details

## Batch Operations

Process multiple orders efficiently. See [BATCH_OPERATIONS.md](BATCH_OPERATIONS.md) for details.

### Batch Create

```php
$results = JntExpress::batchCreateOrders([
    $orderData1,
    $orderData2,
    $orderData3,
]);

// Returns: ['successful' => [...], 'failed' => [...]]
```

### Batch Track

```php
$results = JntExpress::batchTrackParcels(
    orderIds: ['ORDER-1', 'ORDER-2'],
    trackingNumbers: ['JT123', 'JT456']
);
```

### Batch Cancel

```php
$results = JntExpress::batchCancelOrders(
    orderIds: ['ORDER-1', 'ORDER-2'],
    reason: CancellationReason::OUT_OF_STOCK
);
```

### Batch Print

```php
$results = JntExpress::batchPrintWaybills(
    orderIds: ['ORDER-1', 'ORDER-2'],
    trackingNumbers: ['JT123', 'JT456']
);
```

## Data Objects

### AddressData

```php
use AIArmada\Jnt\Data\AddressData;

$address = new AddressData(
    name: 'John Doe',
    phone: '60123456789',
    address: '123 Main Street',
    postCode: '50000',
    state: 'Kuala Lumpur',        // Clean name for 'prov'
    city: 'KL',
    area: 'Bukit Bintang',        // Optional
    email: 'john@example.com'     // Optional
);
```

### ItemData

```php
use AIArmada\Jnt\Data\ItemData;

$item = new ItemData(
    name: 'Product Name',         // Clean name for 'itemName'
    quantity: 2,                  // Clean name for 'number'
    weight: 500,                  // In grams
    price: 99.90,                 // Clean name for 'itemValue'
    description: 'Product desc',  // Optional
    currency: 'MYR'              // Optional
);
```

### PackageInfoData

```php
use AIArmada\Jnt\Data\PackageInfoData;
use AIArmada\Jnt\Enums\GoodsType;

$package = new PackageInfoData(
    quantity: 1,                  // Number of packages
    weight: 1.5,                  // In kilograms
    value: 199.90,                // Declared value
    goodsType: GoodsType::PACKAGE,
    length: 30,                   // Optional, in cm
    width: 20,                    // Optional, in cm
    height: 15                    // Optional, in cm
);
```

### OrderData (Response)

```php
$order->orderId;              // Your order reference
$order->trackingNumber;       // J&T tracking number
$order->sortingCode;          // Sorting code for warehouse
$order->chargeableWeight;     // Billable weight
$order->toArray();            // Convert to array
```

### TrackingData (Response)

```php
$tracking->trackingNumber;    // J&T tracking number
$tracking->orderId;           // Your order reference
$tracking->lastStatus;        // Latest status description
$tracking->scanTime;          // Latest scan timestamp
$tracking->details;           // Array of all tracking events
$tracking->isDelivered();     // Check if delivered
$tracking->hasProblem();      // Check if has issues
```

## Enums

### ExpressType

```php
use AIArmada\Jnt\Enums\ExpressType;

ExpressType::DOMESTIC   // 'EZ' - Standard delivery
ExpressType::NEXT_DAY   // 'EX' - Express next day
ExpressType::FRESH      // 'FD' - Cold chain delivery
```

### ServiceType

```php
use AIArmada\Jnt\Enums\ServiceType;

ServiceType::DOOR_TO_DOOR  // '1' - Pickup from sender
ServiceType::WALK_IN       // '6' - Drop-off at counter
```

### PaymentType

```php
use AIArmada\Jnt\Enums\PaymentType;

PaymentType::PREPAID_POSTPAID  // 'PP_PM' - Most common
PaymentType::PREPAID_CASH      // 'PP_CASH' - Cash prepaid
PaymentType::COLLECT_CASH      // 'CC_CASH' - COD
```

### GoodsType

```php
use AIArmada\Jnt\Enums\GoodsType;

GoodsType::DOCUMENT  // 'ITN2' - Documents
GoodsType::PACKAGE   // 'ITN8' - Parcels
```

### CancellationReason

```php
use AIArmada\Jnt\Enums\CancellationReason;

CancellationReason::OUT_OF_STOCK
CancellationReason::CUSTOMER_CANCELLED
CancellationReason::WRONG_ADDRESS
CancellationReason::DUPLICATE_ORDER
CancellationReason::PRICE_ERROR
// ... see enum file for all options
```

## Events

### TrackingStatusReceived

Dispatched when webhook receives tracking update.

```php
use AIArmada\Jnt\Events\TrackingStatusReceived;

// In your listener
public function handle(TrackingStatusReceived $event): void
{
    $event->trackingNumber;      // J&T tracking number
    $event->orderId;             // Your order ID
    $event->lastStatus;          // Latest status
    $event->scanTime;            // Timestamp
    $event->allStatuses;         // All status updates
    $event->isDelivered();       // true if delivered
    $event->hasProblem();        // true if issue
}
```

See [WEBHOOKS.md](WEBHOOKS.md) for webhook setup.

## Artisan Commands

### Check Configuration

```bash
php artisan jnt:config:check

# Check specific environment
php artisan jnt:config:check --env=sandbox
```

### Create Order (CLI)

```bash
php artisan jnt:order:create \
    --order-id=ORDER-123 \
    --sender-name="John Sender" \
    --sender-phone=60123456789 \
    --receiver-name="Jane Receiver" \
    --receiver-phone=60198765432
    
# See all options
php artisan jnt:order:create --help
```

### Track Parcel (CLI)

```bash
# Track by order ID
php artisan jnt:order:track --order-id=ORDER-123

# Track by tracking number
php artisan jnt:order:track --tracking-number=JT630002864925
```

### Cancel Order (CLI)

```bash
php artisan jnt:order:cancel \
    --order-id=ORDER-123 \
    --reason="Out of stock" \
    --tracking-number=JT630002864925
```

### Print Waybill (CLI)

```bash
php artisan jnt:order:print \
    --order-id=ORDER-123 \
    --tracking-number=JT630002864925
```

## Error Handling

### Exception Types

```php
use AIArmada\Jnt\Exceptions\{
    JntException,           // Base exception
    JntApiException,        // API errors (4xx/5xx)
    JntNetworkException,    // Network failures
    JntValidationException, // Validation errors
    JntConfigurationException // Config errors
};

try {
    $order = JntExpress::createOrderFromArray($data);
} catch (JntValidationException $e) {
    // Handle validation errors
    $errors = $e->getErrors();
} catch (JntApiException $e) {
    // Handle API errors
    $statusCode = $e->getStatusCode();
    $response = $e->getResponseData();
} catch (JntNetworkException $e) {
    // Handle network failures
    Log::error('JNT network error', ['exception' => $e]);
} catch (JntException $e) {
    // Handle any JNT error
    Log::error('JNT error', ['exception' => $e]);
}
```

### Common Error Codes

| Code | Meaning | Solution |
|------|---------|----------|
| 401 | Invalid credentials | Check API account and private key |
| 422 | Validation failed | Review request data |
| 500 | J&T server error | Retry with exponential backoff |
| -1 | Network timeout | Check connectivity, increase timeout |

## Testing

### HTTP Faking

```php
use Illuminate\Support\Facades\Http;

test('creates order successfully', function () {
    Http::fake([
        '*gate.jtexpress.my*' => Http::response([
            'code' => '0',
            'msg' => 'success',
            'data' => [
                'billCode' => 'JT123456',
                'sortingCode' => 'KL-001',
            ],
        ], 200),
    ]);

    $result = JntExpress::createOrderFromArray($orderData);
    
    expect($result->trackingNumber)->toBe('JT123456');
});
```

### Sandbox Environment

**Quick setup:** When `JNT_ENVIRONMENT=testing`, the package automatically uses J&T's official public testing credentials:

```env
JNT_ENVIRONMENT=testing

# These are auto-configured for testing (optional to set):
# JNT_API_ACCOUNT=640826271705595946
# JNT_PRIVATE_KEY=8e88c8477d4e4939859c560192fcafbc

# You only need to provide:
JNT_CUSTOMER_CODE=your_customer_code
JNT_PASSWORD=your_password
```

For production, you must explicitly set all credentials from your J&T Distribution Partner.

## Field Name Mappings

The package uses clean, intuitive field names that are automatically converted to J&T's API format:

| Clean Name | API Name | Description |
|------------|----------|-------------|
| `orderId` | `txlogisticId` | Your order reference |
| `trackingNumber` | `billCode` | J&T tracking number |
| `state` | `prov` | State/province |
| `quantity` (item) | `number` | Item quantity |
| `price` | `itemValue` | Item unit price |
| `quantity` (package) | `packageQuantity` | Package count |
| `value` | `packageValue` | Declared value |
| `chargeableWeight` | `packageChargeWeight` | Billable weight |

You write clean code, the package handles the API translation. See [QUICK_REFERENCE.md](QUICK_REFERENCE.md) for more mappings.

## Related Documentation

- [README.md](../README.md) - Package overview and installation
- [BATCH_OPERATIONS.md](BATCH_OPERATIONS.md) - Batch processing guide
- [WEBHOOKS.md](WEBHOOKS.md) - Webhook integration guide
- [QUICK_REFERENCE.md](QUICK_REFERENCE.md) - Quick reference cheat sheet
