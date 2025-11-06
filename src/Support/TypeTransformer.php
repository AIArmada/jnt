<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Support;

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
    public static function toIntegerString(int|float|string $value): string
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
    public static function toDecimalString(float|int|string $value, int $decimals = 2): string
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
    public static function forItemWeight(float|int|string $grams): string
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
    public static function forPackageWeight(float|int|string $kg): string
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
    public static function forDimension(float|int|string $cm): string
    {
        return self::toDecimalString($cm, 2);
    }

    /**
     * Transform money (MALAYSIAN RINGGIT → 2 decimal string)
     *
     * Money is in MALAYSIAN RINGGIT (MYR) and sent with 2 DECIMALS to the API.
     * The API expects String(0.01-999999.99) format for monetary values.
     *
     * @param  float|int|string  $myr  Amount in MYR (0.01-999999.99)
     * @return string Money as 2-decimal string
     *
     * @example
     * TypeTransformer::forMoney(19.9) → "19.90"
     * TypeTransformer::forMoney(150) → "150.00"
     * TypeTransformer::forMoney(150.5) → "150.50"
     * TypeTransformer::forMoney("150") → "150.00"
     */
    public static function forMoney(float|int|string $myr): string
    {
        return self::toDecimalString($myr, 2);
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
    public static function toBooleanString(bool|string $value): string
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
    public static function fromBooleanString(string|bool $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return mb_strtoupper($value) === 'Y';
    }
}
