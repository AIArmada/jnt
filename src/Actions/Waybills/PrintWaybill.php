<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Actions\Waybills;

use AIArmada\Jnt\Services\JntExpressService;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Print a waybill via JNT Express.
 */
final class PrintWaybill
{
    use AsAction;

    public function __construct(
        private readonly JntExpressService $jntService,
    ) {}

    /**
     * Print a waybill for an order.
     *
     * @return array<string, mixed>
     */
    public function handle(
        string $orderId,
        ?string $trackingNumber = null,
        ?string $templateName = null
    ): array {
        return $this->jntService->printOrder($orderId, $trackingNumber, $templateName);
    }
}
