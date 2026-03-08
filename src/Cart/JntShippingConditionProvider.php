<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Cart;

use AIArmada\Cart\Cart;
use AIArmada\Cart\Conditions\CartCondition;
use AIArmada\Cart\Conditions\Enums\ConditionApplication;
use AIArmada\Cart\Conditions\Enums\ConditionPhase;
use AIArmada\Cart\Conditions\Enums\ConditionScope;
use AIArmada\Cart\Contracts\ConditionProviderInterface;
use AIArmada\Jnt\Data\AddressData;
use Carbon\CarbonImmutable;

/**
 * Provides J&T Express shipping rates as cart conditions.
 *
 * This class bridges the JNT package with the Cart package,
 * automatically adding shipping conditions based on cart contents
 * and stored shipping address.
 */
readonly class JntShippingConditionProvider implements ConditionProviderInterface
{
    private const string CONDITION_TYPE = 'shipping';

    private const string SHIPPING_ADDRESS_KEY = 'jnt_shipping_address';

    private const string SHIPPING_QUOTE_KEY = 'jnt_shipping_quote';

    private const int PRIORITY = 75;

    public function __construct(
        private JntShippingCalculator $calculator
    ) {}

    /**
     * Get shipping conditions applicable to the cart.
     *
     * Reads shipping address from cart metadata and calculates
     * J&T shipping rates if address is available.
     *
     * @return array<CartCondition>
     */
    public function getConditionsFor(Cart $cart): array
    {
        /** @var array<string, mixed>|null $shippingAddress */
        $shippingAddress = $cart->getMetadata(self::SHIPPING_ADDRESS_KEY);

        if ($shippingAddress === null) {
            return [];
        }

        // Check if we have a cached quote that's still valid
        /** @var array<string, mixed>|null $cachedQuote */
        $cachedQuote = $cart->getMetadata(self::SHIPPING_QUOTE_KEY);

        if ($cachedQuote !== null && $this->isQuoteValid($cachedQuote, $cart)) {
            return [$this->createConditionFromQuote($cachedQuote)];
        }

        // Calculate fresh shipping quote
        $quote = $this->calculator->calculateShipping($cart, AddressData::fromApiArray($shippingAddress));

        if ($quote === null) {
            return [];
        }

        // Store quote in cart metadata for caching
        $cart->setMetadata(self::SHIPPING_QUOTE_KEY, $quote);

        return [$this->createConditionFromQuote($quote)];
    }

    /**
     * Validate that a shipping condition is still applicable.
     */
    public function validate(CartCondition $condition, Cart $cart): bool
    {
        if ($condition->getType() !== self::CONDITION_TYPE) {
            return true;
        }

        $shippingAddress = $cart->getMetadata(self::SHIPPING_ADDRESS_KEY);

        return $shippingAddress !== null;
    }

    /**
     * Get the condition type identifier.
     */
    public function getType(): string
    {
        return self::CONDITION_TYPE;
    }

    /**
     * Get the priority for condition application.
     * Shipping is applied early (priority 75), after base price calculations.
     */
    public function getPriority(): int
    {
        return self::PRIORITY;
    }

    /**
     * Create a CartCondition from a shipping quote.
     *
     * @param  array<string, mixed>  $quote
     */
    private function createConditionFromQuote(array $quote): CartCondition
    {
        $value = (string) ($quote['amount'] ?? 0);

        return new CartCondition(
            name: $quote['service_name'] ?? 'jnt_shipping',
            type: self::CONDITION_TYPE,
            target: $this->buildTargetDefinition(),
            value: $value,
            attributes: $this->buildAttributes($quote),
            order: self::PRIORITY
        );
    }

    /**
     * Build the target definition for shipping condition.
     *
     * @return array<string, mixed>
     */
    private function buildTargetDefinition(): array
    {
        return [
            'scope' => ConditionScope::CART->value,
            'phase' => ConditionPhase::SHIPPING->value,
            'application' => ConditionApplication::AGGREGATE->value,
        ];
    }

    /**
     * Build condition attributes from shipping quote.
     *
     * @param  array<string, mixed>  $quote
     * @return array<string, mixed>
     */
    private function buildAttributes(array $quote): array
    {
        return [
            'provider' => 'jnt',
            'service_type' => $quote['service_type'] ?? 'standard',
            'service_name' => $quote['service_name'] ?? 'J&T Express',
            'estimated_days' => $quote['estimated_days'] ?? null,
            'weight_kg' => $quote['weight_kg'] ?? null,
            'calculated_at' => $quote['calculated_at'] ?? CarbonImmutable::now()->toISOString(),
            'quote_id' => $quote['quote_id'] ?? null,
        ];
    }

    /**
     * Check if a cached quote is still valid for the current cart state.
     *
     * @param  array<string, mixed>  $quote
     */
    private function isQuoteValid(array $quote, Cart $cart): bool
    {
        // Check if quote has expired (default 30 min TTL)
        $calculatedAt = $quote['calculated_at'] ?? null;
        $ttlMinutes = config('jnt.cart.quote_ttl_minutes', 30);

        if ($calculatedAt !== null) {
            $expiresAt = CarbonImmutable::parse((string) $calculatedAt)->addMinutes($ttlMinutes);
            if (CarbonImmutable::now()->isAfter($expiresAt)) {
                return false;
            }
        }

        // Check if cart weight/items changed (invalidates shipping quote)
        $quotedWeight = $quote['cart_weight'] ?? null;
        $currentWeight = $this->calculator->getCartWeight($cart);

        if ($quotedWeight !== null && $quotedWeight !== $currentWeight) {
            return false;
        }

        return true;
    }
}
