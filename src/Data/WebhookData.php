<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Data;

use AIArmada\Jnt\Exceptions\JntValidationException;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;

/**
 * Webhook payload received from J&T Express servers.
 *
 * J&T sends tracking updates via POST webhooks with structure:
 * {
 *     "digest": "base64_signature",
 *     "bizContent": "{\"billCode\":\"JT001\",\"details\":[...]}",
 *     "apiAccount": "640826271705595946",
 *     "timestamp": "1622520000000"
 * }
 */
class WebhookData extends Data
{
    /**
     * @param  DataCollection<int, TrackingDetailData>  $details  Array of tracking update details
     */
    public function __construct(
        public readonly string $billCode,
        public readonly ?string $txlogisticId,
        #[DataCollectionOf(TrackingDetailData::class)]
        public readonly DataCollection $details,
    ) {}

    /**
     * Create from array of TrackingDetailData objects.
     *
     * @param  array<int, TrackingDetailData>  $details
     */
    public static function make(string $billCode, ?string $txlogisticId, array $details): self
    {
        return new self(
            billCode: $billCode,
            txlogisticId: $txlogisticId,
            details: new DataCollection(TrackingDetailData::class, $details),
        );
    }

    /**
     * Parse webhook payload from incoming request.
     *
     * @throws ValidationException
     */
    public static function fromRequest(Request $request): self
    {
        // Validate request has required fields
        $validated = $request->validate([
            'bizContent' => ['required', 'string'],
        ]);

        // Parse bizContent JSON
        $bizContent = json_decode((string) $validated['bizContent'], true);

        if (! is_array($bizContent)) {
            throw JntValidationException::invalidFormat('bizContent', 'valid JSON', $validated['bizContent']);
        }

        // Validate bizContent structure
        if (! isset($bizContent['billCode'])) {
            throw JntValidationException::requiredFieldMissing('billCode');
        }

        if (! isset($bizContent['details']) || ! is_array($bizContent['details'])) {
            throw JntValidationException::invalidFieldValue('details', 'array', gettype($bizContent['details'] ?? null));
        }

        // Parse tracking details
        $details = array_map(
            fn (array $detail): TrackingDetailData => TrackingDetailData::fromApiArray($detail),
            $bizContent['details']
        );

        return self::make(
            billCode: $bizContent['billCode'],
            txlogisticId: $bizContent['txlogisticId'] ?? null,
            details: $details,
        );
    }

    /**
     * Generate the standard J&T webhook acknowledgement response.
     *
     * J&T requires: {"code": "1", "msg": "success", "data": "SUCCESS"}
     *
     * @return array{code: string, msg: string, data: string, requestId: string}
     */
    public function toJntAckResponse(): array
    {
        return [
            'code' => '1',
            'msg' => 'success',
            'data' => 'SUCCESS',
            'requestId' => (string) Str::uuid(),
        ];
    }

    /**
     * @see \AIArmada\Jnt\Data\WebhookData::toJntAckResponse()
     * @deprecated Use toJntAckResponse() instead
     */
    public function toJntResponse(): array
    {
        return $this->toJntAckResponse();
    }

    /**
     * Get the latest tracking update.
     */
    public function getLatestDetail(): ?TrackingDetailData
    {
        if ($this->details->count() === 0) {
            return null;
        }

        return $this->details->last();
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

        return $latest !== null && $latest->isDelivered();
    }

    /**
     * Convert to array representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $latest = $this->getLatestDetail();

        return [
            'billCode' => $this->billCode,
            'txlogisticId' => $this->txlogisticId,
            'details' => array_map(fn (TrackingDetailData $detail): array => [
                'scanType' => $detail->scanType,
                'scanNetworkName' => $detail->scanNetworkName,
                'description' => $detail->description,
                'scanTime' => $detail->scanTime,
            ], $this->details->all()),
            'latestStatus' => $latest?->scanType,
            'latestLocation' => $latest?->scanNetworkName,
            'latestTime' => $latest?->scanTime,
        ];
    }
}
