<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates integer minor-unit money values.
 *
 * J&T accepts monetary values from MYR 0.01 through MYR 999,999.99. The package
 * represents that range as 1 through 99,999,999 integer sen.
 */
final class MonetaryValue implements ValidationRule
{
    /**
     * @param  int  $maximumMinor  Maximum allowed amount in MYR minor units
     */
    public function __construct(private readonly int $maximumMinor = 99_999_999) {}

    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_int($value)) {
            $fail('The :attribute must be an integer minor-unit amount.');

            return;
        }

        if ($value < 1 || $value > $this->maximumMinor) {
            $fail(sprintf(
                'The :attribute must be between 1 and %s minor units.',
                number_format($this->maximumMinor),
            ));
        }
    }
}
