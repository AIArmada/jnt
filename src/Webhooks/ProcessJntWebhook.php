<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Webhooks;

use AIArmada\CommerceSupport\Webhooks\CommerceWebhookProcessor;
use AIArmada\Jnt\Enums\TrackingStatus;
use AIArmada\Jnt\Events\ParcelDelivered;
use AIArmada\Jnt\Events\ParcelInTransit;
use AIArmada\Jnt\Events\ParcelOutForDelivery;
use AIArmada\Jnt\Events\ParcelPickedUp;
use AIArmada\Jnt\Events\TrackingUpdated;
use AIArmada\Jnt\Models\JntOrder;
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

        // Update shipment status
        $newStatus = $this->mapToStatus($eventType, $biz);

        if ($newStatus) {
            $shipment->update(['status' => $newStatus->value]);

            // Dispatch specific events based on status
            $this->dispatchStatusEvent($shipment, $newStatus, $biz);
        }

        // Always dispatch generic tracking event
        TrackingUpdated::dispatch($billcode, $eventType, $biz);
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
}
