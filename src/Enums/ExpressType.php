<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Enums;

enum ExpressType: string
{
    case DOMESTIC = 'EZ';
    case NEXT_DAY = 'EX';
    case FRESH = 'FD';
    case DOOR_TO_DOOR = 'DO';
    case SAME_DAY = 'JS';

    public function label(): string
    {
        return match ($this) {
            self::DOMESTIC => 'Domestic Standard',
            self::NEXT_DAY => 'Express Next Day',
            self::FRESH => 'Fresh Delivery',
            self::DOOR_TO_DOOR => 'Door to Door',
            self::SAME_DAY => 'Same Day',
        };
    }
}
