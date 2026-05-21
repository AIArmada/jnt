<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Data;

use Carbon\CarbonImmutable;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;

/**
 * Tracking data from JNT Express API.
 */
class TrackingData extends Data
{
    /**
     * @param  DataCollection<int, TrackingDetailData>  $details
     */
    public function __construct(
        public readonly string $trackingNumber,
        #[DataCollectionOf(TrackingDetailData::class)]
        public readonly DataCollection $details,
        public readonly ?string $orderId = null,
    ) {}

    /**
     * Create from array of TrackingDetailData objects.
     *
     * @param  array<int, TrackingDetailData>  $details
     */
    public static function make(string $trackingNumber, array $details, ?string $orderId = null): self
    {
        return new self(
            trackingNumber: $trackingNumber,
            details: new DataCollection(TrackingDetailData::class, $details),
            orderId: $orderId,
        );
    }

    /**
     * Create from JNT API response array.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromApiArray(array $data): self
    {
        $details = array_map(
            fn (array $detail): TrackingDetailData => TrackingDetailData::fromApiArray($detail),
            $data['details'] ?? []
        );

        return self::make(
            trackingNumber: $data['billCode'],
            details: $details,
            orderId: $data['txlogisticId'] ?? null,
        );
    }

    /**
     * Convert to JNT API request array.
     *
     * @return array{billCode: string, txlogisticId: string|null, details: array<int, array<string, mixed>>}
     */
    public function toApiArray(): array
    {
        return [
            'billCode' => $this->trackingNumber,
            'txlogisticId' => $this->orderId,
            'details' => $this->details->toArray(),
        ];
    }

    public function getLatestDetail(): ?TrackingDetailData
    {
        if ($this->details->count() === 0) {
            return null;
        }

        /** @var TrackingDetailData|null $latestDetail */
        $latestDetail = $this->details
            ->toCollection()
            ->sortByDesc(fn (TrackingDetailData $detail): int => CarbonImmutable::parse($detail->scanTime)->getTimestamp())
            ->first();

        return $latestDetail;
    }

    public function getLatestStatus(): ?string
    {
        return $this->getLatestDetail()?->scanType;
    }

    public function getLatestLocation(): ?string
    {
        return $this->getLatestDetail()?->scanNetworkName;
    }

    public function isDelivered(): bool
    {
        $latest = $this->getLatestDetail();

        return $latest !== null && in_array($latest->scanType, ['SIGN', 'SIGN_STATION'], true);
    }
}
