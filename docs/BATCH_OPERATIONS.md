# Batch Operations Guide

Process multiple orders efficiently with batch operations. All batch methods return both successful and failed results for partial success handling.

## Overview

All batch methods follow this pattern:

```php
[
    'successful' => [/* array of successful results */],
    'failed' => [
        [
            'orderId' => 'ORDER-123',
            'error' => 'Error message',
            'exception' => ExceptionObject,  // For debugging
        ],
    ]
]
```

## Batch Create Orders

Create multiple orders in one call.

```php
use AIArmada\Jnt\Facades\JntExpress;

$ordersData = [
    [
        'orderId' => 'ORDER-1',
        'sender' => $senderAddress,
        'receiver' => $receiverAddress1,
        'items' => [$item1],
        'packageInfo' => $package1,
    ],
    [
        'orderId' => 'ORDER-2',
        'sender' => $senderAddress,
        'receiver' => $receiverAddress2,
        'items' => [$item2],
        'packageInfo' => $package2,
    ],
];

$result = JntExpress::batchCreateOrders($ordersData);

// Process results
foreach ($result['successful'] as $order) {
    echo "✓ {$order->orderId} → {$order->trackingNumber}\n";
}

foreach ($result['failed'] as $failure) {
    echo "✗ {$failure['orderId']}: {$failure['error']}\n";
    logger()->error('Order creation failed', $failure);
}
```

**Returns:**
- `successful`: Array of `OrderData` objects
- `failed`: Array with orderId, error message, and exception

## Batch Track Parcels

Track multiple parcels by order IDs or tracking numbers.

```php
// Track by order IDs
$result = JntExpress::batchTrackParcels(
    orderIds: ['ORDER-1', 'ORDER-2', 'ORDER-3']
);

// Track by tracking numbers
$result = JntExpress::batchTrackParcels(
    trackingNumbers: ['JT123456', 'JT789012']
);

// Track both
$result = JntExpress::batchTrackParcels(
    orderIds: ['ORDER-1', 'ORDER-2'],
    trackingNumbers: ['JT123456']
);

// Process results
foreach ($result['successful'] as $tracking) {
    echo "{$tracking->trackingNumber}: {$tracking->lastStatus}\n";
    
    if ($tracking->isDelivered()) {
        // Notify customer
    }
}

foreach ($result['failed'] as $failure) {
    echo "Failed to track {$failure['identifier']}\n";
}
```

**Returns:**
- `successful`: Array of `TrackingData` objects
- `failed`: Array with type, identifier, error, and exception

## Batch Cancel Orders

Cancel multiple orders with the same cancellation reason.

```php
use AIArmada\Jnt\Enums\CancellationReason;

$result = JntExpress::batchCancelOrders(
    orderIds: ['ORDER-1', 'ORDER-2', 'ORDER-3'],
    reason: CancellationReason::OUT_OF_STOCK
);

// Or with custom reason
$result = JntExpress::batchCancelOrders(
    orderIds: ['ORDER-4', 'ORDER-5'],
    reason: 'Customer requested address change'
);

echo "Cancelled: " . count($result['successful']) . " orders\n";
echo "Failed: " . count($result['failed']) . " orders\n";
```

**Returns:**
- `successful`: Array of API response data
- `failed`: Array with orderId, error, and exception

## Batch Print Waybills

Print waybills for multiple orders.

```php
// Print by order IDs
$result = JntExpress::batchPrintWaybills(
    orderIds: ['ORDER-1', 'ORDER-2']
);

// Print by tracking numbers
$result = JntExpress::batchPrintWaybills(
    trackingNumbers: ['JT123456', 'JT789012']
);

// Print both
$result = JntExpress::batchPrintWaybills(
    orderIds: ['ORDER-1'],
    trackingNumbers: ['JT123456']
);

// Download PDFs
foreach ($result['successful'] as $label) {
    $pdfUrl = $label['urlContent'];
    // Download or store URL
}
```

**Returns:**
- `successful`: Array of label data with PDF URLs
- `failed`: Array with identifier, error, and exception

## Error Handling

### Partial Success Pattern

```php
$result = JntExpress::batchCreateOrders($orders);

if (empty($result['failed'])) {
    // All succeeded
    logger()->info('All orders created', [
        'count' => count($result['successful'])
    ]);
} else {
    // Partial success
    logger()->warning('Some orders failed', [
        'successful' => count($result['successful']),
        'failed' => count($result['failed']),
    ]);
    
    // Retry failed orders
    $retryData = array_column($result['failed'], 'orderId');
    // ... implement retry logic
}
```

### Retry Failed Operations

