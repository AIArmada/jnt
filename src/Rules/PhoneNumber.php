<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates phone number format (10-15 digits).
 *
 * This rule ensures that phone numbers conform to the J&T Express API requirements.
 * Phone numbers must be numeric and between 10 and 15 digits in length.
 */
class PhoneNumber implements ValidationRule
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

        if (in_array(preg_match('/^\d{10,15}$/', $value), [0, false], true)) {
            $fail('The :attribute must be 10-15 digits');
        }
    }
}
