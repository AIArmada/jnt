<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Support;

use Carbon\CarbonImmutable;
use Throwable;

/**
 * Canonical identity hash for tracking events.
 *
 * Both ingestion paths (carrier webhooks and API syncs) hash the same
 * stable identity tuple so either path dedupes rows written by the other
 * through the `event_hash` unique index.
 */
final class TrackingEventHash
{
    public static function forIdentity(
        string $orderId,
        string $trackingNumber,
        ?string $scanTypeCode,
        CarbonImmutable | string | null $scanTime,
        ?string $description,
        ?string $ownerType,
        string | int | null $ownerId,
    ): string {
        $normalized = [
            'order_id' => $orderId,
            'tracking_number' => $trackingNumber,
            'scan_type_code' => $scanTypeCode !== null && mb_trim($scanTypeCode) !== '' ? $scanTypeCode : null,
            'scan_time' => self::normalizeScanTime($scanTime),
            'description' => $description !== null && mb_trim($description) !== '' ? $description : null,
            'owner_type' => $ownerType,
            'owner_id' => $ownerId !== null ? (string) $ownerId : null,
        ];

        $payload = json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return hash('sha256', is_string($payload) ? $payload : serialize($normalized));
    }

    private static function normalizeScanTime(CarbonImmutable | string | null $scanTime): ?string
    {
        if ($scanTime instanceof CarbonImmutable) {
            return $scanTime->toIso8601String();
        }

        if (! is_string($scanTime) || $scanTime === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($scanTime)->toIso8601String();
        } catch (Throwable) {
            return $scanTime;
        }
    }
}
