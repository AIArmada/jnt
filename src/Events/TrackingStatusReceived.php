<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Events;

use AIArmada\Jnt\Data\WebhookData;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event dispatched when a tracking status update is received via webhook.
 *
 * This event is fired whenever J&T Express sends a webhook notification
 * about a tracking status change. Applications can listen to this event
 * to update their order statuses, send customer notifications, etc.
 *
 * @example
 * // In your EventServiceProvider:
 * protected $listen = [
 *     \AIArmada\Jnt\Events\TrackingStatusReceived::class => [
 *         \App\Listeners\UpdateOrderTracking::class,
 *         \App\Listeners\NotifyCustomer::class,
 *     ],
 * ];
 */
class TrackingStatusReceived
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param  WebhookData  $webhookData  The parsed webhook data from J&T
     */
    public function __construct(
        public readonly WebhookData $webhookData
    ) {}

    /**
     * Get the tracking number (bill code).
     */
    public function getBillCode(): string
    {
        return $this->webhookData->billCode;
    }

    /**
     * Get the customer order ID (if available).
     */
    public function getTxlogisticId(): ?string
    {
        return $this->webhookData->txlogisticId;
    }

    /**
     * Get the latest tracking status.
     */
    public function getLatestStatus(): ?string
    {
        return $this->webhookData->getLatestDetail()?->scanType;
    }

    /**
     * Get the latest tracking description.
     */
    public function getLatestDescription(): ?string
    {
        return $this->webhookData->getLatestDetail()?->description;
    }

    /**
     * Get the latest tracking location.
     */
    public function getLatestLocation(): ?string
    {
        $detail = $this->webhookData->getLatestDetail();

        if (! $detail instanceof \AIArmada\Jnt\Data\TrackingDetailData) {
            return null;
        }

        $parts = array_filter([
            $detail->scanNetworkName,
            $detail->scanNetworkCity,
            $detail->scanNetworkProvince,
        ]);

        return $parts === [] ? null : implode(', ', $parts);
    }

    /**
     * Get the latest tracking timestamp.
     */
    public function getLatestTimestamp(): ?string
    {
        return $this->webhookData->getLatestDetail()?->scanTime;
    }

    /**
     * Check if this is a delivery event.
     */
    public function isDelivered(): bool
    {
        $status = $this->getLatestStatus();

        return $status === 'delivery' || $status === 'signed';
    }

    /**
     * Check if this is a collection event.
     */
    public function isCollected(): bool
    {
        return $this->getLatestStatus() === 'collection';
    }

    /**
     * Check if there's a problem.
     */
    public function hasProblem(): bool
    {
        $status = $this->getLatestStatus();

        return in_array($status, ['problem', 'return', 'reject']);
    }
}
