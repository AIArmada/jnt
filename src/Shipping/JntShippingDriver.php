<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Shipping;

use AIArmada\Jnt\Data\AddressData as JntAddressData;
use AIArmada\Jnt\Data\ItemData;
use AIArmada\Jnt\Data\PackageInfoData;
use AIArmada\Jnt\Enums\CancellationReason;
use AIArmada\Jnt\Enums\GoodsType;
use AIArmada\Jnt\Enums\TrackingStatus as JntTrackingStatus;
use AIArmada\Jnt\Services\JntExpressService;
use AIArmada\Jnt\Services\JntStatusMapper;
use AIArmada\Jnt\Services\JntTrackingService;
use AIArmada\Shipping\Contracts\AddressValidationResult;
use AIArmada\Shipping\Contracts\ShippingDriverInterface;
use AIArmada\Shipping\Data\AddressData;
use AIArmada\Shipping\Data\LabelData;
use AIArmada\Shipping\Data\PackageData;
use AIArmada\Shipping\Data\RateQuoteData;
use AIArmada\Shipping\Data\ShipmentData;
use AIArmada\Shipping\Data\ShipmentItemData;
use AIArmada\Shipping\Data\ShipmentResultData;
use AIArmada\Shipping\Data\ShippingMethodData;
use AIArmada\Shipping\Data\TrackingData;
use AIArmada\Shipping\Data\TrackingEventData;
use AIArmada\Shipping\Enums\DriverCapability;
use AIArmada\Shipping\Enums\TrackingStatus;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Throwable;

/**
 * J&T Express shipping driver implementation.
 *
 * Integrates J&T Express with the unified shipping abstraction layer.
 */
class JntShippingDriver implements ShippingDriverInterface
{
    public function __construct(
        protected readonly JntExpressService $jntService,
        protected readonly JntTrackingService $trackingService,
        protected readonly JntStatusMapper $statusMapper,
    ) {}

    public function getCarrierCode(): string
    {
        return 'jnt';
    }

    public function getCarrierName(): string
    {
        return 'J&T Express';
    }

    public function supports(DriverCapability $capability): bool
    {
        return match ($capability) {
            DriverCapability::RateQuotes => true,
            DriverCapability::LabelGeneration => true,
            DriverCapability::Tracking => true,
            DriverCapability::Webhooks => true,
            DriverCapability::CashOnDelivery => true,
            DriverCapability::BatchOperations => true,
            DriverCapability::Returns => false,
            DriverCapability::AddressValidation => false,
            DriverCapability::PickupScheduling => true,
            DriverCapability::InsuranceClaims => false,
            DriverCapability::MultiPackage => false,
            DriverCapability::InternationalShipping => false,
        };
    }

    public function getAvailableMethods(): Collection
    {
        return collect([
            new ShippingMethodData(
                code: 'EZ',
                name: 'J&T EZ',
                description: 'Standard delivery',
                minDays: 2,
                maxDays: 4,
                trackingAvailable: true,
            ),
            new ShippingMethodData(
                code: 'EXPRESS',
                name: 'J&T Express',
                description: 'Express delivery',
                minDays: 1,
                maxDays: 2,
                trackingAvailable: true,
            ),
        ]);
    }

    /**
     * @param  array<PackageData>  $packages
     * @param  array<string, mixed>  $options
     * @return Collection<int, RateQuoteData>
     */
    public function getRates(
        AddressData $origin,
        AddressData $destination,
        array $packages,
        array $options = []
    ): Collection {
        $totalWeight = array_sum(array_map(fn (PackageData $p) => $p->weight, $packages));

        // Calculate rate based on weight
        $rate = $this->calculateWeightBasedRate($totalWeight, $destination);
        $estimatedDays = $this->getEstimatedDays($destination);

        return collect([
            new RateQuoteData(
                carrier: 'jnt',
                service: 'EZ',
                rate: $rate,
                currency: 'MYR',
                estimatedDays: $estimatedDays,
                serviceDescription: 'J&T EZ - Standard Delivery',
                calculatedLocally: true,
            ),
        ]);
    }

