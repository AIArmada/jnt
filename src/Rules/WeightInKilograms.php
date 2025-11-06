<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates weight range in kilograms.
 *
 * This rule ensures that package weights are within the acceptable range
 * for the J&T Express API (0.01 to 999.99 kg).
 */
class WeightInKilograms implements ValidationRule
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

        $weight = (float) $value;

        if ($weight < 0.01 || $weight > 999.99) {
            $fail('The :attribute must be between 0.01 and 999.99 kg');
        }
    }
}
