<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Webhooks;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Webhooks\CommerceWebhookProcessor;
use AIArmada\Jnt\Data\TrackingData;
use AIArmada\Jnt\Enums\ScanTypeCode;
use AIArmada\Jnt\Enums\TrackingStatus;
use AIArmada\Jnt\Events\TrackingUpdatedEvent;
use AIArmada\Jnt\Models\JntOrder;
use AIArmada\Jnt\Models\JntTrackingEvent;
use AIArmada\Jnt\Models\JntWebhookLog;
use AIArmada\Jnt\Services\JntStatusMapper;
use AIArmada\Jnt\Support\TrackingEventHash;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Spatie\WebhookClient\Models\WebhookCall;
use Throwable;

/**
 * Process J&T Express webhook events.
 *
 * This job handles incoming J&T tracking updates and dispatches events.
 */
class ProcessJntWebhook extends CommerceWebhookProcessor
{
    public int $tries = 1;

    public function __construct(WebhookCall $webhookCall)
    {
        parent::__construct($webhookCall);

        $this->tries = max(1, (int) config('jnt.webhooks.retry_times', 3));
    }

    public function backoff(): int
    {
        return max(1, (int) config('jnt.webhooks.retry_backoff_seconds', 60));
    }

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

        if (! is_string($canonicalPayload)) {
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
            $scanType = $payload['scantype'] ?? null;

            return is_string($scanType) && $scanType !== '' ? $scanType : 'tracking.update';
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

        $scanType = $payload['scantype'] ?? null;

        return is_string($scanType) && $scanType !== '' ? $scanType : 'tracking.update';
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
            $this->markWebhookLogAsFailed('Missing or invalid bizContent.');

            Log::channel(config('jnt.logging.channel', 'stack'))
                ->warning('J&T webhook missing or invalid bizContent', [
                    'webhook_call_id' => $this->webhookCall->id,
                ]);

            return;
        }

        $billcode = $biz['billCode'] ?? null;

        if (empty($billcode)) {
            $this->markWebhookLogAsFailed('Missing billcode.');

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
            $this->syncWebhookLogMetadata(null, (string) $billcode, $biz);

            Log::channel(config('jnt.logging.channel', 'stack'))
                ->info('J&T webhook for unknown shipment', [
                    'billcode' => $billcode,
                    'webhook_call_id' => $this->webhookCall->id,
                ]);

            $this->dispatchTrackingEvent($biz);

            return;
        }

        $owner = OwnerContext::fromTypeAndId(
            $shipment->owner_type,
            $shipment->owner_id,
        );

        $this->syncWebhookLogMetadata($shipment, (string) $billcode, $biz);

