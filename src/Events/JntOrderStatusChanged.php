<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Events;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Jnt\Enums\TrackingStatus;
use AIArmada\Jnt\Models\JntOrder;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;

final class JntOrderStatusChanged
{
    use Dispatchable;
    use InteractsWithSockets;

    public function __construct(
        public readonly string $orderKey,
        public readonly ?string $orderReference,
        public readonly ?string $trackingNumber,
        public readonly ?string $ownerType,
        public readonly string | int | null $ownerId,
        public readonly TrackingStatus $currentStatus,
        public readonly ?string $previousStatusCode = null,
    ) {}

    public static function fromOrder(JntOrder $order, TrackingStatus $currentStatus, ?string $previousStatusCode = null): self
    {
        return new self(
            orderKey: (string) $order->getKey(),
            orderReference: $order->order_id,
            trackingNumber: $order->tracking_number,
            ownerType: $order->owner_type,
            ownerId: $order->owner_id,
            currentStatus: $currentStatus,
            previousStatusCode: $previousStatusCode,
        );
    }

    public function owner(): ?Model
    {
        return OwnerContext::fromTypeAndId($this->ownerType, $this->ownerId);
    }

    public function resolveOrder(): ?JntOrder
    {
        if ($this->orderKey === '') {
            return null;
        }

        return JntOrder::query()
            ->forOwner($this->owner(), false)
            ->whereKey($this->orderKey)
            ->first();
    }

    public function getOrderId(): string
    {
        return $this->orderReference ?? '';
    }

    public function getTrackingNumber(): ?string
    {
        return $this->trackingNumber;
    }

    public function isDelivered(): bool
    {
        return $this->currentStatus === TrackingStatus::Delivered;
    }

    public function hasException(): bool
    {
        return $this->currentStatus === TrackingStatus::Exception;
    }

    public function isReturning(): bool
    {
        return $this->currentStatus->isReturn();
    }

    public function requiresAttention(): bool
    {
        return $this->currentStatus->requiresAttention();
    }

    public function isTerminal(): bool
    {
        return $this->currentStatus->isTerminal();
    }
}
