<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Exceptions;

use Throwable;

/**
 * Exception thrown when validation fails for J&T operations.
 *
 * This exception includes detailed information about which fields
 * failed validation and why.
 */
class JntValidationException extends JntException
{
    /**
     * @param  array<string, mixed>  $errors
     */
    public function __construct(
        string $message,
        public readonly array $errors = [],
        public readonly ?string $field = null,
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, null, $errors, $code, $previous);
    }

    /**
     * Create exception for field validation failure.
     *
     * @param  array<string, mixed>  $errors
     */
    public static function fieldValidationFailed(string $field, string $reason, array $errors = []): self
    {
        return new self(
            message: sprintf("Validation failed for field '%s': %s", $field, $reason),
            errors: $errors,
            field: $field
        );
    }

    /**
     * Create exception for missing required field.
     */
    public static function requiredFieldMissing(string $field): self
    {
        return new self(
            message: sprintf("Required field '%s' is missing", $field),
            errors: [$field => ['The field is required']],
            field: $field
        );
    }

    /**
     * Create exception for invalid field value.
     */
    public static function invalidFieldValue(string $field, mixed $value, string $expected): self
    {
        $valueStr = is_scalar($value) ? (string) $value : gettype($value);

        return new self(
            message: sprintf("Invalid value for field '%s': expected %s, got %s", $field, $expected, $valueStr),
            errors: [$field => ['Expected '.$expected]],
            field: $field
        );
    }

    /**
     * Create exception for field length violation.
     */
    public static function fieldTooLong(string $field, int $maxLength, int $actualLength): self
    {
        return new self(
            message: sprintf("Field '%s' exceeds maximum length of %d characters (got %d)", $field, $maxLength, $actualLength),
            errors: [$field => [sprintf('Maximum length is %d characters', $maxLength)]],
            field: $field
        );
    }

    /**
     * Create exception for field length violation (too short).
     */
    public static function fieldTooShort(string $field, int $minLength, int $actualLength): self
    {
        return new self(
            message: sprintf("Field '%s' is below minimum length of %d characters (got %d)", $field, $minLength, $actualLength),
            errors: [$field => [sprintf('Minimum length is %d characters', $minLength)]],
            field: $field
        );
    }

    /**
     * Create exception for numeric range violation.
     */
    public static function valueOutOfRange(string $field, float $min, float $max, float $actual): self
    {
        return new self(
            message: sprintf("Field '%s' value %s is outside valid range (%s-%s)", $field, $actual, $min, $max),
            errors: [$field => [sprintf('Value must be between %s and %s', $min, $max)]],
            field: $field
        );
    }

    /**
     * Create exception for invalid format.
     */
    public static function invalidFormat(string $field, string $expectedFormat, mixed $value = null): self
    {
        $message = sprintf("Field '%s' has invalid format: expected %s", $field, $expectedFormat);
        if ($value !== null) {
            $valueStr = is_scalar($value) ? (string) $value : gettype($value);
            $message .= sprintf(", got '%s'", $valueStr);
        }

        return new self(
            message: $message,
            errors: [$field => ['Expected format: '.$expectedFormat]],
            field: $field
        );
    }

    /**
     * Create exception for multiple validation errors.
     *
     * @param  array<string, mixed>  $errors
     */
    public static function multiple(array $errors): self
    {
        $fieldCount = count($errors);
        $fields = implode(', ', array_keys($errors));

        return new self(
            message: sprintf('Validation failed for %d field(s): %s', $fieldCount, $fields),
            errors: $errors
        );
    }
}
