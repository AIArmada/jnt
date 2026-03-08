---
title: Usage
---

# Usage

This guide covers creating, managing, and printing orders with J&T Express.

## Cart Shipping Conditions

When `aiarmada/cart` is installed, the JNT package registers a cart condition provider. Store a
shipping address in cart metadata and read totals to apply a JNT shipping condition.

```php
use AIArmada\Cart\Facades\Cart;

Cart::setMetadata('jnt_shipping_address', [
    'name' => 'John Doe',
    'phone' => '60198765432',
    'address' => '456 Customer Road',
    'postCode' => '81100',
    'city' => 'Johor Bahru',
    'state' => 'Johor',
]);

$total = Cart::total();
```

## Creating Orders

### Using the Fluent Builder

The recommended way to create orders is using the `OrderBuilder`:

```php
use AIArmada\Jnt\Facades\JntExpress;
use AIArmada\Jnt\Data\AddressData;
use AIArmada\Jnt\Data\ItemData;
use AIArmada\Jnt\Data\PackageInfoData;
use AIArmada\Jnt\Enums\ExpressType;
use AIArmada\Jnt\Enums\ServiceType;
use AIArmada\Jnt\Enums\PaymentType;
use AIArmada\Jnt\Enums\GoodsType;

// Create addresses
$sender = new AddressData(
    name: 'Store Name',
    phone: '60123456789',
    address: '123 Main Street, Unit 5',
    postCode: '50000',
    city: 'Kuala Lumpur',
    state: 'Kuala Lumpur',
);

$receiver = new AddressData(
    name: 'John Doe',
    phone: '60198765432',
    address: '456 Customer Road',
    postCode: '81100',
    city: 'Johor Bahru',
    state: 'Johor',
    email: 'john@example.com',
);

// Create item(s)
$item = new ItemData(
    name: 'Wireless Mouse',
    quantity: 2,
    weight: 200,       // Weight in grams
    price: 49.90,      // Unit price in MYR
    description: 'Bluetooth wireless mouse',
);

// Create package info
$packageInfo = new PackageInfoData(
    quantity: 1,
    weight: 0.5,       // Total weight in kilograms
    value: 99.80,      // Declared value in MYR
    goodsType: GoodsType::PACKAGE,
    length: 15,        // Dimensions in cm (optional)
    width: 10,
    height: 5,
);

// Build and create the order
$order = JntExpress::createOrderBuilder()
    ->orderId('ORDER-2024-001')
    ->expressType(ExpressType::DOMESTIC)
    ->serviceType(ServiceType::DOOR_TO_DOOR)
    ->paymentType(PaymentType::PREPAID_POSTPAID)
    ->sender($sender)
    ->receiver($receiver)
    ->addItem($item)
    ->packageInfo($packageInfo)
    ->remark('Handle with care')
    ->build();

$result = JntExpress::createOrderFromArray($order);

// Access the result
echo $result->trackingNumber;  // e.g., "JT630002864925"
echo $result->orderId;         // "ORDER-2024-001"
echo $result->sortingCode;     // Warehouse sorting code
```

### Using Data Objects Directly

For more control, use the service directly:

```php
use AIArmada\Jnt\Services\JntExpressService;

$jntService = app(JntExpressService::class);

$result = $jntService->createOrder(
    sender: $sender,
    receiver: $receiver,
    items: [$item],
    packageInfo: $packageInfo,
    orderId: 'ORDER-2024-001',
    additionalData: [
        'remark' => 'Handle with care',
    ],
);
```

### Using Action Classes

The package provides action classes for dependency injection:

```php
use AIArmada\Jnt\Actions\Orders\CreateOrder;

class OrderController
{
    public function store(CreateOrder $createOrder)
    {
        $result = $createOrder->handle(
            sender: $sender,
            receiver: $receiver,
            items: [$items],
            packageInfo: $packageInfo,
            orderId: $orderId,
        );
        
        return response()->json([
            'tracking_number' => $result->trackingNumber,
        ]);
    }
}
```

## Address Data

The `AddressData` object represents a sender or receiver address:

```php
use AIArmada\Jnt\Data\AddressData;

$address = new AddressData(
    name: 'John Doe',           // Required: Contact name (max 100 chars)
    phone: '60123456789',       // Required: Phone with country code
    address: '123 Main Street', // Required: Full address (max 200 chars)
    postCode: '50000',          // Required: 5-digit Malaysian postcode
    city: 'Kuala Lumpur',       // Optional: City name
    state: 'Kuala Lumpur',      // Optional: State/province
    area: 'Bukit Bintang',      // Optional: District/area
    countryCode: 'MYS',         // Optional: ISO country code (default: MYS)
    email: 'john@example.com',  // Optional: Email for notifications
);
```

