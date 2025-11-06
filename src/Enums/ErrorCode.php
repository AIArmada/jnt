<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Enums;

/**
 * J&T Express API Error Codes
 *
 * Comprehensive error code mapping from official J&T Express API documentation.
 * Error codes are categorized into: Authentication, Validation, Data, and Business Logic.
 */
enum ErrorCode: int
{
    // Success
    case SUCCESS = 1;

    // Generic Failure
    case FAIL = 0;

    // Authentication Errors (145003xxx)
    case DIGEST_EMPTY = 145003052;
    case API_ACCOUNT_EMPTY = 145003051;
    case TIMESTAMP_EMPTY = 145003053;
    case API_ACCOUNT_NOT_EXISTS = 145003010;
    case API_ACCOUNT_NO_PERMISSION = 145003012;
    case SIGNATURE_VERIFICATION_FAILED = 145003030;

    // Validation Errors (145003050, 999001010)
    case ILLEGAL_PARAMETERS = 145003050;
    case CUSTOMER_CODE_REQUIRED = 999001010;
    case PASSWORD_REQUIRED = 999001011;
    case TX_LOGISTIC_ID_REQUIRED = 999001012;

    // Data Errors (999001030, 999002xxx)
    case DATA_NOT_FOUND = 999001030;
    case DATA_NOT_FOUND_CANCEL = 999002000;
    case ORDER_CANNOT_BE_CANCELLED = 999002010;

    /**
     * Create from API response code
     *
     * @param  int  $code  API response code
     * @return self|null Returns ErrorCode enum or null if code not recognized
     */
    public static function fromCode(int $code): ?self
    {
        return self::tryFrom($code);
    }

    /**
     * Get all authentication error codes
     *
     * @return array<self>
     */
    public static function authenticationErrors(): array
    {
        return [
            self::DIGEST_EMPTY,
            self::API_ACCOUNT_EMPTY,
            self::TIMESTAMP_EMPTY,
            self::API_ACCOUNT_NOT_EXISTS,
            self::API_ACCOUNT_NO_PERMISSION,
            self::SIGNATURE_VERIFICATION_FAILED,
        ];
    }

    /**
     * Get all validation error codes
     *
     * @return array<self>
     */
    public static function validationErrors(): array
    {
        return [
            self::ILLEGAL_PARAMETERS,
            self::CUSTOMER_CODE_REQUIRED,
            self::PASSWORD_REQUIRED,
            self::TX_LOGISTIC_ID_REQUIRED,
        ];
    }

    /**
     * Get all data error codes
     *
     * @return array<self>
     */
    public static function dataErrors(): array
    {
        return [
            self::DATA_NOT_FOUND,
            self::DATA_NOT_FOUND_CANCEL,
        ];
    }

    /**
     * Get all business logic error codes
     *
     * @return array<self>
     */
    public static function businessLogicErrors(): array
    {
        return [
            self::ORDER_CANNOT_BE_CANCELLED,
        ];
    }

    /**
     * Get human-readable error message
     */
    public function getMessage(): string
    {
        return match ($this) {
            self::SUCCESS => 'Success',
            self::FAIL => 'Operation failed',

            // Authentication
            self::DIGEST_EMPTY => 'Signature digest is empty',
            self::API_ACCOUNT_EMPTY => 'API account is empty',
            self::TIMESTAMP_EMPTY => 'Timestamp is empty',
            self::API_ACCOUNT_NOT_EXISTS => 'API account does not exist',
            self::API_ACCOUNT_NO_PERMISSION => 'API account has no interface permissions',
            self::SIGNATURE_VERIFICATION_FAILED => 'Headers signature verification failed',

            // Validation
            self::ILLEGAL_PARAMETERS => 'Illegal parameters',
            self::CUSTOMER_CODE_REQUIRED => 'Customer code is required',
            self::PASSWORD_REQUIRED => 'Password is required',
            self::TX_LOGISTIC_ID_REQUIRED => 'Transaction logistic ID is required',

            // Data
            self::DATA_NOT_FOUND => 'Data cannot be found',
            self::DATA_NOT_FOUND_CANCEL => 'Data cannot be found for cancellation',
            self::ORDER_CANNOT_BE_CANCELLED => 'Order status cannot be cancelled',
        };
    }

