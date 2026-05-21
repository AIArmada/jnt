<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Services;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Jnt\Data\TrackingData;
use AIArmada\Jnt\Data\TrackingDetailData;
use AIArmada\Jnt\Enums\TrackingStatus;
use AIArmada\Jnt\Events\JntOrderStatusChanged;
use AIArmada\Jnt\Models\JntOrder;
use AIArmada\Jnt\Models\JntTrackingEvent;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Throwable;

class JntTrackingService
{
    public function __construct(
        private readonly JntExpressService $expressService,
        private readonly JntStatusMapper $statusMapper,
    ) {}

    /**
     * Get the normalized tracking status for a tracking detail
     */
    public function getNormalizedStatus(TrackingDetailData $detail): TrackingStatus
    {
        return $this->statusMapper->resolve(
            scanTypeCode: $detail->scanTypeCode,
            statusDescription: $detail->description !== ''
                ? $detail->description
                : ($detail->scanTypeName !== '' ? $detail->scanTypeName : $detail->scanType),
        );
    }

    /**
     * Get the current normalized status from tracking data
     */
    public function getCurrentStatus(TrackingData $trackingData): TrackingStatus
    {
        if ($trackingData->details->count() === 0) {
            return TrackingStatus::Pending;
        }

        $latestDetail = $trackingData->getLatestDetail();

        if ($latestDetail === null) {
            return TrackingStatus::Pending;
        }

        return $this->getNormalizedStatus($latestDetail);
    }

    /**
     * Track a parcel and return normalized status information
     *
     * @return array{tracking_number: string, order_id: string|null, current_status: TrackingStatus, events: array<array{status: TrackingStatus, description: string, location: string|null, occurred_at: Carbon, raw: TrackingDetailData}>}
     */
    public function track(?string $orderId = null, ?string $trackingNumber = null): array
    {
        $trackingData = $this->expressService->trackParcel($orderId, $trackingNumber);

        return $this->parseTrackingData($trackingData);
    }

    /**
     * Parse tracking data into normalized format
     *
     * @return array{tracking_number: string, order_id: string|null, current_status: TrackingStatus, events: array<array{status: TrackingStatus, description: string, location: string|null, occurred_at: Carbon, raw: TrackingDetailData}>}
     */
    public function parseTrackingData(TrackingData $trackingData): array
    {
        $events = [];

        foreach ($trackingData->details->toCollection() as $detail) {
            $events[] = [
                'status' => $this->getNormalizedStatus($detail),
                'description' => $detail->description,
                'location' => $this->formatLocation($detail),
                'occurred_at' => Carbon::parse($detail->scanTime),
                'raw' => $detail,
            ];
        }

        return [
            'tracking_number' => $trackingData->trackingNumber,
            'order_id' => $trackingData->orderId,
            'current_status' => $this->getCurrentStatus($trackingData),
            'events' => $events,
        ];
    }

    /**
     * Sync tracking events to the database for a JntOrder
     */
    public function syncOrderTracking(JntOrder $order): JntOrder
    {
        $owner = OwnerContext::fromTypeAndId($order->owner_type, $order->owner_id);

        return OwnerContext::withOwner($owner, function () use ($order): JntOrder {
            $trackingNumber = $order->tracking_number;

            if ($trackingNumber === null) {
                return $order;
            }

            $trackingData = $this->expressService->trackParcel(trackingNumber: $trackingNumber);

            // Store new events
            foreach ($trackingData->details->toCollection() as $detail) {
                $scanTime = Carbon::parse($detail->scanTime);

                $ownerType = $order->owner_type;
                $ownerId = $order->owner_id;

                JntTrackingEvent::firstOrCreate(
                    [
                        'order_id' => $order->id,
                        'tracking_number' => $trackingNumber,
                        'scan_type_code' => $detail->scanTypeCode,
                        'scan_time' => $scanTime,
                        'owner_type' => $ownerType,
                        'owner_id' => $ownerId,
                    ],
                    [
                        'order_reference' => $order->order_id,
                        'scan_type_name' => $detail->scanTypeName,
                        'scan_type' => $detail->scanType,
                        'description' => $detail->description,
                        'scan_network_type_name' => $detail->scanNetworkTypeName,
                        'scan_network_name' => $detail->scanNetworkName,
                        'scan_network_contact' => $detail->scanNetworkContact,
                        'scan_network_province' => $detail->scanNetworkProvince,
                        'scan_network_city' => $detail->scanNetworkCity,
                        'scan_network_area' => $detail->scanNetworkArea,
                        'scan_network_country' => $detail->scanNetworkCountry,
                        'post_code' => $detail->postCode,
                        'next_stop_name' => $detail->nextStopName,
                        'next_network_province_name' => $detail->nextNetworkProvinceName,
                        'next_network_city_name' => $detail->nextNetworkCityName,
                        'next_network_area_name' => $detail->nextNetworkAreaName,
                        'remark' => $detail->remark,
                        'problem_type' => $detail->problemType,
                        'payment_status' => $detail->paymentStatus,
                        'payment_method' => $detail->paymentMethod,
                        'actual_weight' => $detail->actualWeight,
                        'longitude' => $detail->longitude,
                        'latitude' => $detail->latitude,
                        'time_zone' => $detail->timeZone,
                        'scan_network_id' => $detail->scanNetworkId,
                        'staff_name' => $detail->staffName,
                        'staff_contact' => $detail->staffContact,
                        'otp' => $detail->otp,
                        'second_level_type_code' => $detail->secondLevelTypeCode,
                        'wc_trace_flag' => $detail->wcTraceFlag,
                        'signature_picture_url' => $detail->signaturePictureUrl,
                        'sign_url' => $detail->signUrl,
                        'electronic_signature_pic_url' => $detail->electronicSignaturePicUrl,
                        'payload' => $detail->toApiArray(),
                        'owner_type' => $ownerType,
                        'owner_id' => $ownerId,
                    ]
                );
            }

            // Update order status
            $currentStatus = $this->getCurrentStatus($trackingData);
            $previousStatusCode = $order->last_status_code;
            $latestDetail = $trackingData->getLatestDetail();

            if ($latestDetail !== null) {
                $order->last_status_code = $latestDetail->scanTypeCode;
                $order->last_status = $latestDetail->description;
                $order->last_tracked_at = CarbonImmutable::now();

                // Check if this is a problem event
                if ($latestDetail->problemType !== null || $currentStatus === TrackingStatus::Exception) {
                    $order->has_problem = true;
                }

                // Mark as delivered if appropriate
                if ($currentStatus === TrackingStatus::Delivered && $order->delivered_at === null) {
                    $order->delivered_at = Carbon::parse($latestDetail->scanTime);
                }
            }

            $order->save();

            // Fire status changed event if status actually changed
            if ($previousStatusCode !== $order->last_status_code) {
                event(JntOrderStatusChanged::fromOrder($order, $currentStatus, $previousStatusCode));
            }

            return $order->fresh() ?? $order;
        });
    }

