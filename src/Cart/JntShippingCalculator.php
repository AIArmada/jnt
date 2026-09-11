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
 * Calculates J&T Express shipping rates and exposes them as cart conditions.
 *
 * This is the single cart integration path for J&T. Monetary values remain
 * integer minor units from configuration through the generated quote.
 */
final class JntShippingCalculator implements ConditionProviderInterface
{
    private const string CONDITION_TYPE = 'shipping';

    private const string SHIPPING_ADDRESS_KEY = 'jnt_shipping_address';

    private const string SHIPPING_QUOTE_KEY = 'jnt_shipping_quote';

    private const int PRIORITY = 75;

    /**
     * Calculate shipping cost for the cart to a destination address.
     *
     * @return array<string, mixed>|null Shipping quote or null if unavailable
     */
    public function calculateShipping(Cart $cart, AddressData $destination): ?array
    {
        $totalWeight = $this->getCartWeight($cart);

        if ($totalWeight <= 0) {
            return null;
        }

        if ($this->getOriginAddress() === null) {
            return null;
        }

        return [
            'service_name' => (string) config('jnt.shipping.default_service_name', 'J&T Express'),
            'service_type' => (string) config('jnt.shipping.default_service_type', 'EZ'),
            'amount' => $this->calculateWeightBasedRate($totalWeight, $destination),
            'weight_kg' => $totalWeight / 1000,
            'estimated_days' => $this->getEstimatedDays($destination),
            'calculated_at' => CarbonImmutable::now()->toISOString(),
            'cart_weight' => $totalWeight,
            'quote_id' => uniqid('jnt_quote_', true),
        ];
    }

    /**
     * Get total weight of all cart items in grams.
     */
    public function getCartWeight(Cart $cart): int
    {
        $totalWeight = 0;

        foreach ($cart->getItems() as $item) {
            $weight = $item->getAttribute('weight') ?? 0;
            $quantity = $item->quantity;
            $totalWeight += (int) ($weight * $quantity);
        }

        return $totalWeight;
    }

    /**
     * Get shipping conditions applicable to the cart.
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

        /** @var array<string, mixed>|null $cachedQuote */
        $cachedQuote = $cart->getMetadata(self::SHIPPING_QUOTE_KEY);

        if ($cachedQuote !== null && $this->isQuoteValid($cachedQuote, $cart)) {
            return [$this->createConditionFromQuote($cachedQuote)];
        }

        $quote = $this->calculateShipping($cart, AddressData::fromApiArray($shippingAddress));

        if ($quote === null) {
            return [];
        }

        $cart->setMetadata(self::SHIPPING_QUOTE_KEY, $quote);

        return [$this->createConditionFromQuote($quote)];
    }

    public function validate(CartCondition $condition, Cart $cart): bool
    {
        if ($condition->getType() !== self::CONDITION_TYPE) {
            return true;
        }

        return $cart->getMetadata(self::SHIPPING_ADDRESS_KEY) !== null;
    }

    public function getType(): string
    {
        return self::CONDITION_TYPE;
    }

    public function getPriority(): int
    {
        return self::PRIORITY;
    }

    /**
     * Get the origin (sender) address from configuration.
     */
    private function getOriginAddress(): ?AddressData
    {
        $origin = config('jnt.shipping.origin');

        if (! is_array($origin) || empty($origin['name'])) {
            return null;
        }

        return new AddressData(
            name: (string) $origin['name'],
            phone: (string) ($origin['phone'] ?? ''),
            address: (string) ($origin['address'] ?? ''),
            postCode: (string) ($origin['post_code'] ?? ''),
            countryCode: (string) ($origin['country_code'] ?? 'MYS'),
            state: isset($origin['state']) ? (string) $origin['state'] : null,
            city: isset($origin['city']) ? (string) $origin['city'] : null,
        );
    }

    /**
     * Calculate a weight-based shipping rate in integer minor units.
     */
    private function calculateWeightBasedRate(int $weightGrams, AddressData $destination): int
    {
        $weightKg = (int) ceil($weightGrams / 1000);
        $baseRate = (int) config('jnt.shipping.base_rate', 800);
        $perKgRate = (int) config('jnt.shipping.per_kg_rate', 200);
        $minCharge = (int) config('jnt.shipping.min_charge', 800);
        $regionMultiplierBasisPoints = $this->getRegionMultiplierBasisPoints($destination);

        $rate = $baseRate + ($perKgRate * max(0, $weightKg - 1));
        $rate = $this->applyRegionMultiplier($rate, $regionMultiplierBasisPoints);

        return max($rate, $minCharge);
    }

    /**
     * Resolve a regional multiplier represented as basis points (10000 = 1x).
     */
    private function getRegionMultiplierBasisPoints(AddressData $destination): int
    {
        $regionRates = config('jnt.shipping.region_multipliers_bp', []);

        if (! is_array($regionRates) || $regionRates === []) {
            return 10000;
        }

        $state = mb_strtolower($destination->state ?? '');

        foreach ($regionRates as $region => $basisPoints) {
            if (str_contains($state, mb_strtolower((string) $region))) {
                return (int) $basisPoints;
            }
        }

        return 10000;
    }

    private function applyRegionMultiplier(int $rate, int $basisPoints): int
    {
        return intdiv(($rate * $basisPoints) + 5000, 10000);
    }

    /**
     * Get estimated delivery days based on destination.
     */
    private function getEstimatedDays(AddressData $destination): int
    {
        $defaultDays = (int) config('jnt.shipping.default_estimated_days', 3);
        $eastExtraDays = (int) config('jnt.shipping.east_malaysia_extra_days', 2);
        $eastMalaysiaStates = ['sabah', 'sarawak', 'labuan'];
        $state = mb_strtolower($destination->state ?? '');

        foreach ($eastMalaysiaStates as $eastState) {
            if (str_contains($state, $eastState)) {
                return $defaultDays + $eastExtraDays;
            }
        }

        return $defaultDays;
    }

    /**
     * @param  array<string, mixed>  $quote
     */
    private function createConditionFromQuote(array $quote): CartCondition
    {
        return new CartCondition(
            name: (string) ($quote['service_name'] ?? 'jnt_shipping'),
            type: self::CONDITION_TYPE,
            target: $this->buildTargetDefinition(),
            value: (string) ($quote['amount'] ?? 0),
            attributes: $this->buildAttributes($quote),
            order: self::PRIORITY,
        );
    }

    /**
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
     * @param  array<string, mixed>  $quote
     */
    private function isQuoteValid(array $quote, Cart $cart): bool
    {
        $calculatedAt = $quote['calculated_at'] ?? null;
        $ttlMinutes = (int) config('jnt.cart.quote_ttl_minutes', 30);

        if ($calculatedAt !== null) {
            $expiresAt = CarbonImmutable::parse((string) $calculatedAt)->addMinutes($ttlMinutes);

            if (CarbonImmutable::now()->isAfter($expiresAt)) {
                return false;
            }
        }

        $quotedWeight = $quote['cart_weight'] ?? null;
        $currentWeight = $this->getCartWeight($cart);

        return $quotedWeight === null || $quotedWeight === $currentWeight;
    }
}
