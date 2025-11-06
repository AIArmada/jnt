<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Data;

use Deprecated;

class TrackingData
{
    /**
     * @param  array<TrackingDetailData>  $details
     */
    public function __construct(
        public readonly string $trackingNumber,
        public readonly array $details,
        public readonly ?string $orderId = null,
    ) {}

    /**
     * Create from API response array
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromApiArray(array $data): self
    {
        $details = array_map(
            fn (array $detail): TrackingDetailData => TrackingDetailData::fromApiArray($detail),
            $data['details'] ?? []
        );

        return new self(
            trackingNumber: $data['billCode'],
            details: $details,
            orderId: $data['txlogisticId'] ?? null,
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
     * @return array{billCode: string, txlogisticId: string, details: array<int, array<string, mixed>>}
     */
    public function toApiArray(): array
    {
        return [
            'billCode' => $this->trackingNumber,
            'txlogisticId' => $this->orderId,
            'details' => array_map(fn (TrackingDetailData $detail): array => $detail->toApiArray(), $this->details),
        ];
    }

    /** @phpstan-ignore missingType.return */
    #[Deprecated(message: 'Use toApiArray() instead')]
    public function toArray()
    {
        return $this->toApiArray();
    }
}
