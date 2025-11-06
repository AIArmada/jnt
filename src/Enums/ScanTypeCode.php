<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Enums;

/**
 * Scan type codes representing different tracking statuses
 *
 * Based on official J&T Express API documentation
 */
enum ScanTypeCode: string
{
    // Cargo/Customs Operations (400-405)
    case PICKED_UP_FROM_CARGO = '400';
    case CUSTOMS_CLEARANCE_IN_PROCESS = '401';
    case CUSTOMS_CLEARANCE = '402';
    case DELIVERED_TO_HUB = '403';
    case PACKAGE_INBOUND = '404';
    case CENTER_INBOUND = '405';

    // Normal Flow (10-100)
    case PARCEL_PICKUP = '10';
    case OUTBOUND_SCAN = '20';
    case ARRIVAL = '30';
    case DELIVERY_SCAN = '94';
    case PARCEL_SIGNED = '100';

    // Problems & Returns (110-173)
    case PROBLEMATIC_SCANNING = '110';
    case RETURN_SCAN = '172';
    case RETURN_SIGN = '173';

    // Terminal/Abnormal States (200-306)
    case COLLECTED = '200';
    case DAMAGE_PARCEL = '201';
    case LOST_PARCEL = '300';
    case DISPOSE_PARCEL = '301';
    case REJECT_PARCEL = '302';
    case CUSTOMS_CONFISCATED = '303';
    case EXCEED_LIFE_CYCLE = '304';
    case CROSSBORDER_DISPOSE = '305';
    case COLLECTED_ALT = '306';

    /**
     * Create from string value with validation
     */
    public static function fromValue(string $value): ?self
    {
        return self::tryFrom($value);
    }

    /**
     * Get human-readable description of the scan type
     */
    public function getDescription(): string
    {
        return match ($this) {
            self::PICKED_UP_FROM_CARGO => 'Picked Up from Cargo Station',
            self::CUSTOMS_CLEARANCE_IN_PROCESS => 'Customs Clearance in Process',
            self::CUSTOMS_CLEARANCE => 'Customs Clearance',
            self::DELIVERED_TO_HUB => 'Delivered to Hub',
            self::PACKAGE_INBOUND => 'Package Inbound',
            self::CENTER_INBOUND => 'Center Inbound',
            self::PARCEL_PICKUP => 'Parcel Pickup',
            self::OUTBOUND_SCAN => 'Outbound Scan',
            self::ARRIVAL => 'Arrival',
            self::DELIVERY_SCAN => 'Delivery Scan',
            self::PARCEL_SIGNED => 'Parcel Signed',
            self::PROBLEMATIC_SCANNING => 'Problematic Scanning',
            self::RETURN_SCAN => 'Return Scan',
            self::RETURN_SIGN => 'Return Sign',
            self::COLLECTED, self::DAMAGE_PARCEL => 'Damage Parcel',
            self::LOST_PARCEL => 'Lost Parcel',
            self::DISPOSE_PARCEL => 'Dispose Parcel',
            self::REJECT_PARCEL => 'Reject Parcel',
            self::CUSTOMS_CONFISCATED => 'Customs Confiscated Parcel',
            self::EXCEED_LIFE_CYCLE => 'Exceed Life Cycle Parcel',
            self::CROSSBORDER_DISPOSE => 'Crossborder Dispose Parcel',
            self::COLLECTED_ALT => 'Collected',
        };
    }

    /**
     * Check if this scan type represents a terminal/final state
     */
    public function isTerminalState(): bool
    {
        return in_array($this->value, ['200', '201', '300', '301', '302', '303', '304', '305', '306'], true);
    }

    /**
     * Check if this scan type represents successful delivery
     */
    public function isSuccessfulDelivery(): bool
    {
        return $this === self::PARCEL_SIGNED;
    }

    /**
     * Check if this scan type represents a problem
     */
    public function isProblem(): bool
    {
        return $this === self::PROBLEMATIC_SCANNING || $this->isTerminalState();
    }

    /**
     * Check if this scan type is related to returns
     */
    public function isReturn(): bool
    {
        return $this === self::RETURN_SCAN || $this === self::RETURN_SIGN;
    }

    /**
     * Check if this scan type is related to customs
     */
    public function isCustoms(): bool
    {
        return in_array($this, [
            self::CUSTOMS_CLEARANCE_IN_PROCESS,
            self::CUSTOMS_CLEARANCE,
            self::CUSTOMS_CONFISCATED,
        ], true);
    }

    /**
     * Get the category of this scan type
     */
    public function getCategory(): string
    {
        return match (true) {
            $this->value >= '400' && $this->value <= '405' => 'Cargo/Customs', // @phpstan-ignore-line
            $this->value >= '10' && $this->value <= '100' => 'Normal Flow', // @phpstan-ignore-line
            $this->value >= '110' && $this->value <= '173' => 'Problems/Returns',
            $this->value >= '200' && $this->value <= '306' => 'Terminal States',
            default => 'Unknown',
        };
    }
}