## Item Data

The `ItemData` object represents individual items in a shipment:

```php
use AIArmada\Jnt\Data\ItemData;

$item = new ItemData(
    name: 'Product Name',        // Required: Item name (max 200 chars)
    quantity: 2,                 // Required: Quantity (1-9999999)
    weight: 500,                 // Required: Weight per unit in GRAMS
    price: 49.90,                // Required: Unit price in MYR
    englishName: 'Product',      // Optional: English name for customs
    description: 'Description',  // Optional: Item description (max 500 chars)
    currency: 'MYR',             // Optional: Currency code (default: MYR)
);

// Helper methods
$item->getTotalValue();  // Returns 99.80 (price × quantity)
$item->getTotalWeight(); // Returns 1000 (weight × quantity)
```

## Package Info Data

The `PackageInfoData` object describes the package dimensions:

```php
use AIArmada\Jnt\Data\PackageInfoData;
use AIArmada\Jnt\Enums\GoodsType;

$package = new PackageInfoData(
    quantity: 1,                    // Required: Number of packages
    weight: 1.5,                    // Required: Total weight in KILOGRAMS
    value: 199.90,                  // Required: Declared value in MYR
    goodsType: GoodsType::PACKAGE,  // Required: DOCUMENT or PACKAGE
    length: 30,                     // Optional: Length in cm
    width: 20,                      // Optional: Width in cm
    height: 15,                     // Optional: Height in cm
);

// Helper methods
$package->hasAllDimensions();     // true if all dimensions provided
$package->getVolumetricWeight();  // Volumetric weight in kg
$package->getChargeableWeight();  // Max of actual/volumetric
$package->isDocument();           // true if goods type is DOCUMENT
```

## Cash on Delivery (COD)

To enable COD payment:

```php
$order = JntExpress::createOrderBuilder()
    ->orderId('ORDER-COD-001')
    ->sender($sender)
    ->receiver($receiver)
    ->addItem($item)
    ->packageInfo($packageInfo)
    ->paymentType(PaymentType::COLLECT_CASH)
    ->cashOnDelivery(199.90)  // COD amount in MYR
    ->build();
```

## Insurance

To add shipment insurance:

```php
$order = JntExpress::createOrderBuilder()
    ->orderId('ORDER-INS-001')
    ->sender($sender)
    ->receiver($receiver)
    ->addItem($item)
    ->packageInfo($packageInfo)
    ->insurance(500.00)  // Insurance value in MYR
    ->build();
```

## Cancelling Orders

Cancel an order before pickup:

```php
use AIArmada\Jnt\Facades\JntExpress;
use AIArmada\Jnt\Enums\CancellationReason;

$result = JntExpress::cancelOrder(
    orderId: 'ORDER-2024-001',
    reason: CancellationReason::CUSTOMER_REQUEST,
    trackingNumber: 'JT630002864925',  // Optional
);
```

### Cancellation Reasons

```php
use AIArmada\Jnt\Enums\CancellationReason;

// Customer-initiated
CancellationReason::CUSTOMER_REQUEST
CancellationReason::CUSTOMER_CHANGED_MIND
CancellationReason::CUSTOMER_ORDERED_BY_MISTAKE
CancellationReason::CUSTOMER_FOUND_BETTER_PRICE

// Merchant-initiated
CancellationReason::OUT_OF_STOCK
CancellationReason::INCORRECT_PRICING
CancellationReason::UNABLE_TO_FULFILL
CancellationReason::DUPLICATE_ORDER

// Delivery issues
CancellationReason::INCORRECT_ADDRESS
CancellationReason::ADDRESS_NOT_SERVICEABLE
CancellationReason::DELIVERY_NOT_AVAILABLE

// Payment issues
CancellationReason::PAYMENT_FAILED
CancellationReason::PAYMENT_PENDING_TOO_LONG

// Other
CancellationReason::SYSTEM_ERROR
CancellationReason::OTHER

// Helper methods
$reason->getDescription();         // Human-readable description
$reason->requiresCustomerContact(); // Should notify customer?
$reason->isMerchantResponsibility();
$reason->isCustomerResponsibility();
$reason->getCategory();            // "Customer-Initiated", etc.
```

## Printing Waybills

Generate a shipping label/waybill:

