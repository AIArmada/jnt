<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Cart;

use AIArmada\Cart\Cart;
use AIArmada\Jnt\Data\AddressData;
use Carbon\CarbonImmutable;

/**
 * Calculates J&T Express shipping rates for cart contents.
 *
 * This service bridges cart item data with J&T's shipping rate API,
 * calculating total weight and dimensions for accurate quotes.
 */
class JntShippingCalculator
{
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

        $originAddress = $this->getOriginAddress();

        if ($originAddress === null) {
            return null;
        }

        // For now, use a configurable flat rate or weight-based calculation
        // Full J&T API integration for dynamic rates would go here
        $rate = $this->calculateWeightBasedRate($totalWeight, $destination);

        return [
            'service_name' => config('jnt.shipping.default_service_name', 'J&T Express'),
            'service_type' => config('jnt.shipping.default_service_type', 'EZ'),
            'amount' => $rate,
            'weight_kg' => $totalWeight / 1000, // Convert grams to kg
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
     * Get the origin (sender) address from configuration.
     */
    private function getOriginAddress(): ?AddressData
    {
        $origin = config('jnt.shipping.origin');

        if (! is_array($origin) || empty($origin['name'])) {
            return null;
        }

        return new AddressData(
            name: $origin['name'],
            phone: $origin['phone'] ?? '',
            address: $origin['address'] ?? '',
            postCode: $origin['post_code'] ?? '',
            countryCode: $origin['country_code'] ?? 'MYS',
            state: $origin['state'] ?? null,
            city: $origin['city'] ?? null,
        );
    }

    /**
     * Calculate weight-based shipping rate.
     *
     * Uses configurable rates per kg with minimum charge.
     *
     * @param  int  $weightGrams  Total weight in grams
     */
    private function calculateWeightBasedRate(int $weightGrams, AddressData $destination): int
    {
        $weightKg = ceil($weightGrams / 1000);
        $baseRate = (int) config('jnt.shipping.base_rate', 800); // RM8.00 default in cents
        $perKgRate = (int) config('jnt.shipping.per_kg_rate', 200); // RM2.00 per kg in cents
        $minCharge = (int) config('jnt.shipping.min_charge', 800); // RM8.00 minimum

        // Calculate based on destination region if configured
        $regionMultiplier = $this->getRegionMultiplier($destination);

        $rate = (int) (($baseRate + ($perKgRate * max(0, $weightKg - 1))) * $regionMultiplier);

        return max($rate, $minCharge);
    }

    /**
     * Get region-based rate multiplier for destination.
     */
    private function getRegionMultiplier(AddressData $destination): float
    {
        $regionRates = config('jnt.shipping.region_multipliers', []);

        if (! is_array($regionRates) || empty($regionRates)) {
            return 1.0;
        }

        $state = mb_strtolower($destination->state ?? '');

        foreach ($regionRates as $region => $multiplier) {
            if (str_contains($state, mb_strtolower($region))) {
                return (float) $multiplier;
            }
        }

        return 1.0;
    }

    /**
     * Get estimated delivery days based on destination.
     */
    private function getEstimatedDays(AddressData $destination): int
    {
        $defaultDays = (int) config('jnt.shipping.default_estimated_days', 3);
        $eastExtraDays = (int) config('jnt.shipping.east_malaysia_extra_days', 2);

        // East Malaysia takes longer
        $eastMalaysiaStates = ['sabah', 'sarawak', 'labuan'];
        $state = mb_strtolower($destination->state ?? '');

        foreach ($eastMalaysiaStates as $eastState) {
            if (str_contains($state, $eastState)) {
                return $defaultDays + $eastExtraDays;
            }
        }

        return $defaultDays;
    }
}
