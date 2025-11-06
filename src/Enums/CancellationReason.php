<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Enums;

/**
 * J&T Express Order Cancellation Reasons
 *
 * Predefined cancellation reasons for order cancellation.
 * These provide standardized reasons that help with tracking and analytics.
 */
enum CancellationReason: string
{
    // Customer-Initiated Cancellations
    case CUSTOMER_REQUEST = 'customer_request';
    case CUSTOMER_CHANGED_MIND = 'customer_changed_mind';
    case CUSTOMER_ORDERED_BY_MISTAKE = 'customer_ordered_by_mistake';
    case CUSTOMER_FOUND_BETTER_PRICE = 'customer_found_better_price';

    // Merchant-Initiated Cancellations
    case OUT_OF_STOCK = 'out_of_stock';
    case INCORRECT_PRICING = 'incorrect_pricing';
    case UNABLE_TO_FULFILL = 'unable_to_fulfill';
    case DUPLICATE_ORDER = 'duplicate_order';

    // Address/Delivery Issues
    case INCORRECT_ADDRESS = 'incorrect_address';
    case ADDRESS_NOT_SERVICEABLE = 'address_not_serviceable';
    case DELIVERY_NOT_AVAILABLE = 'delivery_not_available';

    // Payment Issues
    case PAYMENT_FAILED = 'payment_failed';
    case PAYMENT_PENDING_TOO_LONG = 'payment_pending_too_long';

    // Other
    case SYSTEM_ERROR = 'system_error';
    case OTHER = 'other';

    /**
     * Get all customer-initiated cancellation reasons
     *
     * @return array<self>
     */
    public static function customerInitiated(): array
    {
        return [
            self::CUSTOMER_REQUEST,
            self::CUSTOMER_CHANGED_MIND,
            self::CUSTOMER_ORDERED_BY_MISTAKE,
            self::CUSTOMER_FOUND_BETTER_PRICE,
        ];
    }

    /**
     * Get all merchant-initiated cancellation reasons
     *
     * @return array<self>
     */
    public static function merchantInitiated(): array
    {
        return [
            self::OUT_OF_STOCK,
            self::INCORRECT_PRICING,
            self::UNABLE_TO_FULFILL,
            self::DUPLICATE_ORDER,
        ];
    }

    /**
     * Get all delivery issue cancellation reasons
     *
     * @return array<self>
     */
    public static function deliveryIssues(): array
    {
        return [
            self::INCORRECT_ADDRESS,
            self::ADDRESS_NOT_SERVICEABLE,
            self::DELIVERY_NOT_AVAILABLE,
        ];
    }

    /**
     * Get all payment issue cancellation reasons
     *
     * @return array<self>
     */
    public static function paymentIssues(): array
    {
        return [
            self::PAYMENT_FAILED,
            self::PAYMENT_PENDING_TOO_LONG,
        ];
    }

    /**
     * Create from string (case-insensitive)
     *
     * @param  string  $reason  Cancellation reason string
     * @return self|null Returns CancellationReason enum or null if not recognized
     */
    public static function fromString(string $reason): ?self
    {
        return self::tryFrom(mb_strtolower($reason));
    }

    /**
     * Get human-readable description
     */
    public function getDescription(): string
    {
        return match ($this) {
            self::CUSTOMER_REQUEST => 'Customer requested cancellation',
            self::CUSTOMER_CHANGED_MIND => 'Customer changed their mind',
            self::CUSTOMER_ORDERED_BY_MISTAKE => 'Customer ordered by mistake',
            self::CUSTOMER_FOUND_BETTER_PRICE => 'Customer found better price elsewhere',

            self::OUT_OF_STOCK => 'Product is out of stock',
            self::INCORRECT_PRICING => 'Pricing error on product',
            self::UNABLE_TO_FULFILL => 'Merchant unable to fulfill order',
            self::DUPLICATE_ORDER => 'Duplicate order detected',

            self::INCORRECT_ADDRESS => 'Incorrect or incomplete delivery address',
            self::ADDRESS_NOT_SERVICEABLE => 'Address is not serviceable by J&T Express',
            self::DELIVERY_NOT_AVAILABLE => 'Delivery service not available for this area',

            self::PAYMENT_FAILED => 'Payment processing failed',
            self::PAYMENT_PENDING_TOO_LONG => 'Payment pending for too long',

            self::SYSTEM_ERROR => 'System error occurred',
            self::OTHER => 'Other reason',
        };
    }

    /**
     * Check if cancellation requires customer contact
     *
     * Some cancellation reasons should trigger customer notification or contact.
     */
    public function requiresCustomerContact(): bool
    {
        return match ($this) {
            self::OUT_OF_STOCK,
            self::INCORRECT_PRICING,
            self::UNABLE_TO_FULFILL,
            self::DUPLICATE_ORDER,
            self::ADDRESS_NOT_SERVICEABLE,
            self::DELIVERY_NOT_AVAILABLE,
            self::PAYMENT_FAILED,
            self::SYSTEM_ERROR => true,
            default => false,
        };
    }

    /**
     * Check if cancellation is merchant's responsibility
     *
     * Determines if the merchant should take action to prevent future occurrences.
     */
    public function isMerchantResponsibility(): bool
    {
        return match ($this) {
            self::OUT_OF_STOCK,
            self::INCORRECT_PRICING,
            self::UNABLE_TO_FULFILL,
            self::DUPLICATE_ORDER,
            self::SYSTEM_ERROR => true,
            default => false,
        };
    }

    /**
     * Check if cancellation is customer's responsibility
     */
    public function isCustomerResponsibility(): bool
    {
        return match ($this) {
            self::CUSTOMER_REQUEST,
            self::CUSTOMER_CHANGED_MIND,
            self::CUSTOMER_ORDERED_BY_MISTAKE,
            self::CUSTOMER_FOUND_BETTER_PRICE,
            self::INCORRECT_ADDRESS => true,
            default => false,
        };
    }

    /**
     * Check if cancellation is due to delivery issues
     */
    public function isDeliveryIssue(): bool
    {
        return match ($this) {
            self::INCORRECT_ADDRESS,
            self::ADDRESS_NOT_SERVICEABLE,
            self::DELIVERY_NOT_AVAILABLE => true,
            default => false,
        };
    }

    /**
     * Check if cancellation is due to payment issues
     */
    public function isPaymentIssue(): bool
    {
        return match ($this) {
            self::PAYMENT_FAILED,
            self::PAYMENT_PENDING_TOO_LONG => true,
            default => false,
        };
    }

    /**
     * Get category of cancellation reason
     */
    public function getCategory(): string
    {
        return match ($this) {
            self::CUSTOMER_REQUEST,
            self::CUSTOMER_CHANGED_MIND,
            self::CUSTOMER_ORDERED_BY_MISTAKE,
            self::CUSTOMER_FOUND_BETTER_PRICE => 'Customer-Initiated',

            self::OUT_OF_STOCK,
            self::INCORRECT_PRICING,
            self::UNABLE_TO_FULFILL,
            self::DUPLICATE_ORDER => 'Merchant-Initiated',

            self::INCORRECT_ADDRESS,
            self::ADDRESS_NOT_SERVICEABLE,
            self::DELIVERY_NOT_AVAILABLE => 'Delivery Issue',

            self::PAYMENT_FAILED,
            self::PAYMENT_PENDING_TOO_LONG => 'Payment Issue',

            self::SYSTEM_ERROR => 'System Error',
            self::OTHER => 'Other',
        };
    }
}
