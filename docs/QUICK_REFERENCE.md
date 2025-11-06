# JNT Package - Quick Reference Card

## 🎯 Property Name Cheat Sheet

| What You Need | Clean Name | Old API Name |
|---|---|---|
| **Your order reference** | `orderId` | `txlogisticId` |
| **J&T tracking number** | `trackingNumber` | `billCode` |
| **State/Province** | `state` | `prov` |
| **Item quantity** | `quantity` | `number` |
| **Price per item** | `unitPrice` | `itemValue` |
| **Item description** | `description` | `itemDesc` |
| **Currency code** | `currency` | `itemCurrency` |
| **Package quantity** | `quantity` | `packageQuantity` |
| **Declared value** | `declaredValue` | `packageValue` |
| **Actual weight** | `actualWeight` | `realWeight` |
| **Billable weight** | `chargeableWeight` | `packageChargeWeight` |
| **Description** | `description` | `desc` |
| **Signature image** | `signaturePictureUrl` | `sigPicUrl` |
| **Additional tracking** | `additionalTrackingNumbers` | `multipleVoteBillCodes` |

## 📦 Enum Quick Reference

### ExpressType
```php
ExpressType::DOMESTIC   // 'EZ' - Standard domestic delivery
ExpressType::NEXT_DAY   // 'EX' - Express next day delivery
ExpressType::FRESH      // 'FD' - Fresh/cold chain delivery
```

### ServiceType
```php
ServiceType::DOOR_TO_DOOR  // '1' - Pick up from sender
ServiceType::WALK_IN       // '6' - Drop off at J&T counter
```

### PaymentType
```php
PaymentType::PREPAID_POSTPAID  // 'PP_PM' - Prepaid by you, postpaid by merchant
PaymentType::PREPAID_CASH      // 'PP_CASH' - Prepaid with cash
PaymentType::COLLECT_CASH      // 'CC_CASH' - Cash on delivery (COD)
```

### GoodsType
```php
GoodsType::DOCUMENT  // 'ITN2' - Documents
GoodsType::PACKAGE   // 'ITN8' - Packages/parcels
```

## 🚀 Common Tasks

### Create an Order
```php
use AIArmada\Jnt\Enums\{ExpressType, ServiceType, PaymentType, GoodsType};

$order = JntExpress::createOrderBuilder()
    ->orderId('ORD-'.time())
    ->expressType(ExpressType::DOMESTIC)
    ->serviceType(ServiceType::DOOR_TO_DOOR)
    ->paymentType(PaymentType::PREPAID_POSTPAID)
    ->sender($sender)
    ->receiver($receiver)
    ->addItem($item)
    ->packageInfo($packageInfo)
    ->build();
```

### Create an Item
```php
$item = new ItemData(
    itemName: 'Basketball',
    quantity: 2,
    weight: 10,
    unitPrice: 50.00
);
```

### Create Package Info
```php
$packageInfo = new PackageInfoData(
    quantity: 1,
    weight: 10,
    declaredValue: 50,
    goodsType: GoodsType::PACKAGE
);
```

### Create Address
```php
$address = new AddressData(
    name: 'John Doe',
    phone: '60123456789',
    address: 'No 32, Jalan Kempas 4',
    postCode: '81930',
    state: 'Johor',
    city: 'Johor Bahru'
);
```

### Track Order
```php
// By your order ID
$tracking = JntExpress::trackParcel(orderId: 'ORD-123');

// By J&T tracking number
$tracking = JntExpress::trackParcel(trackingNumber: 'JT123456789');

// Access details
foreach ($tracking->details as $detail) {
    echo $detail->scanTime . ': ' . $detail->description;
}
```

### Cancel Order
```php
JntExpress::cancelOrder(
    orderId: 'ORD-123',
    reason: 'Customer requested',
    trackingNumber: 'JT123456789' // Optional
);
```

### Print Label
```php
$label = JntExpress::printOrder(
    orderId: 'ORD-123',
    trackingNumber: 'JT123456789'
);

// Get PDF URL
$url = $label['urlContent'];
```

##  Pro Tips

1. **Use Enums Everywhere**
   ```php
   // ❌ Don't
   ->expressType('EZ')
   
   // ✅ Do
   ->expressType(ExpressType::DOMESTIC)
   ```

2. **Let Your IDE Help**
   - Type `ExpressType::` and see all options
   - Type `$order->` and see all properties
   - Autocomplete prevents typos

3. **Type Hints Are Your Friend**
   ```php
   function createShipment(
       ExpressType $type,  // Only accepts valid types!
       int $quantity,      // Only accepts numbers!
   ) { }
   ```

4. **Read Error Messages**
   ```php
   // Old: "Invalid express type: EXZ"
   // New: Type error at compile time!
   ```

5. **Use Named Parameters**
   ```php
   // ✅ Clear and explicit
   new ItemData(
       itemName: 'Ball',
       quantity: 2,
       unitPrice: 50.00,
       weight: 10
   );
   
   // ❌ Hard to understand
   new ItemData('Ball', 2, 50.00, 10);
   ```

## 🆘 Common Issues

### "Property doesn't exist"
**Problem:** Using old property names
```php
echo $order->billCode; // ❌ Error
```
**Solution:** Use new clean names
```php
echo $order->trackingNumber; // ✅ Works
```

### "Type error with enum"
**Problem:** Passing string to enum parameter
```php
->expressType('EZ'); // ❌ If method expects enum
```
**Solution:** Use the enum
```php
->expressType(ExpressType::DOMESTIC); // ✅ Works
```

### "I need the old API format"
**Problem:** You're integrating with something that needs old names
```php
$order->orderId; // Gives clean name
```
**Solution:** Use `toApiArray()`
```php
$apiData = $order->toApiArray();
echo $apiData['txlogisticId']; // Old format
```

## 📚 Full Documentation

- **README.md** - Complete guide with examples
- **CHANGELOG.md** - All changes documented
- **BEFORE_AFTER_COMPARISON.md** - Detailed comparisons
- **IMPLEMENTATION_SUMMARY.md** - Technical details

## 🤝 Need Help?

1. Check enum definitions: `src/Enums/`
2. Read the README examples
3. Look at test files: `tests/Unit/OrderBuilderTest.php`
4. Check this quick reference!

---

**Remember:** You write clean code, the package handles the messy API! 🎉
