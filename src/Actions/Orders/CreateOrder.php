<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Actions\Orders;

use AIArmada\Jnt\Data\AddressData;
use AIArmada\Jnt\Data\ItemData;
use AIArmada\Jnt\Data\OrderData;
use AIArmada\Jnt\Data\PackageInfoData;
use AIArmada\Jnt\Services\JntExpressService;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Create a shipping order via JNT Express.
 */
final class CreateOrder
{
    use AsAction;

    public function __construct(
        private readonly JntExpressService $jntService,
    ) {}

    /**
     * Create a new shipping order.
     *
     * @param  array<ItemData>  $items
     * @param  array<string, mixed>  $additionalData
     */
    public function handle(
        AddressData $sender,
        AddressData $receiver,
        array $items,
        PackageInfoData $packageInfo,
        ?string $orderId = null,
        array $additionalData = [],
    ): OrderData {
        return $this->jntService->createOrder(
            $sender,
            $receiver,
            $items,
            $packageInfo,
            $orderId,
            $additionalData
        );
    }
}
