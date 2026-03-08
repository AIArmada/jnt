<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Data;

use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Order data from JNT Express API.
 */
#[MapInputName(SnakeCaseMapper::class)]
#[MapOutputName(SnakeCaseMapper::class)]
class OrderData extends Data
{
    /**
     * @param  array<string>|null  $additionalTrackingNumbers
     */
    public function __construct(
        public readonly string $orderId,
        public readonly ?string $trackingNumber = null,
        public readonly ?string $sortingCode = null,
        public readonly ?string $thirdSortingCode = null,
        public readonly ?array $additionalTrackingNumbers = null,
        public readonly ?string $chargeableWeight = null,
    ) {}

    /**
     * Create from JNT API response array.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromApiArray(array $data): self
    {
        return new self(
            orderId: $data['txlogisticId'],
            trackingNumber: $data['billCode'] ?? null,
            sortingCode: $data['sortingCode'] ?? null,
            thirdSortingCode: $data['thirdSortingCode'] ?? null,
            additionalTrackingNumbers: $data['multipleVoteBillCodes'] ?? null,
            chargeableWeight: $data['packageChargeWeight'] ?? null,
        );
    }

    /**
     * Convert to JNT API request array.
     *
     * @return array<string, string|array<string>>
     */
    public function toApiArray(): array
    {
        return array_filter([
            'txlogisticId' => $this->orderId,
            'billCode' => $this->trackingNumber,
            'sortingCode' => $this->sortingCode,
            'thirdSortingCode' => $this->thirdSortingCode,
            'multipleVoteBillCodes' => $this->additionalTrackingNumbers,
            'packageChargeWeight' => $this->chargeableWeight,
        ], fn (string | array | null $value): bool => $value !== null);
    }

    public function hasTrackingNumber(): bool
    {
        return $this->trackingNumber !== null;
    }

    public function hasMultipleTrackingNumbers(): bool
    {
        return $this->additionalTrackingNumbers !== null && count($this->additionalTrackingNumbers) > 0;
    }

    /**
     * Get all tracking numbers including the primary one.
     *
     * @return array<string>
     */
    public function getAllTrackingNumbers(): array
    {
        $numbers = [];

        if ($this->trackingNumber !== null) {
            $numbers[] = $this->trackingNumber;
        }

        if ($this->additionalTrackingNumbers !== null) {
            $numbers = array_merge($numbers, $this->additionalTrackingNumbers);
        }

        return $numbers;
    }
}
