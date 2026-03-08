<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Enums;

/**
 * Normalized tracking status enum for unified order tracking
 *
 * Maps J&T Express scan type codes to application-friendly status values.
 */
enum TrackingStatus: string
{
    case Pending = 'pending';
    case PickedUp = 'picked_up';
    case InTransit = 'in_transit';
    case AtHub = 'at_hub';
    case OutForDelivery = 'out_for_delivery';
    case DeliveryAttempted = 'delivery_attempted';
    case Delivered = 'delivered';
    case ReturnInitiated = 'return_initiated';
    case Returned = 'returned';
    case Exception = 'exception';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::PickedUp => 'Picked Up',
            self::InTransit => 'In Transit',
            self::AtHub => 'At Hub',
            self::OutForDelivery => 'Out for Delivery',
            self::DeliveryAttempted => 'Delivery Attempted',
            self::Delivered => 'Delivered',
            self::ReturnInitiated => 'Return Initiated',
            self::Returned => 'Returned',
            self::Exception => 'Exception',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Pending => 'heroicon-o-clock',
            self::PickedUp => 'heroicon-o-truck',
            self::InTransit => 'heroicon-o-arrow-path',
            self::AtHub => 'heroicon-o-building-office',
            self::OutForDelivery => 'heroicon-o-map-pin',
            self::DeliveryAttempted => 'heroicon-o-exclamation-circle',
            self::Delivered => 'heroicon-o-check-circle',
            self::ReturnInitiated, self::Returned => 'heroicon-o-arrow-uturn-left',
            self::Exception => 'heroicon-o-x-circle',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'gray',
            self::PickedUp, self::InTransit, self::AtHub => 'blue',
            self::OutForDelivery => 'yellow',
            self::DeliveryAttempted => 'orange',
            self::Delivered => 'green',
            self::ReturnInitiated, self::Returned => 'purple',
            self::Exception => 'red',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [
            self::Delivered,
            self::Returned,
            self::Exception,
        ], true);
    }

    public function isSuccessful(): bool
    {
        return $this === self::Delivered;
    }

    public function isInProgress(): bool
    {
        return in_array($this, [
            self::PickedUp,
            self::InTransit,
            self::AtHub,
            self::OutForDelivery,
            self::DeliveryAttempted,
        ], true);
    }

    public function isReturn(): bool
    {
        return in_array($this, [
            self::ReturnInitiated,
            self::Returned,
        ], true);
    }

    public function requiresAttention(): bool
    {
        return in_array($this, [
            self::DeliveryAttempted,
            self::ReturnInitiated,
            self::Exception,
        ], true);
    }
}
