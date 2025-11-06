<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates price/monetary value range.
 *
 * This rule ensures that prices and monetary values are within the acceptable range
 * for the J&T Express API (0.01 to 999,999.99).
 */
class MonetaryValue implements ValidationRule
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

        $amount = (float) $value;

        if ($amount < 0.01 || $amount > 999999.99) {
            $fail('The :attribute must be between 0.01 and 999,999.99');
        }
    }
}
