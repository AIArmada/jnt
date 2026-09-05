<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Support;

use InvalidArgumentException;

/**
 * Handles type transformations between developer-friendly types and J&T API requirements
 *
 * Uses context-aware methods to handle different unit requirements:
 * - Item weights: GRAMS (integer)
 * - Package weights: KILOGRAMS (2 decimals)
 * - Dimensions: CENTIMETERS (2 decimals)
 * - Money: MALAYSIAN RINGGIT (2 decimals)
 */
class TypeTransformer
{
    /**
     * Convert to integer string (for quantities, counts, etc.)
     *
     * API Format: String(1-999) - integer values sent as strings
     *
     * @return string Integer formatted as string
     *
     * @example
     * TypeTransformer::toIntegerString(5) → "5"
     * TypeTransformer::toIntegerString(5.7) → "5"
     * TypeTransformer::toIntegerString("5") → "5"
     */
    public static function toIntegerString(int | float | string $value): string
    {
        return (string) (int) $value;
    }

    /**
     * Convert to N-decimal float string
     *
     * API Format: String with exact decimal places
     *
     * @param  int  $decimals  Number of decimal places (default: 2)
     * @return string Float formatted as string with N decimal places
     *
     * @example
     * TypeTransformer::toDecimalString(5, 2) → "5.00"
     * TypeTransformer::toDecimalString(5.1, 2) → "5.10"
     * TypeTransformer::toDecimalString(5.456, 2) → "5.46"
     */
    public static function toDecimalString(float | int | string $value, int $decimals = 2): string
    {
        return number_format((float) $value, $decimals, '.', '');
    }

    /**
     * Transform item weight (GRAMS → integer string)
     *
     * Items are measured in GRAMS and sent as INTEGER strings to the API.
     * The API expects String(1-9999) format for item weights.
     *
     * @param  float|int|string  $grams  Weight in grams (1-9999)
     * @return string Weight as integer string
     *
     * @example
     * TypeTransformer::forItemWeight(500) → "500"
     * TypeTransformer::forItemWeight(500.5) → "500"
     * TypeTransformer::forItemWeight("500") → "500"
     */
    public static function forItemWeight(float | int | string $grams): string
    {
        return self::toIntegerString($grams);
    }

    /**
     * Transform package weight (KILOGRAMS → 2 decimal string)
     *
     * Packages are measured in KILOGRAMS and sent with 2 DECIMALS to the API.
     * The API expects String(0.01-999.99) format for package weights.
     *
     * @param  float|int|string  $kg  Weight in kilograms (0.01-999.99)
     * @return string Weight as 2-decimal string
     *
     * @example
     * TypeTransformer::forPackageWeight(5) → "5.00"
     * TypeTransformer::forPackageWeight(5.5) → "5.50"
     * TypeTransformer::forPackageWeight(5.456) → "5.46"
     * TypeTransformer::forPackageWeight("5.5") → "5.50"
     */
    public static function forPackageWeight(float | int | string $kg): string
    {
        return self::toDecimalString($kg, 2);
    }

    /**
     * Transform dimension (CENTIMETERS → 2 decimal string)
     *
     * Dimensions are measured in CENTIMETERS and sent with 2 DECIMALS to the API.
     * The API expects String(0.01-999.99) format for dimensions.
     *
     * @param  float|int|string  $cm  Dimension in centimeters (0.01-999.99)
     * @return string Dimension as 2-decimal string
     *
     * @example
     * TypeTransformer::forDimension(25) → "25.00"
     * TypeTransformer::forDimension(25.5) → "25.50"
     * TypeTransformer::forDimension(25.756) → "25.76"
     * TypeTransformer::forDimension("25") → "25.00"
     */
    public static function forDimension(float | int | string $cm): string
    {
        return self::toDecimalString($cm, 2);
    }

    /**
     * Convert a major-unit API value to integer minor units.
     *
     * The J&T API represents money as a decimal string in MYR. Domain data
     * represents money as integer sen, so conversion happens only at this
     * integration boundary.
     */
    public static function moneyToMinor(int | float | string $major): int
    {
        if (is_int($major)) {
            return $major * 100;
        }

        if (is_float($major)) {
            if (! is_finite($major)) {
                throw new InvalidArgumentException('Money must be a finite number.');
            }

            return (int) round($major * 100);
        }

        $normalized = str_replace([',', ' '], '', mb_trim($major));

        if (! preg_match('/^-?\d+(?:\.\d{1,2})?$/', $normalized)) {
            throw new InvalidArgumentException("Invalid money value: {$major}");
        }

        $negative = str_starts_with($normalized, '-');
        $unsigned = mb_ltrim($normalized, '+-');
        [$whole, $fraction] = array_pad(explode('.', $unsigned, 2), 2, '');
        $minor = ((int) $whole * 100) + (int) mb_str_pad($fraction, 2, '0');

        return $negative ? -$minor : $minor;
    }

    /**
     * Transform integer minor units to the J&T major-unit money string.
     */
    public static function forMoney(int $minor): string
    {
        $negative = $minor < 0;
        $absolute = abs($minor);
        $value = intdiv($absolute, 100) . '.' . mb_str_pad((string) ($absolute % 100), 2, '0', STR_PAD_LEFT);

        return $negative ? '-' . $value : $value;
    }

    /**
     * Convert boolean to Y/N string
     *
     * API Format: String(Y/N) - boolean flags sent as Y or N
     *
     * @param  bool|string  $value  Boolean value or Y/N string
     * @return string 'Y' or 'N'
     *
     * @example
     * TypeTransformer::toBooleanString(true) → "Y"
     * TypeTransformer::toBooleanString(false) → "N"
     * TypeTransformer::toBooleanString("Y") → "Y"
     * TypeTransformer::toBooleanString("n") → "N"
     */
    public static function toBooleanString(bool | string $value): string
    {
        if (is_string($value)) {
            return mb_strtoupper($value) === 'Y' ? 'Y' : 'N';
        }

        return $value ? 'Y' : 'N';
    }

    /**
     * Convert Y/N string to boolean
     *
     * @param  string|bool  $value  Y/N string or boolean
     *
     * @example
     * TypeTransformer::fromBooleanString('Y') → true
     * TypeTransformer::fromBooleanString('N') → false
     * TypeTransformer::fromBooleanString(true) → true
     */
    public static function fromBooleanString(string | bool $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return mb_strtoupper($value) === 'Y';
    }
}
