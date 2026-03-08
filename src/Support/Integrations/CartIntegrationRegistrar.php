<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Support\Integrations;

use AIArmada\Jnt\Cart\CartManagerWithJntShipping;
use AIArmada\Jnt\Cart\JntShippingCalculator;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Facade;

/**
 * Registers JNT shipping integration with the Cart package.
 *
 * This registrar extends the CartManager with JNT shipping functionality
 * using the decorator pattern, allowing shipping address management
 * and rate calculation through the cart.
 */
final class CartIntegrationRegistrar
{
    public function __construct(private readonly Application $app) {}

    /**
     * Register the cart integration.
     */
    public function register(): void
    {
        if (! class_exists('AIArmada\\Cart\\CartManager')) {
            return;
        }

        if (! interface_exists('AIArmada\\Cart\\Contracts\\CartManagerInterface')) {
            return;
        }

        $this->app->extend('cart', function ($manager, Application $app) {
            $cartManagerInterface = 'AIArmada\\Cart\\Contracts\\CartManagerInterface';

            if (! ($manager instanceof $cartManagerInterface)) {
                return $manager;
            }

            if ($manager instanceof CartManagerWithJntShipping) {
                return $manager;
            }

            $proxy = CartManagerWithJntShipping::fromCartManager($manager);

            // Inject the calculator
            $calculator = new JntShippingCalculator;
            $proxy->setCalculator($calculator);

            $app->instance('AIArmada\\Cart\\CartManager', $proxy);
            $app->instance($cartManagerInterface, $proxy);

            Facade::clearResolvedInstance('cart');

            return $proxy;
        });
    }
}
