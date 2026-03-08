<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Enums;

enum PaymentType: string
{
    case PREPAID_POSTPAID = 'PP_PM';
    case PREPAID_CASH = 'PP_CASH';
    case COLLECT_CASH = 'CC_CASH';

    public function label(): string
    {
        return match ($this) {
            self::PREPAID_POSTPAID => 'Prepaid - Postpaid by Merchant',
            self::PREPAID_CASH => 'Prepaid - Cash',
            self::COLLECT_CASH => 'Cash on Delivery',
        };
    }
}
