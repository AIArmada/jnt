<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event fired when parcel is in transit.
 */
final class ParcelInTransit
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly Model $shipment,
        public readonly array $payload = []
    ) {}
}
