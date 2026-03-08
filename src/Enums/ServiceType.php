<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Enums;

enum ServiceType: string
{
    case DOOR_TO_DOOR = '1';
    case WALK_IN = '6';

    public function label(): string
    {
        return match ($this) {
            self::DOOR_TO_DOOR => 'Door to Door',
            self::WALK_IN => 'Walk-In',
        };
    }
}
