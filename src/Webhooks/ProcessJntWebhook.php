<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Webhooks;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Webhooks\CommerceWebhookProcessor;
use AIArmada\Jnt\Enums\ScanTypeCode;
use AIArmada\Jnt\Enums\TrackingStatus;
use AIArmada\Jnt\Events\ParcelDelivered;
use AIArmada\Jnt\Events\ParcelInTransit;
use AIArmada\Jnt\Events\ParcelOutForDelivery;
use AIArmada\Jnt\Events\ParcelPickedUp;
use AIArmada\Jnt\Events\TrackingUpdated;
use AIArmada\Jnt\Models\JntOrder;
use AIArmada\Jnt\Models\JntTrackingEvent;
use AIArmada\Jnt\Services\JntStatusMapper;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;

/**
 * Process J&T Express webhook events.
 *
 * This job handles incoming J&T tracking updates and dispatches events.
 */
class ProcessJntWebhook extends CommerceWebhookProcessor
{
    /**
     * @param  array<string, mixed>  $payload
     */
    protected function extractEventId(array $payload): ?string
    {
        $eventId = parent::extractEventId($payload);

        if ($eventId !== null) {
            return $eventId;
        }

        $biz = $this->decodeBizContent($payload);

        if ($biz === null) {
            return null;
        }

        $canonicalPayload = json_encode($biz, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if (! is_string($canonicalPayload) || $canonicalPayload === '') {
            return null;
        }

        return 'jnt:' . hash('sha256', $canonicalPayload);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function sanitizePayloadForLogging(array $payload): array
    {
        $sanitized = [
            'payload_keys' => array_keys($payload),
        ];

        $bizContent = $payload['bizContent'] ?? null;

        if (is_string($bizContent) && $bizContent !== '') {
            $sanitized['bizContent_length'] = mb_strlen($bizContent);
            $sanitized['bizContent_sha256'] = hash('sha256', $bizContent);
        }

        return $sanitized;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    protected function decodeBizContent(array $payload): ?array
    {
        $bizContent = $payload['bizContent'] ?? null;

        if (! is_string($bizContent) || $bizContent === '') {
            return null;
        }

        $decoded = json_decode($bizContent, true);

        return is_array($decoded) ? $decoded : null;
    }

    protected function extractEventType(array $payload): string
    {
        $biz = $this->decodeBizContent($payload);

        if ($biz === null) {
            return $payload['scantype'] ?? $payload['event'] ?? $payload['type'] ?? 'tracking.update';
        }

        $details = $biz['details'] ?? null;

        if (is_array($details) && $details !== []) {
            $last = end($details);

            if (is_array($last)) {
                $scanType = $last['scanType'] ?? $last['scanTypeCode'] ?? null;

                if (is_string($scanType) && $scanType !== '') {
                    return $scanType;
                }
            }
        }

        return $payload['scantype'] ?? $payload['event'] ?? $payload['type'] ?? 'tracking.update';
    }

    /**
     * Process the webhook event.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function processEvent(string $eventType, array $payload): void
    {
        $biz = $this->decodeBizContent($payload);

        if ($biz === null) {
            Log::channel(config('jnt.logging.channel', 'stack'))
                ->warning('J&T webhook missing or invalid bizContent', [
                    'webhook_call_id' => $this->webhookCall->id,
                ]);

            return;
        }

        $billcode = $biz['billCode'] ?? null;

        if (empty($billcode)) {
            $context = ['webhook_call_id' => $this->webhookCall->id];

            $context += (bool) config('jnt.webhooks.log_payloads', false)
                ? $this->sanitizePayloadForLogging($payload)
                : ['payload_keys' => array_keys($payload)];

            Log::channel(config('jnt.logging.channel', 'stack'))
                ->warning('J&T webhook missing billcode', $context);

            return;
        }

        // Find the shipment
        $shipment = JntOrder::query()
            ->withoutOwnerScope()
            ->where('tracking_number', $billcode)
            ->first();

        if (! $shipment) {
            Log::channel(config('jnt.logging.channel', 'stack'))
                ->info('J&T webhook for unknown shipment', [
                    'billcode' => $billcode,
                    'webhook_call_id' => $this->webhookCall->id,
                ]);

            // Still dispatch generic tracking event
            TrackingUpdated::dispatch($billcode, $eventType, $biz);

            return;
        }

        $owner = OwnerContext::fromTypeAndId(
            $shipment->owner_type,
            $shipment->owner_id,
        );

        OwnerContext::withOwner($owner, function () use ($shipment, $billcode, $eventType, $biz): void {
            $latestDetail = $this->latestTrackingDetail($biz);
            $newStatus = $this->resolveStatus($eventType, $latestDetail);

            $this->syncShipmentTrackingFromWebhook($shipment, $billcode, $biz, $newStatus);

            if ($newStatus) {
                $this->dispatchStatusEvent($shipment, $newStatus, $biz);
            }

            TrackingUpdated::dispatch($billcode, $eventType, $biz);
        });
    }

    /**
     * Map J&T scan type to TrackingStatus.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function mapToStatus(string $scanType, array $payload): ?TrackingStatus
    {
        // J&T scan types mapping
        return match (mb_strtoupper($scanType)) {
            'PICKUP', 'COLLECTED' => TrackingStatus::PickedUp,
            'IN_TRANSIT', 'TRANSIT', 'ARRIVED', 'DEPARTED' => TrackingStatus::InTransit,
            'OUT_FOR_DELIVERY', 'DELIVERING' => TrackingStatus::OutForDelivery,
            'DELIVERED', 'POD' => TrackingStatus::Delivered,
            'FAILED', 'UNDELIVERED' => TrackingStatus::Exception,
            'RETURNED', 'RTS' => TrackingStatus::Returned,
            default => null,
        };
    }

    /**
     * Dispatch status-specific events.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function dispatchStatusEvent(JntOrder $shipment, TrackingStatus $status, array $payload): void
    {
        match ($status) {
            TrackingStatus::PickedUp => ParcelPickedUp::dispatch($shipment, $payload),
            TrackingStatus::InTransit => ParcelInTransit::dispatch($shipment, $payload),
            TrackingStatus::OutForDelivery => ParcelOutForDelivery::dispatch($shipment, $payload),
            TrackingStatus::Delivered => ParcelDelivered::dispatch($shipment, $payload),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>|null  $latestDetail
     */
    private function resolveStatus(string $eventType, ?array $latestDetail): ?TrackingStatus
    {
        if ($latestDetail !== null) {
            $scanTypeCode = $latestDetail['scanTypeCode'] ?? null;
            $statusDescription = $latestDetail['desc'] ?? $latestDetail['scanTypeName'] ?? $latestDetail['scanType'] ?? null;

            if (is_string($scanTypeCode) && $scanTypeCode !== '') {
                $scanType = ScanTypeCode::tryFrom($scanTypeCode);

                if ($scanType !== null) {
                    return app(JntStatusMapper::class)->fromScanType($scanType);
                }
            }

            if (is_string($statusDescription) && $statusDescription !== '') {
                return app(JntStatusMapper::class)->fromString($statusDescription);
            }
        }

        return $this->mapToStatus($eventType, $latestDetail ?? []);
    }

    /**
     * @param  array<string, mixed>  $biz
     */
    private function syncShipmentTrackingFromWebhook(JntOrder $shipment, string $billcode, array $biz, ?TrackingStatus $status): void
    {
        $details = $biz['details'] ?? null;

        if (is_array($details)) {
            foreach ($details as $detail) {
                if (! is_array($detail)) {
                    continue;
                }

                $scanTime = $this->parseScanTime($detail['scanTime'] ?? null);

                try {
                    JntTrackingEvent::query()->create([
                        'event_hash' => $this->trackingEventHash($shipment, $billcode, $detail),
                        'order_id' => $shipment->id,
                        'tracking_number' => $billcode,
                        'scan_type_code' => $this->nullableString($detail['scanTypeCode'] ?? null),
                        'scan_time' => $scanTime,
                        'order_reference' => $shipment->order_id,
                        'scan_type_name' => $this->nullableString($detail['scanTypeName'] ?? null),
                        'scan_type' => $this->nullableString($detail['scanType'] ?? null),
                        'description' => $this->nullableString($detail['desc'] ?? null),
                        'scan_network_type_name' => $this->nullableString($detail['scanNetworkTypeName'] ?? null),
                        'scan_network_name' => $this->nullableString($detail['scanNetworkName'] ?? null),
                        'scan_network_contact' => $this->nullableString($detail['scanNetworkContact'] ?? null),
                        'scan_network_province' => $this->nullableString($detail['scanNetworkProvince'] ?? null),
                        'scan_network_city' => $this->nullableString($detail['scanNetworkCity'] ?? null),
                        'scan_network_area' => $this->nullableString($detail['scanNetworkArea'] ?? null),
                        'scan_network_country' => $this->nullableString($detail['scanNetworkCountray'] ?? $detail['scanNetworkCountry'] ?? null),
                        'post_code' => $this->nullableString($detail['postCode'] ?? null),
                        'next_stop_name' => $this->nullableString($detail['nextStopName'] ?? null),
                        'next_network_province_name' => $this->nullableString($detail['nextNetworkProvinceName'] ?? null),
                        'next_network_city_name' => $this->nullableString($detail['nextNetworkCityName'] ?? null),
                        'next_network_area_name' => $this->nullableString($detail['nextNetworkAreaName'] ?? null),
                        'remark' => $this->nullableString($detail['remark'] ?? null),
                        'problem_type' => $this->nullableString($detail['problemType'] ?? null),
                        'payment_status' => $this->nullableString($detail['paymentStatus'] ?? null),
                        'payment_method' => $this->nullableString($detail['paymentMethod'] ?? null),
                        'actual_weight' => $this->nullableString($detail['realWeight'] ?? null),
                        'longitude' => $this->nullableString($detail['longitude'] ?? null),
                        'latitude' => $this->nullableString($detail['latitude'] ?? null),
                        'time_zone' => $this->nullableString($detail['timeZone'] ?? null),
                        'scan_network_id' => isset($detail['scanNetworkId']) ? (int) $detail['scanNetworkId'] : null,
                        'staff_name' => $this->nullableString($detail['staffName'] ?? null),
                        'staff_contact' => $this->nullableString($detail['staffContact'] ?? null),
                        'otp' => $this->nullableString($detail['otp'] ?? null),
                        'second_level_type_code' => $this->nullableString($detail['secondLevelTypeCode'] ?? null),
                        'wc_trace_flag' => $this->nullableString($detail['wcTraceFlag'] ?? null),
                        'signature_picture_url' => $this->nullableString($detail['sigPicUrl'] ?? null),
                        'sign_url' => $this->nullableString($detail['signUrl'] ?? null),
                        'electronic_signature_pic_url' => $this->nullableString($detail['electronicSignaturePicUrl'] ?? null),
                        'payload' => $detail,
                        'owner_type' => $shipment->owner_type,
                        'owner_id' => $shipment->owner_id,
                    ]);
                } catch (QueryException $exception) {
                    if (! $this->isUniqueConstraintViolation($exception)) {
                        throw $exception;
                    }
                }
            }
        }

        $latestDetail = $this->latestTrackingDetail($biz);
        $updates = [
            'last_tracked_at' => CarbonImmutable::now(),
        ];

        if ($status !== null) {
            $updates['status'] = $status->value;
        }

        if ($latestDetail !== null) {
            $lastStatusCode = $this->nullableString($latestDetail['scanTypeCode'] ?? null);
            $lastStatus = $this->nullableString($latestDetail['desc'] ?? $latestDetail['scanTypeName'] ?? $latestDetail['scanType'] ?? null);

            if ($lastStatusCode !== null) {
                $updates['last_status_code'] = $lastStatusCode;
            }

            if ($lastStatus !== null) {
                $updates['last_status'] = mb_substr($lastStatus, 0, 128);
            }

            if (($latestDetail['problemType'] ?? null) !== null || $status === TrackingStatus::Exception) {
                $updates['has_problem'] = true;
            }

            if ($status === TrackingStatus::Delivered && $shipment->delivered_at === null) {
                $deliveredAt = $this->parseScanTime($latestDetail['scanTime'] ?? null);

                if ($deliveredAt !== null) {
                    $updates['delivered_at'] = $deliveredAt;
                }
            }
        }

        $shipment->fill($updates);
        $shipment->save();
    }

    /**
     * @param  array<string, mixed>  $biz
     * @return array<string, mixed>|null
     */
    private function latestTrackingDetail(array $biz): ?array
    {
        $details = $biz['details'] ?? null;

        if (! is_array($details) || $details === []) {
            return null;
        }

        usort($details, function (mixed $left, mixed $right): int {
            $leftTimestamp = is_array($left)
                ? $this->parseScanTime($left['scanTime'] ?? null)?->getTimestamp()
                : null;
            $rightTimestamp = is_array($right)
                ? $this->parseScanTime($right['scanTime'] ?? null)?->getTimestamp()
                : null;

            return ($rightTimestamp ?? 0) <=> ($leftTimestamp ?? 0);
        });

        $latest = $details[0] ?? null;

        return is_array($latest) ? $latest : null;
    }

    private function parseScanTime(mixed $value): ?Carbon
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return Carbon::parse($value);
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $string = mb_trim((string) $value);

        return $string === '' ? null : $string;
    }

    /**
     * @param  array<string, mixed>  $detail
     */
    private function trackingEventHash(JntOrder $shipment, string $billcode, array $detail): string
    {
        ksort($detail);

        $normalized = [
            'order_id' => (string) $shipment->id,
            'tracking_number' => $billcode,
            'owner_type' => $shipment->owner_type,
            'owner_id' => $shipment->owner_id,
            'detail' => $detail,
        ];

        $payload = json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if (! is_string($payload) || $payload === '') {
            return hash('sha256', serialize($normalized));
        }

        return hash('sha256', $payload);
    }

    private function isUniqueConstraintViolation(QueryException $exception): bool
    {
        $sqlState = (string) ($exception->errorInfo[0] ?? $exception->getCode());

        if (in_array($sqlState, ['23000', '23505'], true)) {
            return true;
        }

        $message = mb_strtolower($exception->getMessage());

        return str_contains($message, 'duplicate')
            || str_contains($message, 'unique constraint');
    }
}
