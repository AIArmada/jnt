<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Data;

use AIArmada\Jnt\Exceptions\JntValidationException;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;
use Throwable;

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
        if (! isset($data['billCode']) || ! is_string($data['billCode']) || $data['billCode'] === '') {
            throw JntValidationException::requiredFieldMissing('billCode');
        }

        $rawDetails = $data['details'] ?? [];

        if (! is_array($rawDetails)) {
            throw JntValidationException::invalidFieldValue('details', $rawDetails, 'array');
        }

        $details = array_map(
            static function (mixed $detail): TrackingDetailData {
                if (! is_array($detail)) {
                    throw JntValidationException::invalidFieldValue('details.*', $detail, 'array');
                }

                return TrackingDetailData::fromApiArray($detail);
            },
            array_is_list($rawDetails) ? $rawDetails : [$rawDetails],
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
            ->sortByDesc(static function (TrackingDetailData $detail): int {
                try {
                    return CarbonImmutable::parse($detail->scanTime)->getTimestamp();
                } catch (Throwable) {
                    return 0;
                }
            })
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