    /**
     * Batch sync tracking for multiple orders
     *
     * @param  iterable<JntOrder>  $orders
     * @return array{successful: array<JntOrder>, failed: array<array{order: JntOrder, error: string}>}
     */
    public function batchSyncTracking(iterable $orders): array
    {
        $successful = [];
        $failed = [];

        foreach ($orders as $order) {
            try {
                $successful[] = $this->syncOrderTracking($order);
            } catch (Throwable $e) {
                $failed[] = [
                    'order' => $order,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return [
            'successful' => $successful,
            'failed' => $failed,
        ];
    }

    /**
     * Get orders that need tracking updates
     *
     * @return Collection<int, JntOrder>
     */
    public function getOrdersNeedingTrackingUpdate(int $limit = 100): Collection
    {
        if (! (bool) config('jnt.owner.enabled', false)) {
            return JntOrder::query()
                ->whereNotNull('tracking_number')
                ->whereNull('delivered_at')
                ->where(function ($query): void {
                    $query->whereNull('last_tracked_at')
                        ->orWhere('last_tracked_at', '<', CarbonImmutable::now()->subHours(1));
                })
                ->orderBy('last_tracked_at', 'asc')
                ->limit($limit)
                ->get();
        }

        $owner = OwnerContext::resolve();

        if ($owner === null) {
            throw new AuthorizationException('JNT tracking updates require an explicit owner context when jnt.owner.enabled is true.');
        }

        $includeGlobal = (bool) config('jnt.owner.include_global', false);

        return $this->getOrdersNeedingTrackingUpdateForOwner(owner: $owner, includeGlobal: $includeGlobal, limit: $limit);

    }

    /**
     * Get orders that need tracking updates for a specific owner.
     *
     * NOTE: Non-request surfaces must not rely on ambient auth; pass/iterate owners explicitly.
     *
     * @return Collection<int, JntOrder>
     */
    public function getOrdersNeedingTrackingUpdateForOwner(?Model $owner, bool $includeGlobal = false, int $limit = 100): Collection
    {
        $includeGlobal = $includeGlobal && (bool) config('jnt.owner.include_global', false);

        /** @var Builder<JntOrder> $query */
        $query = JntOrder::query()->forOwner(owner: $owner, includeGlobal: $includeGlobal);

        return $query
            ->whereNotNull('tracking_number')
            ->whereNull('delivered_at')
            ->where(function ($query): void {
                $query->whereNull('last_tracked_at')
                    ->orWhere('last_tracked_at', '<', CarbonImmutable::now()->subHours(1));
            })
            ->orderBy('last_tracked_at', 'asc')
            ->limit($limit)
            ->get();
    }

    /**
     * Format location from tracking detail
     */
    private function formatLocation(TrackingDetailData $detail): ?string
    {
        $parts = array_filter([
            $detail->scanNetworkArea,
            $detail->scanNetworkCity,
            $detail->scanNetworkProvince,
            $detail->scanNetworkCountry,
        ]);

        if (empty($parts)) {
            return $detail->scanNetworkName;
        }

        return implode(', ', $parts);
    }
}
