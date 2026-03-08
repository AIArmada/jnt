<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Data;

use AIArmada\Jnt\Support\TypeTransformer;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Item data for JNT Express shipments.
 *
 * Represents a single item in a shipment.
 */
#[MapInputName(SnakeCaseMapper::class)]
#[MapOutputName(SnakeCaseMapper::class)]
class ItemData extends Data
{
    /**
     * @param  string  $name  Item name (max 200 chars, required)
     * @param  int|string  $quantity  Number of units (1-9999999, required, integer)
     * @param  float|int|string  $weight  Weight per unit in GRAMS (1-999999, required, integer)
     * @param  float|int|string  $price  Unit price in MYR (0.01-9999999.99, required, 2 decimals)
     * @param  string|null  $englishName  English name (max 200 chars, optional)
     * @param  string|null  $description  Item description (max 500 chars, optional)
     * @param  string  $currency  Currency code (default: MYR)
     */
    public function __construct(
        public readonly string $name,
        public readonly int | string $quantity,
        public readonly float | int | string $weight,
        public readonly float | int | string $price,
        public readonly ?string $englishName = null,
        public readonly ?string $description = null,
        public readonly string $currency = 'MYR',
    ) {}

    /**
     * Create from JNT API response array.
     *
     * @param  array<string, mixed>  $data  API response data
     */
    public static function fromApiArray(array $data): self
    {
        return new self(
            name: $data['itemName'],
            quantity: (int) $data['number'],
            weight: (float) $data['weight'],
            price: (float) $data['itemValue'],
            englishName: $data['englishName'] ?? null,
            description: $data['itemDesc'] ?? null,
            currency: $data['itemCurrency'] ?? 'MYR',
        );
    }

    /**
     * Convert to JNT API request array.
     *
     * Uses context-aware transformers to ensure correct formatting:
     * - quantity: Integer string (1-9999999)
     * - weight: Integer string in GRAMS (1-999999)
     * - price: Decimal string in MYR with 2 decimals (0.01-9999999.99)
     *
     * @return array<string, string>
     */
    public function toApiArray(): array
    {
        return array_filter([
            'itemName' => $this->name,
            'englishName' => $this->englishName,
            'number' => TypeTransformer::toIntegerString($this->quantity),
            'weight' => TypeTransformer::forItemWeight($this->weight),
            'itemValue' => TypeTransformer::forMoney($this->price),
            'itemCurrency' => $this->currency,
            'itemDesc' => $this->description,
        ], fn (?string $value): bool => $value !== null);
    }

    public function getTotalValue(): float
    {
        return (float) $this->price * (int) $this->quantity;
    }

    public function getTotalWeight(): float
    {
        return (float) $this->weight * (int) $this->quantity;
    }
}
