<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Data;

use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Tracking detail data from JNT Express API.
 */
#[MapInputName(SnakeCaseMapper::class)]
#[MapOutputName(SnakeCaseMapper::class)]
class TrackingDetailData extends Data
{
    public function __construct(
        public readonly string $scanTime,
        public readonly string $description,
        public readonly string $scanTypeCode,
        public readonly string $scanTypeName,
        public readonly string $scanType,
        public readonly ?string $actualWeight = null,
        public readonly ?string $scanNetworkTypeName = null,
        public readonly ?string $scanNetworkName = null,
        public readonly ?string $staffName = null,
        public readonly ?string $staffContact = null,
        public readonly ?string $scanNetworkContact = null,
        public readonly ?string $scanNetworkProvince = null,
        public readonly ?string $scanNetworkCity = null,
        public readonly ?string $scanNetworkArea = null,
        public readonly ?string $signaturePictureUrl = null,
        public readonly ?string $longitude = null,
        public readonly ?string $latitude = null,
        public readonly ?string $timeZone = null,
        // Additional fields from complete API documentation
        public readonly ?string $otp = null,
        public readonly ?string $secondLevelTypeCode = null,
        public readonly ?string $wcTraceFlag = null,
        public readonly ?string $postCode = null,
        public readonly ?string $paymentStatus = null,
        public readonly ?string $paymentMethod = null,
        public readonly ?string $nextStopName = null,
        public readonly ?string $remark = null,
        public readonly ?string $nextNetworkProvinceName = null,
        public readonly ?string $nextNetworkCityName = null,
        public readonly ?string $nextNetworkAreaName = null,
        public readonly ?string $problemType = null,
        public readonly ?string $signUrl = null,
        public readonly ?string $electronicSignaturePicUrl = null,
        public readonly ?int $scanNetworkId = null,
        public readonly ?string $scanNetworkCountry = null,
    ) {}

    /**
     * Create from JNT API response array.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromApiArray(array $data): self
    {
        return new self(
            scanTime: $data['scanTime'],
            description: $data['desc'],
            scanTypeCode: $data['scanTypeCode'],
            scanTypeName: $data['scanTypeName'],
            scanType: $data['scanType'],
            actualWeight: $data['realWeight'] ?? null,
            scanNetworkTypeName: $data['scanNetworkTypeName'] ?? null,
            scanNetworkName: $data['scanNetworkName'] ?? null,
            staffName: $data['staffName'] ?? null,
            staffContact: $data['staffContact'] ?? null,
            scanNetworkContact: $data['scanNetworkContact'] ?? null,
            scanNetworkProvince: $data['scanNetworkProvince'] ?? null,
            scanNetworkCity: $data['scanNetworkCity'] ?? null,
            scanNetworkArea: $data['scanNetworkArea'] ?? null,
            signaturePictureUrl: $data['sigPicUrl'] ?? null,
            longitude: $data['longitude'] ?? null,
            latitude: $data['latitude'] ?? null,
            timeZone: $data['timeZone'] ?? null,
            otp: $data['otp'] ?? null,
            secondLevelTypeCode: $data['secondLevelTypeCode'] ?? null,
            wcTraceFlag: $data['wcTraceFlag'] ?? null,
            postCode: $data['postCode'] ?? null,
            paymentStatus: $data['paymentStatus'] ?? null,
            paymentMethod: $data['paymentMethod'] ?? null,
            nextStopName: $data['nextStopName'] ?? null,
            remark: $data['remark'] ?? null,
            nextNetworkProvinceName: $data['nextNetworkProvinceName'] ?? null,
            nextNetworkCityName: $data['nextNetworkCityName'] ?? null,
            nextNetworkAreaName: $data['nextNetworkAreaName'] ?? null,
            problemType: $data['problemType'] ?? null,
            signUrl: $data['signUrl'] ?? null,
            electronicSignaturePicUrl: $data['electronicSignaturePicUrl'] ?? null,
            scanNetworkId: isset($data['scanNetworkId']) ? (int) $data['scanNetworkId'] : null,
            scanNetworkCountry: $data['scanNetworkCountray'] ?? null, // API has typo
        );
    }

    /**
     * Convert to JNT API request array.
     *
     * @return array<string, string|int>
     */
    public function toApiArray(): array
    {
        return array_filter([
            'scanTime' => $this->scanTime,
            'desc' => $this->description,
            'scanTypeCode' => $this->scanTypeCode,
            'scanTypeName' => $this->scanTypeName,
            'scanType' => $this->scanType,
            'realWeight' => $this->actualWeight,
            'scanNetworkTypeName' => $this->scanNetworkTypeName,
            'scanNetworkName' => $this->scanNetworkName,
            'staffName' => $this->staffName,
            'staffContact' => $this->staffContact,
            'scanNetworkContact' => $this->scanNetworkContact,
            'scanNetworkProvince' => $this->scanNetworkProvince,
            'scanNetworkCity' => $this->scanNetworkCity,
            'scanNetworkArea' => $this->scanNetworkArea,
            'sigPicUrl' => $this->signaturePictureUrl,
            'longitude' => $this->longitude,
            'latitude' => $this->latitude,
            'timeZone' => $this->timeZone,
            'otp' => $this->otp,
            'secondLevelTypeCode' => $this->secondLevelTypeCode,
            'wcTraceFlag' => $this->wcTraceFlag,
            'postCode' => $this->postCode,
            'paymentStatus' => $this->paymentStatus,
            'paymentMethod' => $this->paymentMethod,
            'nextStopName' => $this->nextStopName,
            'remark' => $this->remark,
            'nextNetworkProvinceName' => $this->nextNetworkProvinceName,
            'nextNetworkCityName' => $this->nextNetworkCityName,
            'nextNetworkAreaName' => $this->nextNetworkAreaName,
            'problemType' => $this->problemType,
            'signUrl' => $this->signUrl,
            'electronicSignaturePicUrl' => $this->electronicSignaturePicUrl,
            'scanNetworkId' => $this->scanNetworkId,
            'scanNetworkCountray' => $this->scanNetworkCountry, // API has typo
        ], fn (string | int | null $value): bool => $value !== null);
    }

    public function getLocation(): string
    {
        return collect([
            $this->scanNetworkArea,
            $this->scanNetworkCity,
            $this->scanNetworkProvince,
        ])->filter()->implode(', ') ?: $this->scanNetworkName ?? '';
    }

    public function hasCoordinates(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    public function isDelivered(): bool
    {
        return in_array($this->scanType, ['SIGN', 'SIGN_STATION'], true);
    }

    public function isInTransit(): bool
    {
        return in_array($this->scanType, ['TMS_SIGN', 'ARRIVE', 'DEPARTURE', 'LOAD'], true);
    }
}
