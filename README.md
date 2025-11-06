# J&T Express Malaysia API Integration Package

A modern, type-safe Laravel package for integrating with J&T Express Malaysia Open API with **clean, intuitive property names** and **type-safe enums**.

## Features

- ✅ **Clean, developer-friendly API** - No more confusing property names like `txlogisticId`
- ✅ **Type-safe enums** - Use `ExpressType::DOMESTIC` instead of magic strings like `'EZ'`
- ✅ **Automatic API translation** - Clean names internally, J&T format for API calls
- ✅ Create orders with comprehensive validation
- ✅ Query order details
- ✅ Cancel orders
- ✅ Print AWB labels
- ✅ Track parcels
- ✅ **Batch operations** - Create, track, cancel, and print multiple orders at once
- ✅ Webhook support for tracking updates
- ✅ Automatic signature generation and verification
- ✅ Retry logic for failed requests (including 5xx errors)
- ✅ Comprehensive logging with sensitive data masking
- ✅ Laravel HTTP Client integration for better testing
- ✅ Type-safe data objects with PHP 8.4
- ✅ Testing and production environments
- ✅ PHPStan level 6 compliant
- ✅ Comprehensive test suite with Pest (312 tests)

## 📚 Documentation

- **[API Reference](docs/API_REFERENCE.md)** - Complete method reference
- **[Batch Operations](docs/BATCH_OPERATIONS.md)** - Process multiple orders efficiently
- **[Webhooks](docs/WEBHOOKS.md)** - Webhook integration and troubleshooting
- **[Quick Reference](docs/QUICK_REFERENCE.md)** - Cheat sheet for enums and field names

## Installation

```bash
composer require aiarmada/jnt
```

Publish the configuration file:

```bash
php artisan vendor:publish --tag=jnt-config
```

## Configuration

Add the following to your `.env` file:

```env
JNT_ENVIRONMENT=testing  # or 'production'

# Required: Get these from your J&T Distribution Partner
JNT_CUSTOMER_CODE=your_customer_code
JNT_PASSWORD=your_password

# Optional: Testing environment uses J&T's public credentials by default
# JNT_API_ACCOUNT=640826271705595946
# JNT_PRIVATE_KEY=8e88c8477d4e4939859c560192fcafbc

# Optional settings
JNT_LOGGING_ENABLED=true
JNT_LOGGING_CHANNEL=stack
JNT_WEBHOOK_ENABLED=true
JNT_WEBHOOK_VERIFY_SIGNATURE=true
```

### Testing Credentials

**Good news!** When `JNT_ENVIRONMENT=testing`, the package automatically uses J&T's official public testing credentials. You only need to provide:

```env
JNT_ENVIRONMENT=testing
JNT_CUSTOMER_CODE=your_customer_code
JNT_PASSWORD=your_password
```

The testing `API_ACCOUNT` and `PRIVATE_KEY` are pre-configured with J&T's public sandbox credentials:
- API Account: `640826271705595946`
- Private Key: `8e88c8477d4e4939859c560192fcafbc`

**For production**, you must explicitly provide all credentials from your J&T Distribution Partner.

## Usage

### Creating an Order

```php
use AIArmada\Jnt\Facades\JntExpress;
use AIArmada\Jnt\Data\{AddressData, ItemData, PackageInfoData};
use AIArmada\Jnt\Enums\{ExpressType, ServiceType, PaymentType, GoodsType};

// Create sender address
$sender = new AddressData(
    name: 'John Sender',
    phone: '60123456789',
    address: 'No 32, Jalan Kempas 4',
    postCode: '81930',
    countryCode: 'MYS',
    state: 'Johor',              // ✨ Clean name (was 'prov')
    city: 'Bandar Penawar',
    area: 'Taman Desaru Utama'
);

// Create receiver address
$receiver = new AddressData(
    name: 'Jane Receiver',
    phone: '60987654321',
    address: '4678, Laluan Sentang 35',
    postCode: '31000',
    countryCode: 'MYS',
    state: 'Perak',
    city: 'Batu Gajah',
    area: 'Kampung Seri Mariah'
);

// Create items
$item = new ItemData(
    itemName: 'Basketball',
    quantity: 2,                 // ✨ Clear (was 'number')
    weight: 10,
    unitPrice: 50.00,            // ✨ Clear (was 'itemValue')
    englishName: 'Basketball',
    description: 'Sports equipment', // ✨ Full word (was 'itemDesc')
    currency: 'MYR'              // ✨ Short & clear (was 'itemCurrency')
);

// Create package info
$packageInfo = new PackageInfoData(
    quantity: 1,                 // ✨ Short & clear (was 'packageQuantity')
    weight: 10,
    declaredValue: 50,           // ✨ Purpose-clear (was 'packageValue')
    goodsType: GoodsType::PACKAGE, // ✨ Type-safe enum (was 'ITN8')
    length: 30,
    width: 20,
    height: 15
);

// Build and create order
$order = JntExpress::createOrderBuilder()
    ->orderId('ORDER-'.time())                    // ✨ Clear (was 'txlogisticId')
    ->expressType(ExpressType::DOMESTIC)          // ✨ Type-safe enum
    ->serviceType(ServiceType::DOOR_TO_DOOR)      // ✨ Type-safe enum
    ->paymentType(PaymentType::PREPAID_POSTPAID) // ✨ Type-safe enum
    ->sender($sender)
    ->receiver($receiver)
    ->addItem($item)
    ->packageInfo($packageInfo)
    ->insurance(50.00)                            // Optional
    ->cashOnDelivery(100.00)                      // ✨ Clear (was 'cod')
    ->remark('Handle with care')                  // Optional
    ->build();

$order = JntExpress::createOrderFromArray($order);

// Access order details with clean names
echo "Order ID: " . $order->orderId;                  // Your reference
echo "Tracking Number: " . $order->trackingNumber;    // J&T tracking number
echo "Chargeable Weight: " . $order->chargeableWeight; // Billing weight
```

