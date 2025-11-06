<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Events;

use AIArmada\Jnt\Enums\CancellationReason;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderCancelledEvent
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $response
     */
    public function __construct(
        public readonly string $orderId,
        public readonly CancellationReason $reason,
        public readonly array $response,
        public readonly ?string $trackingNumber = null
    ) {}

    public function getOrderId(): string
    {
        return $this->orderId;
    }

    public function getReason(): CancellationReason
    {
        return $this->reason;
    }

    public function getReasonDescription(): string
    {
        return $this->reason->getDescription();
    }

    public function getTrackingNumber(): ?string
    {
        return $this->trackingNumber;
    }

    public function hasTrackingNumber(): bool
    {
        return $this->trackingNumber !== null;
    }

    public function wasSuccessful(): bool
    {
        return ($this->response['code'] ?? 1) === 1;
    }

    public function getMessage(): string
    {
        return $this->response['msg'] ?? 'Order cancelled';
    }

    /**
     * @return array<string, mixed>
     */
    public function getResponse(): array
    {
        return $this->response;
    }
}
