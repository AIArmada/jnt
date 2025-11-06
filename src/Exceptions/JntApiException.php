<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Exceptions;

use Throwable;

/**
 * Exception thrown when J&T API operations fail.
 *
 * This exception includes additional context about the API call,
 * such as the endpoint, error code, and raw API response.
 */
class JntApiException extends JntException
{
    public function __construct(
        string $message,
        ?string $errorCode = null,
        public readonly mixed $apiResponse = null,
        public readonly ?string $endpoint = null,
        int $code = 0,
        ?Throwable $previous = null
    ) {
        // Pass errorCode and apiResponse (as data) to parent, add endpoint as child property
        parent::__construct($message, $errorCode, $apiResponse, $code, $previous);
    }

    /**
     * Create exception for failed order creation.
     */
    public static function orderCreationFailed(string $reason, mixed $response = null): self
    {
        return new self(
            message: 'Order creation failed: '.$reason,
            errorCode: 'ORDER_CREATE_ERROR',
            apiResponse: $response,
            endpoint: 'order/addOrder'
        );
    }

    /**
     * Create exception for failed order cancellation.
     */
    public static function orderCancellationFailed(string $orderId, string $reason, mixed $response = null): self
    {
        return new self(
            message: sprintf('Failed to cancel order %s: %s', $orderId, $reason),
            errorCode: 'ORDER_CANCEL_ERROR',
            apiResponse: $response,
            endpoint: 'order/cancelOrder'
        );
    }

    /**
     * Create exception for failed tracking query.
     */
    public static function trackingFailed(string $orderId, mixed $response = null): self
    {
        return new self(
            message: 'Failed to retrieve tracking information for order '.$orderId,
            errorCode: 'TRACKING_ERROR',
            apiResponse: $response,
            endpoint: 'logistics/trace'
        );
    }

    /**
     * Create exception for failed order query.
     */
    public static function orderQueryFailed(string $orderId, mixed $response = null): self
    {
        return new self(
            message: 'Failed to query order '.$orderId,
            errorCode: 'ORDER_QUERY_ERROR',
            apiResponse: $response,
            endpoint: 'order/getOrders'
        );
    }

    /**
     * Create exception for failed waybill printing.
     */
    public static function printFailed(string $orderId, string $reason, mixed $response = null): self
    {
        return new self(
            message: sprintf('Failed to print waybill for order %s: %s', $orderId, $reason),
            errorCode: 'PRINT_ERROR',
            apiResponse: $response,
            endpoint: 'order/printOrder'
        );
    }

    /**
     * Create exception for invalid API response format.
     */
    public static function invalidApiResponse(string $endpoint, mixed $response): self
    {
        return new self(
            message: 'Invalid API response format from '.$endpoint,
            errorCode: 'INVALID_RESPONSE',
            apiResponse: $response,
            endpoint: $endpoint
        );
    }

    /**
     * Create exception for API rate limiting.
     */
    public static function rateLimitExceeded(string $endpoint, mixed $response = null): self
    {
        return new self(
            message: 'Rate limit exceeded for '.$endpoint,
            errorCode: 'RATE_LIMIT_EXCEEDED',
            apiResponse: $response,
            endpoint: $endpoint
        );
    }

    /**
     * Create exception for API authentication failure.
     */
    public static function authenticationFailed(string $reason, mixed $response = null): self
    {
        return new self(
            message: 'API authentication failed: '.$reason,
            errorCode: 'AUTH_ERROR',
            apiResponse: $response
        );
    }
}
