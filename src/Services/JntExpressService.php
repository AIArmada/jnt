<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Services;

use AIArmada\Jnt\Builders\OrderBuilder;
use AIArmada\Jnt\Data\AddressData;
use AIArmada\Jnt\Data\ItemData;
use AIArmada\Jnt\Data\OrderData;
use AIArmada\Jnt\Data\PackageInfoData;
use AIArmada\Jnt\Data\TrackingData;
use AIArmada\Jnt\Enums\CancellationReason;
use AIArmada\Jnt\Exceptions\JntApiException;
use AIArmada\Jnt\Exceptions\JntConfigurationException;
use AIArmada\Jnt\Exceptions\JntValidationException;
use AIArmada\Jnt\Http\JntClient;
use AIArmada\Jnt\Support\FieldNameConverter;
use Illuminate\Support\Facades\Concurrency;
use Throwable;

class JntExpressService
{
    protected ?JntClient $client = null;

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        protected readonly ?string $customerCode,
        protected readonly ?string $password,
        protected readonly array $config = [],
    ) {
        // Client is now lazy-loaded on first use
    }

    /**
     * Option 1: Builder Pattern (for complex orders)
     */
    public function createOrderBuilder(): OrderBuilder
    {
        return new OrderBuilder($this->getCustomerCode(), $this->getPassword());
    }

    /**
     * Option 2: Direct method with data objects (type-safe)
     *
     * @param  array<ItemData>  $items
     * @param  array<string, mixed>  $additionalData
     */
    public function createOrder(
        AddressData $sender,
        AddressData $receiver,
        array $items,
        PackageInfoData $packageInfo,
        ?string $orderId = null,
        array $additionalData = [],
    ): OrderData {
        $customerCode = $this->getCustomerCode();
        $password = $this->getPassword();

        $orderData = [
            'txlogisticId' => $orderId ?? 'TXN-' . time(),
            'actionType' => 'add',
            'serviceType' => '1',
            'payType' => 'PP_PM',
            'expressType' => 'EZ',
            'customerCode' => $customerCode,
            'password' => $password,
            'sender' => $sender->toApiArray(),
            'receiver' => $receiver->toApiArray(),
            'items' => array_map(fn (ItemData $item): array => $item->toApiArray(), $items),
            'packageInfo' => $packageInfo->toApiArray(),
            ...$additionalData,
        ];

        return $this->createOrderFromArray($orderData);
    }

    /**
     * Option 3: Array passthrough (quick prototyping, less type safety)
     *
     * @param  array<string, mixed>  $orderData
     */
    public function createOrderFromArray(array $orderData): OrderData
    {
        $response = $this->getClient()->post('/api/order/addOrder', $orderData);

        return OrderData::fromApiArray($response['data']);
    }

    /**
     * @return array<string, mixed>
     */
    public function queryOrder(string $orderId): array
    {
        $response = $this->getClient()->post('/api/order/getOrders', [
            'customerCode' => $this->getCustomerCode(),
            'password' => $this->getPassword(),
            'txlogisticId' => $orderId,
        ]);

        return $response['data'];
    }

    /**
     * Cancel an order.
     *
     * Cancels an existing order. Accepts either a predefined CancellationReason enum
     * or a custom string reason. Using the enum provides better type safety and
     * business logic helpers (requiresCustomerContact, isMerchantResponsibility, etc.).
     *
     * @param  string  $orderId  The merchant's order ID (txlogisticId)
     * @param  CancellationReason|string  $reason  Cancellation reason (enum recommended)
     * @param  string|null  $trackingNumber  Optional J&T tracking number (billCode)
     * @return array<string, mixed> Response data from API
     *
     * @throws JntApiException If API returns error
     *
     * @example
     * ```php
     * // Using predefined enum (recommended)
     * $result = $service->cancelOrder('ORDER123', CancellationReason::OUT_OF_STOCK);
     *
     * // Check if customer contact required
     * if (CancellationReason::OUT_OF_STOCK->requiresCustomerContact()) {
     *     // Notify customer about cancellation
     * }
     *
     * // Using custom string (for flexibility)
     * $result = $service->cancelOrder('ORDER123', 'Custom cancellation reason');
     * ```
     */
    /**
     * @return array<string, mixed>
     */
    public function cancelOrder(string $orderId, CancellationReason | string $reason, ?string $trackingNumber = null): array
    {
        $payload = [
            'customerCode' => $this->getCustomerCode(),
            'password' => $this->getPassword(),
            'txlogisticId' => $orderId,
            'reason' => $reason instanceof CancellationReason ? $reason->value : $reason,
        ];

        if ($trackingNumber !== null) {
            $payload['billCode'] = $trackingNumber;
        }

        $response = $this->getClient()->post('/api/order/cancelOrder', $payload);

        return $response['data'];
    }

    /**
     * @return array<string, mixed>
     */
    public function printOrder(string $orderId, ?string $trackingNumber = null, ?string $templateName = null): array
    {
        $payload = [
            'customerCode' => $this->getCustomerCode(),
            'password' => $this->getPassword(),
            'txlogisticId' => $orderId,
        ];

        if ($trackingNumber !== null) {
            $payload['billCode'] = $trackingNumber;
        }

        if ($templateName !== null) {
            $payload['templateName'] = $templateName;
        }

        $response = $this->getClient()->post('/api/order/printOrder', $payload);

        return $response['data'];
    }

    public function trackParcel(?string $orderId = null, ?string $trackingNumber = null): TrackingData
    {
        if ($orderId === null && $trackingNumber === null) {
            throw JntValidationException::requiredFieldMissing('orderId or trackingNumber');
        }

        $payload = [
            'customerCode' => $this->getCustomerCode(),
            'password' => $this->getPassword(),
        ];

        if ($orderId !== null) {
            $payload['txlogisticId'] = $orderId;
        }

        if ($trackingNumber !== null) {
            $payload['billCode'] = $trackingNumber;
        }

        $response = $this->getClient()->post('/api/logistics/trace', $payload);

        return TrackingData::fromApiArray($response['data']);
    }

    /**
     * @param  array<string, mixed>  $webhookData
     * @return array<TrackingData>
     */
    public function parseWebhookPayload(array $webhookData): array
    {
        if (! isset($webhookData['bizContent'])) {
            throw JntValidationException::requiredFieldMissing('bizContent');
        }

        $bizContent = is_string($webhookData['bizContent'])
            ? json_decode($webhookData['bizContent'], true)
            : $webhookData['bizContent'];

        if (! is_array($bizContent)) {
            throw JntValidationException::invalidFormat('bizContent', 'valid JSON array', gettype($bizContent));
        }

        return array_map(
            fn (array $item): TrackingData => TrackingData::fromApiArray($item),
            $bizContent
        );
    }

    /**
     * Batch create multiple orders.
     *
     * Creates multiple orders in a single operation with intelligent error handling.
     * Returns both successful orders and failed attempts for processing.
     *
     * Accepts clean field names (orderId, trackingNumber) which are automatically
     * converted to J&T API format internally for consistency with the rest of the package.
     *
     * @param  array<array<string, mixed>>  $ordersData  Array of order data arrays with clean field names
     * @return array{successful: array<OrderData>, failed: array<array{orderId: string, error: string, exception: Throwable}>}
     *
     * @example
     * ```php
     * $orders = [
     *     ['orderId' => 'ORDER1', 'sender' => [...], ...],
     *     ['orderId' => 'ORDER2', 'sender' => [...], ...],
     * ];
     *
     * $result = $service->batchCreateOrders($orders);
     *
     * foreach ($result['successful'] as $order) {
     *     echo "Created: {$order->orderId}\n";
     * }
     *
     * foreach ($result['failed'] as $failure) {
     *     echo "Failed {$failure['orderId']}: {$failure['error']}\n";
     * }
     * ```
     */
    public function batchCreateOrders(array $ordersData): array
    {
        $successful = [];
        $failed = [];

        foreach ($ordersData as $orderData) {
            try {
                // Convert clean field names to J&T API format
                $apiOrderData = FieldNameConverter::convert($orderData);

                $order = $this->createOrderFromArray($apiOrderData);
                $successful[] = $order;
            } catch (Throwable $e) {
                // Support both clean (orderId) and API (txlogisticId) field names for error reporting
                $orderId = $orderData['orderId'] ?? $orderData['txlogisticId'] ?? 'unknown';
                $failed[] = [
                    'orderId' => $orderId,
                    'error' => $e->getMessage(),
                    'exception' => $e,
                ];
            }
        }

        return [
            'successful' => $successful,
            'failed' => $failed,
        ];
    }

    /**
     * Batch track multiple parcels using parallel execution.
     *
     * Retrieves tracking information for multiple orders/parcels concurrently.
     * Uses Laravel's Concurrency facade to run API calls in parallel, significantly
     * improving performance when tracking many parcels.
     *
     * Performance: 10 parcels × 300ms = 3s sequential → ~300ms concurrent
     *
     * @param  array<string>  $orderIds  Array of order IDs to track
     * @param  array<string>  $trackingNumbers  Array of tracking numbers to track
     * @return array{successful: array<TrackingData>, failed: array<array{identifier: string, type: string, error: string}>}
     *
     * @example
     * ```php
     * // Track by order IDs (runs in parallel)
     * $result = $service->batchTrackParcels(orderIds: ['ORDER1', 'ORDER2', 'ORDER3']);
     *
     * // Track by tracking numbers (runs in parallel)
     * $result = $service->batchTrackParcels(trackingNumbers: ['TN001', 'TN002']);
     *
     * // Mixed (all run in parallel)
     * $result = $service->batchTrackParcels(
     *     orderIds: ['ORDER1'],
     *     trackingNumbers: ['TN002']
     * );
     * ```
     */
    public function batchTrackParcels(array $orderIds = [], array $trackingNumbers = []): array
    {
        $successful = [];
        $failed = [];

        // Build concurrent tasks - pass primitives only to avoid serialization issues
        // Each closure will resolve a fresh service instance in its child process
        $tasks = [];

        // Tasks for order IDs
        foreach ($orderIds as $orderId) {
            $tasks["order:{$orderId}"] = static fn (): array => self::executeTrackingTask(
                orderId: $orderId,
                trackingNumber: null,
            );
        }

        // Tasks for tracking numbers
        foreach ($trackingNumbers as $trackingNumber) {
            $tasks["tracking:{$trackingNumber}"] = static fn (): array => self::executeTrackingTask(
                orderId: null,
                trackingNumber: $trackingNumber,
            );
        }

        // If no tasks, return early
        if (empty($tasks)) {
            return ['successful' => [], 'failed' => []];
        }

        // Execute all tracking requests in parallel
        /** @var array<string, array{success: bool, data?: TrackingData, error?: string, identifier: string, type: string}> $results */
        $results = Concurrency::run($tasks);

        // Process results
        foreach ($results as $result) {
            if ($result['success']) {
                $successful[] = $result['data'];
            } else {
                $failed[] = [
                    'identifier' => $result['identifier'],
                    'type' => $result['type'],
                    'error' => $result['error'],
                ];
            }
        }

        return [
            'successful' => $successful,
            'failed' => $failed,
        ];
    }

    /**
     * Batch cancel multiple orders.
     *
     * Cancels multiple orders in a single operation. All orders will use the same
     * cancellation reason. For different reasons per order, call cancelOrder individually.
     *
     * @param  array<string>  $orderIds  Array of order IDs to cancel
     * @param  CancellationReason|string  $reason  Cancellation reason
     * @return array{successful: array<array{orderId: string, data: array<string, mixed>}>, failed: array<array{orderId: string, error: string, exception: Throwable}>}
     *
     * @example
     * ```php
     * use AIArmada\Jnt\Enums\CancellationReason;
     *
     * $result = $service->batchCancelOrders(
     *     orderIds: ['ORDER1', 'ORDER2', 'ORDER3'],
     *     reason: CancellationReason::OUT_OF_STOCK
     * );
     *
     * echo "Cancelled: " . count($result['successful']) . "\n";
     * echo "Failed: " . count($result['failed']) . "\n";
     * ```
     */
    public function batchCancelOrders(array $orderIds, CancellationReason | string $reason): array
    {
        $successful = [];
        $failed = [];

        foreach ($orderIds as $orderId) {
            try {
                $data = $this->cancelOrder($orderId, $reason);
                $successful[] = [
                    'orderId' => $orderId,
                    'data' => $data,
                ];
            } catch (Throwable $e) {
                $failed[] = [
                    'orderId' => $orderId,
                    'error' => $e->getMessage(),
                    'exception' => $e,
                ];
            }
        }

        return [
            'successful' => $successful,
            'failed' => $failed,
        ];
    }

    /**
     * Batch print waybills for multiple orders using parallel execution.
     *
     * Prints waybills for multiple orders concurrently. All waybills will use
     * the same template if specified.
     *
     * Performance: 10 waybills × 500ms = 5s sequential → ~500ms concurrent
     *
     * @param  array<string>  $orderIds  Array of order IDs to print
     * @param  string|null  $templateName  Optional template name for all waybills
     * @return array{successful: array<array{orderId: string, data: array<string, mixed>}>, failed: array<array{orderId: string, error: string}>}
     *
     * @example
     * ```php
     * $result = $service->batchPrintWaybills(
     *     orderIds: ['ORDER1', 'ORDER2', 'ORDER3'],
     *     templateName: 'CUSTOM_TEMPLATE'
     * );
     *
     * foreach ($result['successful'] as $waybill) {
     *     // Save or process waybill data
     *     file_put_contents(
     *         "waybill_{$waybill['orderId']}.pdf",
     *         base64_decode($waybill['data']['content'])
     *     );
     * }
     * ```
     */
    public function batchPrintWaybills(array $orderIds, ?string $templateName = null): array
    {
        if (empty($orderIds)) {
            return ['successful' => [], 'failed' => []];
        }

        // Build concurrent tasks - pass primitives only
        $tasks = [];
        foreach ($orderIds as $orderId) {
            $tasks[$orderId] = static fn (): array => self::executePrintTask($orderId, $templateName);
        }

        // Execute all print requests in parallel
        /** @var array<string, array{success: bool, orderId: string, data?: array<string, mixed>, error?: string}> $results */
        $results = Concurrency::run($tasks);

        $successful = [];
        $failed = [];

        foreach ($results as $result) {
            if ($result['success']) {
                $successful[] = [
                    'orderId' => $result['orderId'],
                    'data' => $result['data'],
                ];
            } else {
                $failed[] = [
                    'orderId' => $result['orderId'],
                    'error' => $result['error'],
                ];
            }
        }

        return [
            'successful' => $successful,
            'failed' => $failed,
        ];
    }

    /**
     * Lazy load the HTTP client only when needed
     */
    protected function getClient(): JntClient
    {
        if (! $this->client instanceof JntClient) {
            $baseUrl = $this->getBaseUrl();
            $apiAccount = $this->config['api_account'] ?? throw JntConfigurationException::missingApiAccount();
            $privateKey = $this->config['private_key'] ?? throw JntConfigurationException::missingPrivateKey();

            $this->client = new JntClient($baseUrl, $apiAccount, $privateKey, $this->config);
        }

        return $this->client;
    }

    protected function getBaseUrl(): string
    {
        $environment = $this->normalizeEnvironment((string) ($this->config['environment'] ?? 'testing'));
        $baseUrls = $this->config['base_urls'] ?? [];

        return $baseUrls[$environment] ?? throw JntConfigurationException::invalidEnvironment($environment);
    }

    protected function normalizeEnvironment(string $environment): string
    {
        return match ($environment) {
            'production' => 'production',
            'testing', 'local', 'development' => 'testing',
            default => throw JntConfigurationException::invalidEnvironment($environment),
        };
    }

    protected function getCustomerCode(): string
    {
        if ($this->customerCode === null || $this->customerCode === '') {
            throw JntConfigurationException::missingCustomerCode();
        }

        return $this->customerCode;
    }

    protected function getPassword(): string
    {
        if ($this->password === null || $this->password === '') {
            throw JntConfigurationException::missingPassword();
        }

        return $this->password;
    }

    /**
     * Execute a single tracking task in a child process.
     *
     * This static method is called in child processes during concurrent execution.
     * It resolves a fresh JntExpressService instance to ensure proper initialization.
     *
     * @return array{success: bool, data?: TrackingData, error?: string, identifier: string, type: string}
     */
    private static function executeTrackingTask(?string $orderId, ?string $trackingNumber): array
    {
        $identifier = $orderId ?? $trackingNumber ?? '';
        $type = $orderId !== null ? 'orderId' : 'trackingNumber';

        try {
            // Resolve a fresh service instance in the child process
            /** @var JntExpressService $service */
            $service = app(self::class);
            $tracking = $service->trackParcel(orderId: $orderId, trackingNumber: $trackingNumber);

            return [
                'success' => true,
                'data' => $tracking,
                'identifier' => $identifier,
                'type' => $type,
            ];
        } catch (Throwable $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'identifier' => $identifier,
                'type' => $type,
            ];
        }
    }

    /**
     * Execute a single print task in a child process.
     *
     * @return array{success: bool, orderId: string, data?: array<string, mixed>, error?: string}
     */
    private static function executePrintTask(string $orderId, ?string $templateName): array
    {
        try {
            /** @var JntExpressService $service */
            $service = app(self::class);
            $data = $service->printOrder($orderId, null, $templateName);

            return [
                'success' => true,
                'orderId' => $orderId,
                'data' => $data,
            ];
        } catch (Throwable $e) {
            return [
                'success' => false,
                'orderId' => $orderId,
                'error' => $e->getMessage(),
            ];
        }
    }
}
