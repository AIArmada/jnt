<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Actions\Tracking;

use AIArmada\Jnt\Data\TrackingData;
use AIArmada\Jnt\Services\JntExpressService;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Track a parcel via JNT Express.
 */
final class TrackParcel
{
    use AsAction;

    public function __construct(
        private readonly JntExpressService $jntService,
    ) {}

    /**
     * Track a parcel by order ID or tracking number.
     */
    public function handle(?string $orderId = null, ?string $trackingNumber = null): TrackingData
    {
        return $this->jntService->trackParcel($orderId, $trackingNumber);
    }
}
