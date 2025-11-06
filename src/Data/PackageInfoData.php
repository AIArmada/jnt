<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Data;

use AIArmada\Jnt\Enums\GoodsType;
use AIArmada\Jnt\Support\TypeTransformer;

/**
 * Package Info Data
 *
 * Represents package information for a shipment.
 */
class PackageInfoData
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
        public readonly int|string $quantity,
        public readonly float|int|string $weight,
        public readonly float|int|string $value,
        public readonly GoodsType|string $goodsType,
        public readonly float|int|string|null $length = null,
        public readonly float|int|string|null $width = null,
        public readonly float|int|string|null $height = null,
    ) {}

    /**
     * Create from API response array
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
            weight: (float) $data['weight'], // API sends kg with 2 decimals
            value: (float) $data['packageValue'], // API sends MYR with 2 decimals
            goodsType: $goodsType,
            length: isset($data['length']) ? (float) $data['length'] : null, // API sends cm with 2 decimals
            width: isset($data['width']) ? (float) $data['width'] : null, // API sends cm with 2 decimals
            height: isset($data['height']) ? (float) $data['height'] : null, // API sends cm with 2 decimals
        );
    }

    /**
     * Convert to API request array
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
            'packageQuantity' => TypeTransformer::toIntegerString($this->quantity), // 1-999
            'weight' => TypeTransformer::forPackageWeight($this->weight), // KILOGRAMS with 2 decimals
            'packageValue' => TypeTransformer::forMoney($this->value), // MYR with 2 decimals
            'goodsType' => $goodsTypeValue,
            'length' => $this->length !== null ? TypeTransformer::forDimension($this->length) : null, // CENTIMETERS with 2 decimals
            'width' => $this->width !== null ? TypeTransformer::forDimension($this->width) : null, // CENTIMETERS with 2 decimals
            'height' => $this->height !== null ? TypeTransformer::forDimension($this->height) : null, // CENTIMETERS with 2 decimals
        ], fn (?string $value): bool => $value !== null);
    }
}