    public function createShipment(ShipmentData $data): ShipmentResultData
    {
        try {
            $sender = $this->convertToJntAddress($data->origin, 'sender');
            $receiver = $this->convertToJntAddress($data->destination, 'receiver');
            $items = $this->convertToJntItems($data->items);
            $packageInfo = $this->createPackageInfo($data);

            $orderData = $this->jntService->createOrder(
                sender: $sender,
                receiver: $receiver,
                items: $items,
                packageInfo: $packageInfo,
                orderId: $data->reference,
                additionalData: array_filter([
                    'codInfo' => $data->isCashOnDelivery()
                        ? ['codValue' => $data->codAmount / 100]
                        : null,
                    'remark' => $data->instructions ?? '',
                ], static fn (mixed $value): bool => $value !== null),
            );

            $trackingNumber = $orderData->trackingNumber;

            // Get label URL
            $labelUrl = null;
            if ($trackingNumber !== null) {
                try {
                    $printResponse = $this->jntService->printOrder(orderId: $data->reference, trackingNumber: $trackingNumber);
                    $labelUrl = $printResponse['url'] ?? null;
                } catch (Throwable) {
                    // Label generation may fail, continue without it
                }
            }

            return new ShipmentResultData(
                success: true,
                trackingNumber: $trackingNumber,
                carrierReference: $orderData->orderId,
                labelUrl: $labelUrl,
                rawResponse: $orderData->toArray(),
            );
        } catch (Throwable $e) {
            return new ShipmentResultData(
                success: false,
                error: $e->getMessage(),
            );
        }
    }

