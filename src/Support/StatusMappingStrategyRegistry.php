<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Support;

use AIArmada\Jnt\Contracts\StatusMappingStrategyInterface;
use InvalidArgumentException;

/**
 * Registry for carrier status mapping strategies.
 *
 * Carriers register their own strategies, allowing the system
 * to resolve tracking statuses in a carrier-agnostic way.
 */
final class StatusMappingStrategyRegistry
{
    /**
     * @var array<string, StatusMappingStrategyInterface>
     */
    private array $strategies = [];

    public function register(StatusMappingStrategyInterface $strategy): void
    {
        $this->strategies[$strategy->getCarrierCode()] = $strategy;
    }

    public function get(string $carrierCode): StatusMappingStrategyInterface
    {
        if (! isset($this->strategies[$carrierCode])) {
            throw new InvalidArgumentException(sprintf(
                'No status mapping strategy registered for carrier [%s].',
                $carrierCode,
            ));
        }

        return $this->strategies[$carrierCode];
    }

    public function has(string $carrierCode): bool
    {
        return isset($this->strategies[$carrierCode]);
    }

    /**
     * @return array<string, StatusMappingStrategyInterface>
     */
    public function all(): array
    {
        return $this->strategies;
    }
}
