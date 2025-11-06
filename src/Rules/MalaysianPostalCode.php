<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates Malaysian postal code format (5 digits).
 *
 * This rule ensures that postal codes conform to the Malaysian postal code format
 * required by the J&T Express API. Postal codes must be exactly 5 digits.
 */
class MalaysianPostalCode implements ValidationRule
{
    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('The :attribute must be a string.');

            return;
        }

        if (in_array(preg_match('/^\d{5}$/', $value), [0, false], true)) {
            $fail('The :attribute must be 5 digits');
        }
    }
}
