<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates weight range in grams.
 *
 * This rule ensures that item weights are within the acceptable range
 * for the J&T Express API (1 to 999,999 grams).
 */
class WeightInGrams implements ValidationRule
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

        $weight = (int) $value;

        if ($weight < 1 || $weight > 999999) {
            $fail('The :attribute must be between 1 and 999,999 grams');
        }
    }
}
