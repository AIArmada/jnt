<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Contracts;

use AIArmada\Jnt\Enums\TrackingStatus;
use AIArmada\Shipping\Enums\TrackingStatus as NormalizedTrackingStatus;

/**
 * Strategy interface for mapping carrier-specific tracking codes
 * to normalized tracking statuses.
 *
 * Each carrier (JNT, Pos Malaysia, DHL, etc.) registers its own
 * strategy through the StatusMappingStrategyRegistry.
 */
interface StatusMappingStrategyInterface
{
    /**
     * Get the carrier code this strategy handles.
     */
    public function getCarrierCode(): string;

    /**
     * Map a carrier-specific event code to the normalized shipping status.
     */
    public function map(string $carrierEventCode): NormalizedTrackingStatus;

    /**
     * Map carrier-specific tracking status to the package's TrackingStatus enum.
     */
    public function resolve(?string $scanTypeCode = null, ?string $statusDescription = null): TrackingStatus;
}