    public function cancelShipment(string $trackingNumber): bool
    {
        try {
            $orderId = null;

            try {
                $tracking = $this->jntService->trackParcel(null, $trackingNumber);
                $orderId = $tracking->orderId;
            } catch (Throwable) {
                // Best-effort: cancellation can still succeed without resolving txlogisticId.
            }

            $response = $this->jntService->cancelOrder(
                orderId: $orderId ?? $trackingNumber,
                reason: CancellationReason::CUSTOMER_REQUEST,
                trackingNumber: $trackingNumber,
            );

            if (array_key_exists('success', $response)) {
                return (bool) $response['success'];
            }

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function generateLabel(string $trackingNumber, array $options = []): LabelData
    {
        $orderId = $options['order_id'] ?? $options['orderId'] ?? $trackingNumber;

        $response = $this->jntService->printOrder(
            orderId: (string) $orderId,
            trackingNumber: $trackingNumber,
            templateName: $options['template'] ?? null,
        );

        $base64Content = $response['base64EncodeContent'] ?? null;

        return new LabelData(
            format: 'pdf',
            url: null,
            content: $base64Content,
            trackingNumber: $trackingNumber,
        );
    }

    public function track(string $trackingNumber): TrackingData
    {
        $response = $this->trackingService->track(trackingNumber: $trackingNumber);

        $events = collect($response['events'] ?? [])
            ->map(function (array $event) {
                /** @var JntTrackingStatus $jntStatus */
                $jntStatus = $event['status'];
                $normalizedStatus = $this->mapJntStatusToNormalized($jntStatus);

                /** @var Carbon $occurredAt */
                $occurredAt = $event['occurred_at'];

                return new TrackingEventData(
                    code: $jntStatus->value,
                    description: $event['description'],
                    timestamp: $occurredAt->toDateTimeImmutable(),
                    normalizedStatus: $normalizedStatus,
                    location: $event['location'] ?? null,
                    raw: $event,
                );
            });

        /** @var JntTrackingStatus $currentJntStatus */
        $currentJntStatus = $response['current_status'];
        $currentStatus = $this->mapJntStatusToNormalized($currentJntStatus);
        $latestEvent = $events->first();

        return new TrackingData(
            trackingNumber: $trackingNumber,
            status: $currentStatus,
            events: $events,
            carrier: 'jnt',
            deliveredAt: $currentStatus === TrackingStatus::Delivered
            ? $latestEvent?->timestamp
            : null,
        );
    }

    public function validateAddress(AddressData $address): AddressValidationResult
    {
        // JNT doesn't provide address validation API
        return new AddressValidationResult(
            valid: true,
            warnings: ['Address validation not available for J&T Express.'],
        );
    }

    public function servicesDestination(AddressData $destination): bool
    {
        // JNT services Malaysia
        return in_array(mb_strtoupper($destination->country), ['MY', 'MYS'], true);
    }

    /**
     * Convert shipping address data to JNT format.
     */
    protected function convertToJntAddress(AddressData $address, string $type): JntAddressData
    {
        return new JntAddressData(
            name: $address->name,
            phone: $address->phone,
            address: $address->line1,
            postCode: $address->postcode,
            city: $address->city ?? '',
            area: $address->state ?? '',
        );
    }

    /**
     * Convert shipment items to JNT format.
     *
     * @param  array<ShipmentItemData>  $items
     * @return array<ItemData>
     */
    protected function convertToJntItems(array $items): array
    {
        return array_map(function ($item) {
            return new ItemData(
                name: $item->name,
                quantity: $item->quantity,
                weight: $item->weight ?? 0,
                price: ($item->declaredValue ?? 0) / 100,
            );
        }, $items);
    }

    /**
     * Create package info from shipment data.
     */
    protected function createPackageInfo(ShipmentData $data): PackageInfoData
    {
        return new PackageInfoData(
            quantity: 1,
            weight: round($data->getTotalWeight() / 1000, 2),
            value: ($data->declaredValue ?? 0) / 100,
            goodsType: GoodsType::PACKAGE,
        );
    }

    /**
     * Map JNT tracking status to normalized shipping status.
     */
    protected function mapJntStatusToNormalized(JntTrackingStatus $jntStatus): TrackingStatus
    {
        return match ($jntStatus) {
            JntTrackingStatus::Pending => TrackingStatus::LabelCreated,
            JntTrackingStatus::PickedUp => TrackingStatus::PickedUp,
            JntTrackingStatus::InTransit => TrackingStatus::InTransit,
            JntTrackingStatus::AtHub => TrackingStatus::ArrivedAtFacility,
            JntTrackingStatus::OutForDelivery => TrackingStatus::OutForDelivery,
            JntTrackingStatus::DeliveryAttempted => TrackingStatus::DeliveryAttemptFailed,
            JntTrackingStatus::Delivered => TrackingStatus::Delivered,
            JntTrackingStatus::ReturnInitiated => TrackingStatus::ReturnToSender,
            JntTrackingStatus::Returned => TrackingStatus::ReturnDelivered,
            JntTrackingStatus::Exception => TrackingStatus::OnHold,
        };
    }

    /**
     * Calculate weight-based shipping rate.
     */
    protected function calculateWeightBasedRate(int $weightGrams, AddressData $destination): int
    {
        $weightKg = max(1, ceil($weightGrams / 1000));

        // Base rate configuration
        $baseRate = config('jnt.shipping.base_rate', 800); // RM8.00
        $perKgRate = config('jnt.shipping.per_kg_rate', 200); // RM2.00 per additional kg
        $regionMultiplier = $this->getRegionMultiplier($destination);

        $rate = $baseRate + (max(0, $weightKg - 1) * $perKgRate);

        return (int) round($rate * $regionMultiplier);
    }

    /**
     * Get region multiplier for pricing.
     */
    protected function getRegionMultiplier(AddressData $destination): float
    {
        $postcode = $destination->postcode;

        // East Malaysia (Sabah/Sarawak) - higher rates
        $eastMalaysiaRanges = [
            ['87000', '91999'], // Sabah
            ['93000', '98999'], // Sarawak
        ];

        foreach ($eastMalaysiaRanges as $range) {
            if ($postcode >= $range[0] && $postcode <= $range[1]) {
                return match (true) {
                    $postcode >= '87000' && $postcode <= '91999' => (float) Arr::get(config('jnt.shipping.region_multipliers', []), 'sabah', 1.5),
                    $postcode >= '93000' && $postcode <= '98999' => (float) Arr::get(config('jnt.shipping.region_multipliers', []), 'sarawak', 1.5),
                    default => (float) Arr::get(config('jnt.shipping.region_multipliers', []), 'labuan', 1.5),
                };
            }
        }

        return 1.0;
    }

    /**
     * Get estimated delivery days.
     */
    protected function getEstimatedDays(AddressData $destination): int
    {
        $postcode = $destination->postcode;
        $defaultDays = (int) config('jnt.shipping.default_estimated_days', 3);
        $eastExtraDays = (int) config('jnt.shipping.east_malaysia_extra_days', 2);

        // East Malaysia takes longer
        $eastMalaysiaRanges = [
            ['87000', '91999'],
            ['93000', '98999'],
        ];

        foreach ($eastMalaysiaRanges as $range) {
            if ($postcode >= $range[0] && $postcode <= $range[1]) {
                return $defaultDays + $eastExtraDays;
            }
        }

        return $defaultDays;
    }
}
