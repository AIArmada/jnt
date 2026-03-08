<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Cart;

use AIArmada\Cart\Cart;
use AIArmada\Cart\Contracts\CartManagerInterface;
use AIArmada\Cart\Storage\StorageInterface;
use AIArmada\Jnt\Data\AddressData;
use Illuminate\Database\Eloquent\Model;

/**
 * CartManager decorator that adds J&T shipping functionality.
 *
 * Uses composition pattern to wrap any CartManagerInterface implementation,
 * enabling stacking with other decorators (e.g., CartManagerWithVouchers).
 */
final class CartManagerWithJntShipping implements CartManagerInterface
{
    private const string SHIPPING_ADDRESS_KEY = 'jnt_shipping_address';

    private const string SHIPPING_QUOTE_KEY = 'jnt_shipping_quote';

    private ?JntShippingCalculator $calculator = null;

    public function __construct(
        private CartManagerInterface $manager
    ) {}

    /**
     * @param  array<string, mixed>  $arguments
     */
    public function __call(string $method, array $arguments): mixed
    {
        return $this->manager->{$method}(...$arguments);
    }

    /**
     * Create from existing CartManagerInterface.
     */
    public static function fromCartManager(CartManagerInterface $manager): self
    {
        if ($manager instanceof self) {
            return $manager;
        }

        return new self($manager);
    }

    /**
     * Get the underlying CartManager (unwraps all decorators if needed).
     */
    public function getBaseManager(): CartManagerInterface
    {
        if ($this->manager instanceof self) {
            return $this->manager->getBaseManager();
        }

        return $this->manager;
    }

    public function getCurrentCart(): Cart
    {
        return $this->manager->getCurrentCart();
    }

    public function getCartInstance(string $name, ?string $identifier = null): Cart
    {
        return $this->manager->getCartInstance($name, $identifier);
    }

    public function instance(): string
    {
        return $this->manager->instance();
    }

    public function setInstance(string $name): static
    {
        $this->manager->setInstance($name);

        return $this;
    }

    public function setIdentifier(string $identifier): static
    {
        $this->manager->setIdentifier($identifier);

        return $this;
    }

    public function forgetIdentifier(): static
    {
        $this->manager->forgetIdentifier();

        return $this;
    }

    public function forOwner(Model $owner): static
    {
        return new self($this->manager->forOwner($owner));
    }

    public function getOwnerType(): ?string
    {
        return $this->manager->getOwnerType();
    }

    public function getOwnerId(): string | int | null
    {
        return $this->manager->getOwnerId();
    }

    public function session(?string $sessionKey = null): StorageInterface
    {
        return $this->manager->session($sessionKey);
    }

    public function getById(string $uuid): ?Cart
    {
        return $this->manager->getById($uuid);
    }

    public function swap(string $oldIdentifier, string $newIdentifier, string $instance = 'default'): bool
    {
        return $this->manager->swap($oldIdentifier, $newIdentifier, $instance);
    }

    /**
     * Set the shipping calculator instance.
     */
    public function setCalculator(JntShippingCalculator $calculator): self
    {
        $this->calculator = $calculator;

        return $this;
    }

    /**
     * Get the shipping calculator instance.
     */
    public function getCalculator(): JntShippingCalculator
    {
        if ($this->calculator === null) {
            $this->calculator = app(JntShippingCalculator::class);
        }

        return $this->calculator;
    }

    /**
     * Set shipping destination address for the current cart.
     *
     * @param  AddressData|array<string, mixed>  $address  Shipping address
     */
    public function setShippingAddress(AddressData | array $address): self
    {
        $addressArray = $address instanceof AddressData
            ? $address->toApiArray()
            : $address;

        $cart = $this->getCurrentCart();
        $cart->setMetadata(self::SHIPPING_ADDRESS_KEY, $addressArray);

        // Invalidate cached quote when address changes
        $cart->removeMetadata(self::SHIPPING_QUOTE_KEY);

        return $this;
    }

    /**
     * Get the current shipping address from cart metadata.
     */
    public function getShippingAddress(): ?AddressData
    {
        /** @var array<string, mixed>|null $address */
        $address = $this->getCurrentCart()->getMetadata(self::SHIPPING_ADDRESS_KEY);

        if ($address === null) {
            return null;
        }

        return AddressData::fromApiArray($address);
    }

    /**
     * Remove shipping address from cart.
     */
    public function clearShippingAddress(): self
    {
        $cart = $this->getCurrentCart();
        $cart->removeMetadata(self::SHIPPING_ADDRESS_KEY);
        $cart->removeMetadata(self::SHIPPING_QUOTE_KEY);

        return $this;
    }

    /**
     * Calculate shipping for current cart to the stored address.
     *
     * @return array<string, mixed>|null Shipping quote or null if no address
     */
    public function calculateShipping(): ?array
    {
        $address = $this->getShippingAddress();

        if ($address === null) {
            return null;
        }

        return $this->getCalculator()->calculateShipping($this->getCurrentCart(), $address);
    }

    /**
     * Calculate shipping to a specific address without storing it.
     *
     * @return array<string, mixed>|null Shipping quote or null if calculation fails
     */
    public function estimateShipping(AddressData $address): ?array
    {
        return $this->getCalculator()->calculateShipping($this->getCurrentCart(), $address);
    }

    /**
     * Get the cached shipping quote if available.
     *
     * @return array<string, mixed>|null Cached quote or null
     */
    public function getCachedShippingQuote(): ?array
    {
        /** @var array<string, mixed>|null $quote */
        $quote = $this->getCurrentCart()->getMetadata(self::SHIPPING_QUOTE_KEY);

        return $quote;
    }

    /**
     * Get estimated delivery days for the current shipping address.
     */
    public function getEstimatedDeliveryDays(): ?int
    {
        $quote = $this->getCachedShippingQuote();

        return $quote['estimated_days'] ?? null;
    }

    /**
     * Check if a shipping address is set for the current cart.
     */
    public function hasShippingAddress(): bool
    {
        return $this->getCurrentCart()->hasMetadata(self::SHIPPING_ADDRESS_KEY);
    }
}
