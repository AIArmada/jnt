<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event fired when tracking is updated.
 */
final class TrackingUpdated
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly string $billcode,
        public readonly string $eventType,
        public readonly array $payload = []
    ) {}
}
