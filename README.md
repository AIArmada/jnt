# J&T Express for Laravel

> Laravel 12 integration for [J&T Express Malaysia](https://www.jtexpress.my/) Open API – orders, tracking, waybills, and real-time webhooks.

## Why this package?

- **Complete API coverage** – create orders, track parcels, print waybills, cancel orders, batch operations.
- **Clean API naming** – use `orderId` instead of `txlogisticId`, `trackingNumber` instead of `billCode`.
- **Type-safe enums** – `ExpressType::DOMESTIC` instead of magic strings like `'EZ'`.
- **First-class Laravel DX** – facades, fluent builders, data objects, events, Artisan commands.
- **Production ready** – PHP 8.4 / Laravel 12, PHPStan level 6, Pest test suite.
- **Webhooks included** – automatic signature verification and event dispatching.

## Installation

```bash
composer require aiarmada/jnt
```

Publish configuration and migrations:

```bash
php artisan vendor:publish --tag="jnt-config"
php artisan vendor:publish --tag="jnt-migrations"
php artisan migrate
```

### Environment Variables

```env
JNT_ENVIRONMENT=testing  # or 'production'
JNT_CUSTOMER_CODE=your_customer_code
JNT_PASSWORD=your_password

# Production only (testing uses J&T's public sandbox credentials):
JNT_API_ACCOUNT=your_api_account
JNT_PRIVATE_KEY=your_private_key

# Optional
JNT_LOGGING_ENABLED=true
JNT_WEBHOOKS_ENABLED=true
```

> **Note:** When `JNT_ENVIRONMENT=testing`, the package automatically uses J&T's official sandbox credentials. You only need `JNT_CUSTOMER_CODE` and `JNT_PASSWORD`.

---

## Usage

### Create an Order

```php
use AIArmada\Jnt\Facades\JntExpress;
use AIArmada\Jnt\Data\{AddressData, ItemData, PackageInfoData};
use AIArmada\Jnt\Enums\{ExpressType, ServiceType, PaymentType, GoodsType};

$sender = new AddressData(
    name: 'John Sender',
    phone: '60123456789',
    address: 'No 32, Jalan Kempas 4',
    postCode: '81930',
    countryCode: 'MYS',
    state: 'Johor',
    city: 'Johor Bahru',
);

$receiver = new AddressData(
    name: 'Jane Receiver',
    phone: '60987654321',
    address: '4678, Laluan Sentang 35',
    postCode: '31000',
    countryCode: 'MYS',
    state: 'Perak',
    city: 'Batu Gajah',
);

$item = new ItemData(
    itemName: 'Basketball',
    quantity: 2,
    weight: 10,
    unitPrice: 50.00,
);

$packageInfo = new PackageInfoData(
    quantity: 1,
    weight: 10,
    declaredValue: 50,
    goodsType: GoodsType::PACKAGE,
);

$order = JntExpress::createOrderBuilder()
    ->orderId('ORDER-' . time())
    ->expressType(ExpressType::DOMESTIC)
    ->serviceType(ServiceType::DOOR_TO_DOOR)
    ->paymentType(PaymentType::PREPAID_POSTPAID)
    ->sender($sender)
    ->receiver($receiver)
    ->addItem($item)
    ->packageInfo($packageInfo)
    ->build();

$result = JntExpress::createOrderFromArray($order);

echo "Tracking: " . $result->trackingNumber;
```

### Track a Parcel

```php
// By your order ID
$tracking = JntExpress::trackParcel(orderId: 'ORDER-123');

// Or by J&T tracking number
$tracking = JntExpress::trackParcel(trackingNumber: 'JT630002864925');

foreach ($tracking->details as $detail) {
    echo "{$detail->scanTime}: {$detail->description}\n";
}
```

### Cancel an Order

```php
JntExpress::cancelOrder(
    orderId: 'ORDER-123',
    reason: 'Customer requested cancellation',
    trackingNumber: 'JT630002864925',
);
```

### Print Waybill

```php
$label = JntExpress::printOrder(
    orderId: 'ORDER-123',
    trackingNumber: 'JT630002864925',
);

$pdfUrl = $label['urlContent'];
```

---

## Enums

Type-safe enums prevent invalid values:

```php
// Express Type
ExpressType::DOMESTIC   // Standard delivery
ExpressType::NEXT_DAY   // Express next day
ExpressType::FRESH      // Cold chain delivery

// Service Type
ServiceType::DOOR_TO_DOOR  // Pickup from sender
ServiceType::WALK_IN       // Drop-off at counter

// Payment Type
PaymentType::PREPAID_POSTPAID  // Prepaid by merchant
PaymentType::COLLECT_CASH      // Cash on delivery

// Goods Type
GoodsType::DOCUMENT  // Documents
GoodsType::PACKAGE   // Parcels
```

---

## Webhooks

Receive real-time tracking updates from J&T.

### Setup

1. **Enable webhooks** in `.env`:
   ```env
   JNT_WEBHOOKS_ENABLED=true
   ```

2. **Create a listener**:
   ```php
   namespace App\Listeners;

   use AIArmada\Jnt\Events\TrackingStatusReceived;

   class UpdateOrderTracking
   {
       public function handle(TrackingStatusReceived $event): void
       {
           $order = Order::where('tracking_number', $event->trackingNumber)->first();
           
           $order?->update([
               'tracking_status' => $event->lastStatus,
               'tracking_time' => $event->scanTime,
           ]);
       }
   }
   ```

3. **Register the listener**:
   ```php
   // EventServiceProvider.php
   protected $listen = [
       \AIArmada\Jnt\Events\TrackingStatusReceived::class => [
           \App\Listeners\UpdateOrderTracking::class,
       ],
   ];
   ```

4. **Configure J&T Dashboard** with your webhook URL:
   ```
   https://yourdomain.com/webhooks/jnt/status
   ```

---

## Artisan Commands

```bash
# Check configuration
php artisan jnt:config:check

# Health check
php artisan jnt:health

# Track parcel
php artisan jnt:order:track --order-id=ORDER-123

# Cancel order
php artisan jnt:order:cancel --order-id=ORDER-123 --reason="Out of stock"

# Print waybill
php artisan jnt:order:print --order-id=ORDER-123 --tracking-number=JT123456
```

---

## Documentation

- [API Reference](docs/api-reference.md) – Complete method reference
- [Batch Operations](docs/batch-operations.md) – Process multiple orders efficiently
- [Webhooks](docs/webhooks.md) – Webhook integration guide
- [Testing Credentials](docs/testing-credentials.md) – Auto-configuration for sandbox

---

## Property Name Mapping

The package translates clean names to J&T's API format automatically:

| Your Code | J&T API | Description |
|-----------|---------|-------------|
| `orderId` | `txlogisticId` | Your order reference |
| `trackingNumber` | `billCode` | J&T tracking number |
| `state` | `prov` | State/province |
| `quantity` | `number` | Item quantity |
| `chargeableWeight` | `packageChargeWeight` | Billable weight |

---

## Testing

```bash
vendor/bin/pest tests/src/Jnt
```

---

## Contributing

1. Fork & clone the repository
2. Install dependencies: `composer install`
3. Run tests: `vendor/bin/pest`
4. Format code: `vendor/bin/pint`

---

## License

Released under the MIT License. See [LICENSE](LICENSE) for details.