```php
function createOrdersWithRetry(array $orders, int $maxRetries = 3): array
{
    $allSuccessful = [];
    $remainingOrders = $orders;
    
    for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
        $result = JntExpress::batchCreateOrders($remainingOrders);
        
        $allSuccessful = array_merge($allSuccessful, $result['successful']);
        
        if (empty($result['failed'])) {
            break; // All succeeded
        }
        
        // Prepare failed orders for retry
        $remainingOrders = array_filter(
            $orders,
            fn($order) => in_array(
                $order['orderId'],
                array_column($result['failed'], 'orderId')
            )
        );
        
        if ($attempt < $maxRetries) {
            sleep(pow(2, $attempt)); // Exponential backoff
        }
    }
    
    return [
        'successful' => $allSuccessful,
        'failed' => $result['failed'] ?? [],
    ];
}
```

## Performance Tips

### Optimal Batch Sizes

```php
// Good: Process 50-100 orders per batch
$batches = array_chunk($orders, 50);

foreach ($batches as $batch) {
    $result = JntExpress::batchCreateOrders($batch);
    // Process results
}

// Avoid: Processing thousands at once
// Will cause timeouts and memory issues
```

### Parallel Processing

```php
use Illuminate\Support\Facades\Bus;
use App\Jobs\CreateJntOrder;

// Dispatch jobs in parallel
$jobs = collect($orders)->map(fn($order) => 
    new CreateJntOrder($order)
);

Bus::batch($jobs)->dispatch();
```

### Progress Tracking

```php
$total = count($orders);
$processed = 0;

foreach (array_chunk($orders, 50) as $batch) {
    $result = JntExpress::batchCreateOrders($batch);
    $processed += count($batch);
    
    echo "Progress: {$processed}/{$total} (" . 
         round(($processed / $total) * 100) . "%)\n";
}
```

## Integration Examples

### CSV Import

```php
use League\Csv\Reader;

$csv = Reader::createFromPath('orders.csv');
$orders = [];

foreach ($csv->getRecords() as $record) {
    $orders[] = [
        'orderId' => $record['order_id'],
        'sender' => /* ... build from CSV ... */,
        'receiver' => /* ... build from CSV ... */,
        // ... other fields
    ];
}

$result = JntExpress::batchCreateOrders($orders);

// Export results
$successCsv = Writer::createFromPath('success.csv', 'w+');
$failedCsv = Writer::createFromPath('failed.csv', 'w+');

foreach ($result['successful'] as $order) {
    $successCsv->insertOne([
        $order->orderId,
        $order->trackingNumber,
        $order->sortingCode,
    ]);
}

foreach ($result['failed'] as $failure) {
    $failedCsv->insertOne([
        $failure['orderId'],
        $failure['error'],
    ]);
}
```

### Scheduled Batch Processing

```php
// app/Console/Commands/ProcessPendingOrders.php
class ProcessPendingOrders extends Command
{
    public function handle()
    {
        $orders = Order::where('status', 'pending')
            ->limit(100)
            ->get();
        
        $ordersData = $orders->map(fn($order) => [
            'orderId' => $order->reference,
            'sender' => $order->sender_address,
            'receiver' => $order->receiver_address,
            'items' => $order->items,
            'packageInfo' => $order->package_info,
        ])->toArray();
        
        $result = JntExpress::batchCreateOrders($ordersData);
        
        // Update successful orders
        foreach ($result['successful'] as $jntOrder) {
            Order::where('reference', $jntOrder->orderId)->update([
                'status' => 'shipped',
                'tracking_number' => $jntOrder->trackingNumber,
            ]);
        }
        
        // Log failures
        foreach ($result['failed'] as $failure) {
            Order::where('reference', $failure['orderId'])->update([
                'status' => 'failed',
                'error_message' => $failure['error'],
            ]);
        }
        
        $this->info("Processed: " . count($result['successful']));
        $this->error("Failed: " . count($result['failed']));
    }
}
```

### Register in scheduler:

```php
// routes/console.php
Schedule::command('orders:process-pending')
    ->everyFiveMinutes()
    ->withoutOverlapping();
```

## Best Practices

1. **Use appropriate batch sizes** (50-100 orders)
2. **Implement retry logic** for failed operations
3. **Log all failures** for manual review
4. **Use queues** for large batches
5. **Monitor performance** and adjust batch sizes
6. **Handle partial success** gracefully
7. **Provide progress feedback** for long operations

## Related Documentation

- [API_REFERENCE.md](API_REFERENCE.md) - Single operation methods
- [WEBHOOKS.md](WEBHOOKS.md) - Webhook integration
- [README.md](../README.md) - Package overview
