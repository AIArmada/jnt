<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Data;

use Deprecated;

class OrderData
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
     * Create from API response array
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
     * @param  array<string, mixed>  $data
     */
    #[Deprecated(message: 'Use fromApiArray() instead')]
    public static function fromArray(array $data): self
    {
        return self::fromApiArray($data);
    }

    /**
     * Convert to API request array
     *
     * @return array<string, string|array>
     *
     * @phpstan-ignore missingType.iterableValue
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
        ], fn (string|array|null $value): bool => $value !== null);
    }

    /** @phpstan-ignore missingType.return */
    #[Deprecated(message: 'Use toApiArray() instead')]
    public function toArray()
    {
        return $this->toApiArray();
    }
}
