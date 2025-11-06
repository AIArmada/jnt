<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Data;

use AIArmada\Jnt\Exceptions\JntValidationException;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Webhook payload received from J&T Express servers
 *
 * J&T sends tracking updates via POST webhooks with structure:
 * {
 *     "digest": "base64_signature",
 *     "bizContent": "{\"billCode\":\"JT001\",\"details\":[...]}",
 *     "apiAccount": "640826271705595946",
 *     "timestamp": "1622520000000"
 * }
 */
readonly class WebhookData
{
    /**
     * @param  array<TrackingDetailData>  $details  Array of tracking update details
     */
    public function __construct(
        public string $billCode,
        public ?string $txlogisticId,
        public array $details,
    ) {}

    /**
     * Parse webhook payload from incoming request
     *
     * @throws \Illuminate\Validation\ValidationException
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

        return new self(
            billCode: $bizContent['billCode'],
            txlogisticId: $bizContent['txlogisticId'] ?? null,
            details: $details,
        );
    }

    /**
     * Generate successful webhook response for J&T
     *
     * J&T requires: {"code": "1", "msg": "success", "data": "SUCCESS"}
     *
     * @return array{code: string, msg: string, data: string, requestId: string}
     */
    public function toResponse(): array
    {
        return [
            'code' => '1',
            'msg' => 'success',
            'data' => 'SUCCESS',
            'requestId' => (string) Str::uuid(),
        ];
    }

    /**
     * Get the latest tracking update
     */
    public function getLatestDetail(): ?TrackingDetailData
    {
        if ($this->details === []) {
            return null;
        }

        return $this->details[array_key_last($this->details)] ?? null;
    }

    /**
     * Convert to array for logging or event dispatching
     *
     * @return array{
     *     billCode: string,
     *     txlogisticId: string|null,
     *     details: array,
     *     latestStatus: string|null,
     *     latestLocation: string|null,
     *     latestTime: string|null
     * }
     */
    /**
     * Convert to array representation
     *
     * @return array<string,mixed>
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
            ], $this->details),
            'latestStatus' => $latest?->scanType,
            'latestLocation' => $latest?->scanNetworkName,
            'latestTime' => $latest?->scanTime,
        ];
    }
}