    /**
     * Get detailed description for troubleshooting
     */
    public function getDescription(): string
    {
        return match ($this) {
            self::SUCCESS => 'The request completed successfully',
            self::FAIL => 'The request failed to complete',

            // Authentication
            self::DIGEST_EMPTY => 'The digest (signature) header is missing. Please generate a signature using the Signature Tools.',
            self::API_ACCOUNT_EMPTY => 'The apiAccount header is missing. You can find your apiAccount in the Console.',
            self::TIMESTAMP_EMPTY => 'The timestamp header is missing. Timestamp must be in milliseconds (UTC+8).',
            self::API_ACCOUNT_NOT_EXISTS => 'The provided API account does not exist in the system. Please verify your credentials.',
            self::API_ACCOUNT_NO_PERMISSION => 'Your API account does not have permission for this interface. Please request access and ensure it is in the complete launching list.',
            self::SIGNATURE_VERIFICATION_FAILED => 'The signature verification failed. Ensure you are using the correct privateKey and signing the bizContent correctly.',

            // Validation
            self::ILLEGAL_PARAMETERS => 'One or more parameters are invalid. Please check the request format and field values.',
            self::CUSTOMER_CODE_REQUIRED => 'The customerCode field in Business Parameter is mandatory. Please provide your customer code.',
            self::PASSWORD_REQUIRED => 'The password field in Business Parameter is mandatory. Generate it using the Signature Tools.',
            self::TX_LOGISTIC_ID_REQUIRED => 'The txlogisticId (order ID) field in Business Parameter is mandatory.',

            // Data
            self::DATA_NOT_FOUND => 'No data found for the provided reference. Please verify the order ID or tracking number.',
            self::DATA_NOT_FOUND_CANCEL => 'No data found for cancellation. The order may not exist or has already been cancelled.',
            self::ORDER_CANNOT_BE_CANCELLED => 'The order cannot be cancelled due to its current status. Only pending orders can be cancelled.',
        };
    }

    /**
     * Check if error is retryable (transient error)
     */
    public function isRetryable(): bool
    {
        return match ($this) {
            self::TIMESTAMP_EMPTY,
            self::SIGNATURE_VERIFICATION_FAILED,
            self::ILLEGAL_PARAMETERS => true, // May be transient (clock skew, network issues)
            default => false,
        };
    }

    /**
     * Check if error is a client error (4xx equivalent)
     */
    public function isClientError(): bool
    {
        return match ($this) {
            self::SUCCESS, self::FAIL => false,
            self::DIGEST_EMPTY,
            self::API_ACCOUNT_EMPTY,
            self::TIMESTAMP_EMPTY,
            self::API_ACCOUNT_NOT_EXISTS,
            self::API_ACCOUNT_NO_PERMISSION,
            self::SIGNATURE_VERIFICATION_FAILED,
            self::ILLEGAL_PARAMETERS,
            self::CUSTOMER_CODE_REQUIRED,
            self::PASSWORD_REQUIRED,
            self::TX_LOGISTIC_ID_REQUIRED,
            self::DATA_NOT_FOUND,
            self::DATA_NOT_FOUND_CANCEL,
            self::ORDER_CANNOT_BE_CANCELLED => true,
        };
    }

    /**
     * Check if error is a server error (5xx equivalent)
     *
     * Currently, J&T API does not return explicit server errors.
     * All errors are either success (1) or client errors (0, 145003xxx, 999xxx).
     */
    public function isServerError(): bool
    {
        return false; // J&T API does not have explicit server error codes
    }

    /**
     * Get error category
     */
    public function getCategory(): string
    {
        return match ($this) {
            self::SUCCESS => 'Success',
            self::FAIL => 'Generic',
            self::DIGEST_EMPTY,
            self::API_ACCOUNT_EMPTY,
            self::TIMESTAMP_EMPTY,
            self::API_ACCOUNT_NOT_EXISTS,
            self::API_ACCOUNT_NO_PERMISSION,
            self::SIGNATURE_VERIFICATION_FAILED => 'Authentication',
            self::ILLEGAL_PARAMETERS,
            self::CUSTOMER_CODE_REQUIRED,
            self::PASSWORD_REQUIRED,
            self::TX_LOGISTIC_ID_REQUIRED => 'Validation',
            self::DATA_NOT_FOUND,
            self::DATA_NOT_FOUND_CANCEL => 'Data',
            self::ORDER_CANNOT_BE_CANCELLED => 'Business Logic',
        };
    }

    /**
     * Check if this is a success code
     */
    public function isSuccess(): bool
    {
        return $this === self::SUCCESS;
    }

    /**
     * Check if this is a failure code
     */
    public function isFailure(): bool
    {
        return $this !== self::SUCCESS;
    }
}