        OwnerContext::withOwner($owner, function () use ($shipment, $billcode, $eventType, $biz): void {
            $latestDetail = $this->latestTrackingDetail($biz);
            $newStatus = $this->resolveStatus($eventType, $latestDetail);

            $this->syncShipmentTrackingFromWebhook($shipment, $billcode, $biz, $newStatus);

            $this->dispatchTrackingEvent($biz);
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
     * Dispatch the canonical typed tracking event for both known and unknown orders.
     *
     * @param  array<string, mixed>  $payload
     */
    private function dispatchTrackingEvent(array $payload): void
    {
        TrackingUpdatedEvent::dispatch(TrackingData::fromApiArray($payload));
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
    private function syncWebhookLogMetadata(?JntOrder $shipment, string $billcode, array $biz): void
    {
        $attributes = [
            'tracking_number' => $billcode,
            'order_reference' => $shipment?->order_id ?? $this->extractOrderReference($biz),
            'order_id' => $shipment?->id,
            'digest' => $this->extractDigest(),
            'processing_status' => JntWebhookLog::STATUS_PROCESSED,
            'processing_error' => null,
            'processed_at' => CarbonImmutable::now(),
        ];

        if ($shipment !== null) {
            $attributes['owner_type'] = $shipment->owner_type;
            $attributes['owner_id'] = $shipment->owner_id;
        }

        JntWebhookLog::query()
            ->withoutOwnerScope()
            ->whereKey($this->webhookCall->getKey())
            ->update($attributes);
    }

    private function markWebhookLogAsFailed(string $error): void
    {
        JntWebhookLog::query()
            ->withoutOwnerScope()
            ->whereKey($this->webhookCall->getKey())
            ->update([
                'digest' => $this->extractDigest(),
                'processing_status' => JntWebhookLog::STATUS_FAILED,
                'processing_error' => $error,
                'processed_at' => CarbonImmutable::now(),
            ]);
    }

    /**
     * @param  array<string, mixed>  $biz
     */
    private function extractOrderReference(array $biz): ?string
    {
        $reference = $biz['orderId'] ?? $biz['order_id'] ?? $biz['txlogisticId'] ?? null;

        if (! is_scalar($reference)) {
            return null;
        }

        $reference = mb_trim((string) $reference);

        return $reference === '' ? null : mb_substr($reference, 0, 50);
    }

    private function extractDigest(): ?string
    {
        $headers = $this->webhookCall->headers;

        if (! is_array($headers)) {
            return null;
        }

        $digest = $headers['digest'][0]
            ?? $headers['Digest'][0]
            ?? $headers['DIGEST'][0]
            ?? null;

        if (! is_scalar($digest)) {
            return null;
        }

        $digest = mb_trim((string) $digest);

        return $digest === '' ? null : mb_substr($digest, 0, 255);
    }

    /**
     * @param  array<string, mixed>  $biz
     */
    private function syncShipmentTrackingFromWebhook(JntOrder $shipment, string $billcode, array $biz, ?TrackingStatus $status): void
    {
        DB::transaction(function () use ($shipment, $billcode, $biz, $status): void {
            $locked = $this->lockShipmentForUpdate($shipment);

            $this->insertMissingTrackingEvents($locked, $billcode, $this->cappedDetails($biz));

            $this->applyShipmentStatusUpdates($locked, $biz, $status);
        });
    }

    /**
     * @param  array<string, mixed>  $biz
     * @return list<array<string, mixed>>
     */
    private function cappedDetails(array $biz): array
    {
        $details = $biz['details'] ?? null;

        if (! is_array($details)) {
            return [];
        }

        $items = array_values(array_filter($details, is_array(...)));
        $max = max(1, (int) config('jnt.webhooks.max_details', 500));

        if (count($items) <= $max) {
            return $items;
        }

        Log::channel(config('jnt.logging.channel', 'stack'))
            ->warning('J&T webhook details truncated to configured maximum', [
                'webhook_call_id' => $this->webhookCall->id ?? null,
                'received' => count($items),
                'kept' => $max,
            ]);

        return array_slice($items, -$max);
    }

    /**
     * @param  list<array<string, mixed>>  $details
     */
    private function insertMissingTrackingEvents(JntOrder $shipment, string $billcode, array $details): void
    {
        if ($details === []) {
            return;
        }

        $rows = [];

        foreach ($details as $detail) {
            $scanTime = $this->parseScanTime($detail['scanTime'] ?? null);
            $scanTypeCode = $this->nullableString($detail['scanTypeCode'] ?? null);
            $description = $this->nullableString($detail['desc'] ?? null);

            $rows[] = [
                'id' => (string) Str::orderedUuid(),
                'event_hash' => TrackingEventHash::forIdentity(
                    orderId: (string) $shipment->id,
                    trackingNumber: $billcode,
                    scanTypeCode: $scanTypeCode,
                    scanTime: $scanTime,
                    description: $description,
                    ownerType: $shipment->owner_type,
                    ownerId: $shipment->owner_id,
                ),
                'order_id' => $shipment->id,
                'tracking_number' => $billcode,
                'scan_type_code' => $scanTypeCode,
                'scan_time' => $scanTime?->format('Y-m-d H:i:s'),
                'order_reference' => $shipment->order_id,
                'scan_type_name' => $this->nullableString($detail['scanTypeName'] ?? null),
                'scan_type' => $this->nullableString($detail['scanType'] ?? null),
                'description' => $description,
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
                'payload' => json_encode($detail, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'owner_type' => $shipment->owner_type,
                'owner_id' => $shipment->owner_id,
                'created_at' => CarbonImmutable::now()->format('Y-m-d H:i:s'),
                'updated_at' => CarbonImmutable::now()->format('Y-m-d H:i:s'),
            ];
        }

        $hashes = array_column($rows, 'event_hash');

        $existing = JntTrackingEvent::query()
            ->withoutOwnerScope()
            ->whereIn('event_hash', $hashes)
            ->pluck('event_hash')
            ->all();

        $missing = array_values(array_filter(
            $rows,
            static fn (array $row): bool => ! in_array($row['event_hash'], $existing, true),
        ));

        foreach (array_chunk($missing, 500) as $chunk) {
            JntTrackingEvent::query()->withoutOwnerScope()->insertOrIgnore($chunk);
        }
    }

    private function lockShipmentForUpdate(JntOrder $shipment): JntOrder
    {
        $query = JntOrder::query()->withoutOwnerScope()->whereKey($shipment->id);

        if (DB::connection()->getDriverName() !== 'sqlite') {
            $query->lockForUpdate();
        }

        return $query->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $biz
     */
    private function applyShipmentStatusUpdates(JntOrder $shipment, array $biz, ?TrackingStatus $status): void
    {
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

            if (($latestDetail['problemType'] ?? null) !== null) {
                if ($shipment->problem_at === null) {
                    $updates['problem_at'] = CarbonImmutable::now();
                }
            }

            if ($status === TrackingStatus::Exception) {
                if ($shipment->problem_at === null) {
                    $updates['problem_at'] = CarbonImmutable::now();
                }
                if ($shipment->exception_at === null) {
                    $updates['exception_at'] = CarbonImmutable::now();
                }
            }

            if ($status === TrackingStatus::Returned && $shipment->returned_at === null) {
                $updates['returned_at'] = CarbonImmutable::now();
            }

            if (($latestDetail['problemType'] ?? null) === null && $status !== TrackingStatus::Exception && $shipment->problem_at !== null) {
                $updates['resolved_at'] = CarbonImmutable::now();
                $updates['problem_at'] = null;
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

        $latest = null;
        $latestTimestamp = null;

        foreach ($details as $detail) {
            if (! is_array($detail)) {
                continue;
            }

            $timestamp = $this->parseScanTime($detail['scanTime'] ?? null)?->getTimestamp();

            if ($latest === null || ($timestamp ?? 0) >= ($latestTimestamp ?? 0)) {
                $latest = $detail;
                $latestTimestamp = $timestamp;
            }
        }

        return $latest;
    }

    private function parseScanTime(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (Throwable) {
            Log::channel(config('jnt.logging.channel', 'stack'))
                ->warning('J&T webhook ignoring unparseable scanTime', [
                    'webhook_call_id' => $this->webhookCall->id ?? null,
                    'scan_time_length' => mb_strlen($value),
                ]);

            return null;
        }
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $string = mb_trim((string) $value);

        return $string === '' ? null : $string;
    }
}
