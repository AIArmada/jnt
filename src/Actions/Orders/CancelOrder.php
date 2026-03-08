<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Actions\Orders;

use AIArmada\Jnt\Enums\CancellationReason;
use AIArmada\Jnt\Services\JntExpressService;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Cancel a shipping order via JNT Express.
 */
final class CancelOrder
{
    use AsAction;

    public function __construct(
        private readonly JntExpressService $jntService,
    ) {}

    /**
     * Cancel an existing order.
     *
     * @return array<string, mixed>
     */
    public function handle(
        string $orderId,
        CancellationReason | string $reason,
        ?string $trackingNumber = null
    ): array {
        return $this->jntService->cancelOrder($orderId, $reason, $trackingNumber);
    }
}
