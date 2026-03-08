---
title: Batch Operations
---

# Batch Operations

Process multiple orders efficiently with batch operations. All batch methods return both successful and failed results for partial success handling.

## Response Format

All batch methods return:

```php
[
    'successful' => [...],
    'failed' => [
        [
            'orderId' => 'ORDER-123',
            'error' => 'Error message',
            'exception' => $e,
        ],
    ],
]
```

---

## Batch Create Orders

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

foreach ($result['successful'] as $order) {
    echo "✓ {$order->orderId} → {$order->trackingNumber}\n";
}

foreach ($result['failed'] as $failure) {
    echo "✗ {$failure['orderId']}: {$failure['error']}\n";
}
```

---

## Batch Track Parcels

```php
// By order IDs
$result = JntExpress::batchTrackParcels(
    orderIds: ['ORDER-1', 'ORDER-2', 'ORDER-3']
);

// By tracking numbers
$result = JntExpress::batchTrackParcels(
    trackingNumbers: ['JT123456', 'JT789012']
);

// Both
$result = JntExpress::batchTrackParcels(
    orderIds: ['ORDER-1', 'ORDER-2'],
    trackingNumbers: ['JT123456']
);

foreach ($result['successful'] as $tracking) {
    echo "{$tracking->trackingNumber}: {$tracking->lastStatus}\n";
    
    if ($tracking->isDelivered()) {
        // Handle delivery
    }
}
```

---

## Batch Cancel Orders

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

---

## Batch Print Waybills

```php
// By order IDs
$result = JntExpress::batchPrintWaybills(
    orderIds: ['ORDER-1', 'ORDER-2']
);

// By tracking numbers
$result = JntExpress::batchPrintWaybills(
    trackingNumbers: ['JT123456', 'JT789012']
);

foreach ($result['successful'] as $label) {
    $pdfUrl = $label['urlContent'];
}
```

---

## Error Handling

### Partial Success

```php
$result = JntExpress::batchCreateOrders($orders);

if (empty($result['failed'])) {
    logger()->info('All orders created', [
        'count' => count($result['successful']),
    ]);
} else {
    logger()->warning('Some orders failed', [
        'successful' => count($result['successful']),
        'failed' => count($result['failed']),
    ]);
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
            break;
        }
        
        $failedIds = array_column($result['failed'], 'orderId');
        $remainingOrders = array_filter(
            $orders,
            fn($order) => in_array($order['orderId'], $failedIds)
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

---

## Best Practices

### Optimal Batch Sizes

```php
// Process 50-100 orders per batch
$batches = array_chunk($orders, 50);

foreach ($batches as $batch) {
    $result = JntExpress::batchCreateOrders($batch);
}
```

### Queue Processing

```php
use Illuminate\Support\Facades\Bus;
use App\Jobs\CreateJntOrder;

$jobs = collect($orders)->map(
    fn($order) => new CreateJntOrder($order)
);

Bus::batch($jobs)->dispatch();
```

### Scheduled Batch Processing

```php
// app/Console/Commands/ProcessPendingOrders.php
class ProcessPendingOrders extends Command
{
    public function handle(): void
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
        
        foreach ($result['successful'] as $jntOrder) {
            Order::where('reference', $jntOrder->orderId)->update([
                'status' => 'shipped',
                'tracking_number' => $jntOrder->trackingNumber,
            ]);
        }
        
        $this->info("Processed: " . count($result['successful']));
        $this->error("Failed: " . count($result['failed']));
    }
}
```

Register in scheduler:

```php
// routes/console.php
Schedule::command('orders:process-pending')
    ->everyFiveMinutes()
    ->withoutOverlapping();
```

---

## Summary

1. **Use appropriate batch sizes** (50-100 orders)
2. **Implement retry logic** for failed operations
3. **Log failures** for manual review
4. **Use queues** for large batches
5. **Handle partial success** gracefully
