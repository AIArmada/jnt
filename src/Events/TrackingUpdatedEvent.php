<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Events;

use AIArmada\Jnt\Data\TrackingData;
use AIArmada\Jnt\Data\TrackingDetailData;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class TrackingUpdatedEvent
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(public readonly TrackingData $tracking) {}

    public function getOrderId(): ?string
    {
        return $this->tracking->orderId;
    }

    public function getTrackingNumber(): string
    {
        return $this->tracking->trackingNumber;
    }

    public function getLatestStatus(): ?string
    {
        if ($this->tracking->details->count() === 0) {
            return null;
        }

        $latest = $this->tracking->details->last();

        return $latest instanceof TrackingDetailData ? $latest->scanType : null;
    }

    public function getLatestDescription(): ?string
    {
        if ($this->tracking->details->count() === 0) {
            return null;
        }

        $latest = $this->tracking->details->last();

        return $latest instanceof TrackingDetailData ? $latest->description : null;
    }

    public function getLatestLocation(): ?string
    {
        if ($this->tracking->details->count() === 0) {
            return null;
        }

        $latest = $this->tracking->details->last();

        if (! ($latest instanceof TrackingDetailData)) {
            return null;
        }

        $parts = array_filter([
            $latest->scanNetworkCity ?? null,
            $latest->scanNetworkProvince ?? null,
        ]);

        return $parts === [] ? null : implode(', ', $parts);
    }

    public function isDelivered(): bool
    {
        return $this->tracking->details->toCollection()->some(
            fn (TrackingDetailData $detail): bool => in_array($detail->scanType, ['DELIVER', 'SIGNED'], true)
        );
    }

    public function isInTransit(): bool
    {
        return $this->tracking->details->toCollection()->some(
            fn (TrackingDetailData $detail): bool => in_array($detail->scanType, ['TRANSFER', 'ARRIVAL'], true)
        );
    }

    public function hasProblems(): bool
    {
        return $this->tracking->details->toCollection()->some(
            fn (TrackingDetailData $detail): bool => in_array($detail->scanType, ['RETURN', 'REJECT', 'PROBLEM'], true)
        );
    }

    public function isCollected(): bool
    {
        return $this->tracking->details->toCollection()->some(
            fn (TrackingDetailData $detail): bool => $detail->scanType === 'COLLECT'
        );
    }

    /**
     * @return array<int, TrackingDetailData>
     */
    public function getDetails(): array
    {
        return $this->tracking->details->all();
    }

    public function getDetailCount(): int
    {
        return $this->tracking->details->count();
    }
}