### Available Enums

#### ExpressType
```php
ExpressType::DOMESTIC   // 'EZ' - Domestic Standard
ExpressType::NEXT_DAY   // 'EX' - Express Next Day  
ExpressType::FRESH      // 'FD' - Fresh Delivery
```

#### ServiceType
```php
ServiceType::DOOR_TO_DOOR  // '1' - Door to Door
ServiceType::WALK_IN       // '6' - Walk-In
```

#### PaymentType
```php
PaymentType::PREPAID_POSTPAID  // 'PP_PM' - Prepaid, Postpaid by Merchant
PaymentType::PREPAID_CASH      // 'PP_CASH' - Prepaid Cash
PaymentType::COLLECT_CASH      // 'CC_CASH' - Cash on Delivery
```

#### GoodsType
```php
GoodsType::DOCUMENT  // 'ITN2' - Document
GoodsType::PACKAGE   // 'ITN8' - Package
```

### Query Order

```php
$orderDetails = JntExpress::queryOrder('ORDER-123456789');
```

### Cancel Order

```php
$result = JntExpress::cancelOrder(
    orderId: 'ORDER-123456789',           // ✨ Clear name
    reason: 'Customer requested cancellation',
    trackingNumber: '630002864925'        // ✨ Clear name (was 'billCode')
);
```

### Print AWB Label

```php
$label = JntExpress::printOrder(
    orderId: 'ORDER-123456789',
    trackingNumber: '630002864925',
    templateName: null // Optional
);

// Get PDF URL
$pdfUrl = $label['urlContent'];
```

### Track Parcel

```php
// Track by orderId (your reference)
$tracking = JntExpress::trackParcel(orderId: 'ORDER-123456789');

// Or track by trackingNumber (J&T waybill number)
$tracking = JntExpress::trackParcel(trackingNumber: '630002864925');

// Access tracking details with clean names
echo "Tracking: " . $tracking->trackingNumber;  // J&T tracking number
echo "Order ID: " . $tracking->orderId;         // Your reference

foreach ($tracking->details as $detail) {
    echo $detail->scanTime . ': ' . $detail->description; // ✨ Clear (was 'desc')
    echo "Weight: " . $detail->actualWeight;              // ✨ Clear (was 'realWeight')
}
```

### Webhooks - Automatic Tracking Updates

Receive real-time tracking status updates from J&T automatically.

#### Quick Setup

1. **Configure environment:**
```env
JNT_WEBHOOKS_ENABLED=true
JNT_WEBHOOK_LOG_PAYLOADS=false  # Enable for debugging only
```

2. **Listen to tracking events:**
```php
// app/Providers/EventServiceProvider.php
protected $listen = [
    \AIArmada\Jnt\Events\TrackingStatusReceived::class => [
        \App\Listeners\UpdateOrderTracking::class,
    ],
];
```

3. **Handle tracking updates:**
```php
namespace App\Listeners;

use AIArmada\Jnt\Events\TrackingStatusReceived;
use App\Models\Order;

class UpdateOrderTracking
{
    public function handle(TrackingStatusReceived $event): void
    {
        $order = Order::where('tracking_number', $event->getBillCode())->first();
        
        if (!$order) {
            return;
        }

        $order->update([
            'tracking_status' => $event->getLatestStatus(),
            'tracking_description' => $event->getLatestDescription(),
            'tracking_location' => $event->getLatestLocation(),
            'tracking_updated_at' => $event->getLatestTimestamp(),
        ]);

        // Notify customer if delivered
        if ($event->isDelivered()) {
            $order->user->notify(new OrderDelivered($order));
        }
    }
}
```

4. **Configure webhook URL in J&T Dashboard:**
```
https://yourdomain.com/webhooks/jnt/status
```

That's it! Your application will now automatically receive and process tracking updates.

