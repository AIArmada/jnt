<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates dimension range in centimeters.
 *
 * This rule ensures that package dimensions (length, width, height) are within
 * the acceptable range for the J&T Express API (0.01 to 999.99 cm).
 */
class DimensionInCentimeters implements ValidationRule
{
    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_numeric($value)) {
            $fail('The :attribute must be a number.');

            return;
        }

        $dimension = (float) $value;

        if ($dimension < 0.01 || $dimension > 999.99) {
            $fail('The :attribute must be between 0.01 and 999.99 cm');
        }
    }
}
