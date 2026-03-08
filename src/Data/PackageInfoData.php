<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Data;

use AIArmada\Jnt\Enums\GoodsType;
use AIArmada\Jnt\Support\TypeTransformer;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Package info data for JNT Express shipments.
 *
 * Represents package information for a shipment.
 */
#[MapInputName(SnakeCaseMapper::class)]
#[MapOutputName(SnakeCaseMapper::class)]
class PackageInfoData extends Data
{
    /**
     * @param  int|string  $quantity  Number of packages (1-999, required, integer)
     * @param  float|int|string  $weight  Total weight in KILOGRAMS (0.01-999.99, required, 2 decimals)
     * @param  float|int|string  $value  Declared value in MYR (0.01-999999.99, required, 2 decimals)
     * @param  GoodsType|string  $goodsType  Type of goods (ITN2=Document, ITN8=Package, required)
     * @param  float|int|string|null  $length  Length in CENTIMETERS (0.01-999.99, optional, 2 decimals)
     * @param  float|int|string|null  $width  Width in CENTIMETERS (0.01-999.99, optional, 2 decimals)
     * @param  float|int|string|null  $height  Height in CENTIMETERS (0.01-999.99, optional, 2 decimals)
     */
    public function __construct(
        public readonly int | string $quantity,
        public readonly float | int | string $weight,
        public readonly float | int | string $value,
        public readonly GoodsType | string $goodsType,
        public readonly float | int | string | null $length = null,
        public readonly float | int | string | null $width = null,
        public readonly float | int | string | null $height = null,
    ) {}

    /**
     * Create from JNT API response array.
     *
     * @param  array<string, mixed>  $data  API response data
     */
    public static function fromApiArray(array $data): self
    {
        $goodsType = isset($data['goodsType']) && is_string($data['goodsType'])
            ? GoodsType::tryFrom($data['goodsType']) ?? $data['goodsType']
            : $data['goodsType'];

        return new self(
            quantity: (int) $data['packageQuantity'],
            weight: (float) $data['weight'],
            value: (float) $data['packageValue'],
            goodsType: $goodsType,
            length: isset($data['length']) ? (float) $data['length'] : null,
            width: isset($data['width']) ? (float) $data['width'] : null,
            height: isset($data['height']) ? (float) $data['height'] : null,
        );
    }

    /**
     * Convert to JNT API request array.
     *
     * Uses context-aware transformers to ensure correct formatting:
     * - quantity: Integer string (1-999)
     * - weight: Decimal string in KILOGRAMS with 2 decimals (0.01-999.99)
     * - value: Decimal string in MYR with 2 decimals (0.01-999999.99)
     * - dimensions: Decimal strings in CENTIMETERS with 2 decimals (0.01-999.99)
     *
     * @return array<string, string>
     */
    public function toApiArray(): array
    {
        $goodsTypeValue = $this->goodsType instanceof GoodsType
            ? $this->goodsType->value
            : $this->goodsType;

        return array_filter([
            'packageQuantity' => TypeTransformer::toIntegerString($this->quantity),
            'weight' => TypeTransformer::forPackageWeight($this->weight),
            'packageValue' => TypeTransformer::forMoney($this->value),
            'goodsType' => $goodsTypeValue,
            'length' => $this->length !== null ? TypeTransformer::forDimension($this->length) : null,
            'width' => $this->width !== null ? TypeTransformer::forDimension($this->width) : null,
            'height' => $this->height !== null ? TypeTransformer::forDimension($this->height) : null,
        ], fn (?string $value): bool => $value !== null);
    }

    public function hasAllDimensions(): bool
    {
        return $this->length !== null && $this->width !== null && $this->height !== null;
    }

    /**
     * Calculate volumetric weight in KG.
     *
     * @param  int  $divisor  The volumetric divisor (default 5000 for courier)
     */
    public function getVolumetricWeight(int $divisor = 5000): ?float
    {
        if (! $this->hasAllDimensions()) {
            return null;
        }

        return ((float) $this->length * (float) $this->width * (float) $this->height) / $divisor;
    }

    /**
     * Get chargeable weight (max of actual and volumetric).
     */
    public function getChargeableWeight(): float
    {
        $volumetric = $this->getVolumetricWeight();

        if ($volumetric === null) {
            return (float) $this->weight;
        }

        return max((float) $this->weight, $volumetric);
    }

    public function isDocument(): bool
    {
        if ($this->goodsType instanceof GoodsType) {
            return $this->goodsType === GoodsType::DOCUMENT;
        }

        return $this->goodsType === 'ITN2';
    }
}