#### Features
- ✅ **Automatic signature verification** - Middleware validates all webhooks
- ✅ **Type-safe event data** - Access webhook data through clean helper methods
- ✅ **Queue support** - Process webhooks asynchronously with `ShouldQueue`
- ✅ **Status detection** - Built-in helpers: `isDelivered()`, `isCollected()`, `hasProblem()`
- ✅ **Comprehensive logging** - Configurable webhook payload logging

#### Learn More

📚 **Detailed Documentation:**
- [Webhook Usage Guide](docs/WEBHOOKS_USAGE.md) - Complete setup and configuration
- [Integration Examples](docs/WEBHOOK_INTEGRATION_EXAMPLES.md) - 7 production-ready examples
- [Troubleshooting Guide](docs/WEBHOOK_TROUBLESHOOTING.md) - Common issues and solutions

## Testing

```bash
# Run all tests
vendor/bin/pest

# Run specific test file
vendor/bin/pest tests/Feature/JntExpressServiceTest.php

# Run with coverage
vendor/bin/pest --coverage
```

## API Translation

The package automatically translates between clean property names and J&T API format:

**Your Code (Clean):**
```php
$order = new OrderData(
    orderId: 'ORDER-123',
    trackingNumber: '630002864925',
    chargeableWeight: '12.5'
);
```

**Sent to J&T API (Translated):**
```json
{
  "txlogisticId": "ORDER-123",
  "billCode": "630002864925",
  "packageChargeWeight": "12.5"
}
```

This happens automatically! You never need to deal with confusing API names. 🎉

## Property Name Reference

| Clean Name (Your Code) | API Name (J&T) | Description |
|---|---|---|
| `orderId` | `txlogisticId` | Your order reference number |
| `trackingNumber` | `billCode` | J&T waybill/tracking number |
| `state` | `prov` | State/province |
| `quantity` | `number` | Item quantity |
| `unitPrice` | `itemValue` | Price per item |
| `description` | `desc` / `itemDesc` | Description text |
| `currency` | `itemCurrency` | Currency code |
| `declaredValue` | `packageValue` | Declared value for customs |
| `actualWeight` | `realWeight` | Actual measured weight |
| `chargeableWeight` | `packageChargeWeight` | Billable weight |
| `signaturePictureUrl` | `sigPicUrl` | Delivery signature image |
| `additionalTrackingNumbers` | `multipleVoteBillCodes` | Multi-parcel tracking numbers |

## Requirements

- PHP 8.4+
- Laravel 12+

## Contributing

Contributions are welcome! Please ensure:
- All tests pass
- Code follows PSR-12 standards (run `vendor/bin/pint`)
- PHPStan passes at level 6

## License

MIT License - see LICENSE file for details.

## Credits

- Developed by AIArmada
- J&T Express Malaysia API documentation
- Laravel community

## Documentation

### 📚 Comprehensive Guides

- **[API Reference](docs/API_REFERENCE.md)** - Complete package documentation with all methods, data objects, enums, events, and examples
- **[Integration Testing Guide](docs/INTEGRATION_TESTING.md)** - Sandbox setup, test suite, and CI/CD integration
- **[Batch Operations Guide](docs/BATCH_OPERATIONS.md)** - Process multiple orders efficiently with batch methods
- **[Webhook Integration Examples](docs/WEBHOOK_INTEGRATION_EXAMPLES.md)** - 7 production-ready webhook examples
- **[Webhook Usage Guide](docs/WEBHOOKS_USAGE.md)** - Complete webhook setup and configuration
- **[Quick Reference](docs/QUICK_REFERENCE.md)** - Common operations at a glance
- **[Type System Explained](docs/TYPE_SYSTEM_EXPLAINED.md)** - Understanding type transformations

### 📖 Technical Documentation

- **[100% Completion Report](docs/100_PERCENT_COMPLETE.md)** 🎉 - Package completion milestone (312 tests)
- **[Complete API Gap Analysis](docs/COMPLETE_API_GAP_ANALYSIS.md)** - Package completeness status (100%)
- **[Phase 5 Completion Report](docs/PHASE_5_COMPLETION_REPORT.md)** - Laravel integration features
- **[Post-Phase 5 Improvements](docs/POST_PHASE_5_IMPROVEMENTS.md)** - Test correctness, Spatie integration, property naming
- **[Optional Enhancements](docs/OPTIONAL_ENHANCEMENTS_COMPLETE.md)** - Production validation improvements

### 🔧 Development Resources

- **[Testing Guidelines](.ai/testing.md)** - How to write and run tests
- **[Package Development Guidelines](.ai/package-development.md)** - Development best practices
- **[CHIP Integration Guidelines](.ai/chip.md)** - Payment gateway integration (if needed)

## Support

- **Issues:** [GitHub Issues](https://github.com/aiarmada/jnt/issues)
- **Questions:** Check the [API Reference](docs/API_REFERENCE.md) first
- **Integration Help:** See [Integration Testing Guide](docs/INTEGRATION_TESTING.md)
