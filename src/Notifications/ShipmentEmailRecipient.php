<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Notifications;

/**
 * Mail recipient resolved from an order metadata email address.
 *
 * A named class (rather than an anonymous one) so queued shipment
 * notifications can serialize their notifiable.
 */
final class ShipmentEmailRecipient
{
    public function __construct(public readonly string $email) {}

    public function getKey(): string
    {
        return $this->email;
    }

    /**
     * @return array{mail: string}
     */
    public function routeNotificationForMail(): array
    {
        return ['mail' => $this->email];
    }
}