```php
use AIArmada\Jnt\Facades\JntExpress;

$result = JntExpress::printOrder(
    orderId: 'ORDER-2024-001',
    trackingNumber: 'JT630002864925',
    templateName: null,  // Optional: specific template
);

// For single parcels
if (isset($result['base64Content'])) {
    $pdfContent = base64_decode($result['base64Content']);
    file_put_contents('waybill.pdf', $pdfContent);
}

// For multi-parcel (returns URL)
if (isset($result['urlContent'])) {
    $downloadUrl = $result['urlContent'];
}
```

### Using PrintWaybillData

The response is also available as a data object:

```php
use AIArmada\Jnt\Data\PrintWaybillData;

$waybill = PrintWaybillData::fromApiArray($result);

$waybill->hasBase64Content();  // true for single parcel
$waybill->hasUrlContent();     // true for multi-parcel
$waybill->savePdf('/path/to/waybill.pdf');
$waybill->getPdfContent();     // Binary PDF content
$waybill->getPdfSize();        // Size in bytes
$waybill->getFormattedSize();  // "1.5 MB"
$waybill->isValidPdf();        // Validates PDF magic number
```

## Querying Orders

Query order details from J&T:

```php
$details = JntExpress::queryOrder('ORDER-2024-001');

echo $details['orderStatus'];
echo $details['trackingNumber'];
echo $details['chargeableWeight'];
```

## Validation Rules

The package provides custom validation rules:

```php
use AIArmada\Jnt\Rules\MalaysianPostalCode;
use AIArmada\Jnt\Rules\PhoneNumber;
use AIArmada\Jnt\Rules\WeightInKilograms;
use AIArmada\Jnt\Rules\WeightInGrams;
use AIArmada\Jnt\Rules\DimensionInCentimeters;
use AIArmada\Jnt\Rules\MonetaryValue;

$validated = $request->validate([
    'post_code' => ['required', new MalaysianPostalCode],
    'phone' => ['required', new PhoneNumber],
    'weight_kg' => ['required', new WeightInKilograms],
    'weight_g' => ['required', new WeightInGrams],
    'length' => ['required', new DimensionInCentimeters],
    'price' => ['required', new MonetaryValue],
]);
```

## Express Types

```php
use AIArmada\Jnt\Enums\ExpressType;

ExpressType::DOMESTIC    // 'EZ' - Standard delivery
ExpressType::NEXT_DAY    // 'EX' - Express next day
ExpressType::FRESH       // 'FD' - Fresh/cold chain
ExpressType::DOOR_TO_DOOR // 'DO' - Door to door
ExpressType::SAME_DAY    // 'JS' - Same day

$type->label();  // Human-readable label
$type->value;    // API value ('EZ', 'EX', etc.)
```

## Service Types

```php
use AIArmada\Jnt\Enums\ServiceType;

ServiceType::DOOR_TO_DOOR  // '1' - Pickup from sender
ServiceType::WALK_IN       // '6' - Drop-off at counter
```

## Payment Types

```php
use AIArmada\Jnt\Enums\PaymentType;

PaymentType::PREPAID_POSTPAID  // Prepaid by merchant
PaymentType::PREPAID_CASH      // Cash prepaid
PaymentType::COLLECT_CASH      // Cash on delivery
```

## Error Handling

```php
use AIArmada\Jnt\Exceptions\JntException;
use AIArmada\Jnt\Exceptions\JntApiException;
use AIArmada\Jnt\Exceptions\JntNetworkException;
use AIArmada\Jnt\Exceptions\JntValidationException;
use AIArmada\Jnt\Exceptions\JntConfigurationException;

try {
    $result = JntExpress::createOrderFromArray($order);
} catch (JntValidationException $e) {
    // Validation failed
    $errors = $e->getData();
    $message = $e->getMessage();
} catch (JntApiException $e) {
    // API returned an error
    $errorCode = $e->getErrorCode();
    $response = $e->apiResponse;
    $endpoint = $e->endpoint;
} catch (JntNetworkException $e) {
    // Network/connection error
    Log::error('Network error', ['exception' => $e]);
} catch (JntConfigurationException $e) {
    // Missing/invalid configuration
    Log::error('Config error: ' . $e->getMessage());
} catch (JntException $e) {
    // Generic JNT error
    Log::error('JNT error: ' . $e->getMessage());
}
```

## API Error Codes

The `ErrorCode` enum provides all known J&T API error codes:

```php
use AIArmada\Jnt\Enums\ErrorCode;

$error = ErrorCode::fromCode(145003030);

$error->getMessage();     // "Headers signature verification failed"
$error->getDescription(); // Detailed troubleshooting info
$error->isRetryable();    // Should retry?
$error->isClientError();  // 4xx-equivalent?
$error->getCategory();    // "Authentication", "Validation", etc.
```
