<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Console\Commands\Tracking;

use AIArmada\Jnt\Console\JntCommand;
use AIArmada\Jnt\Services\JntExpressService;

class OrderTrackCommand extends JntCommand
{
    protected $signature = 'jnt:order:track
                          {order-id : Order ID to track}
                          {--tracking-number : Treat the argument as a tracking number (billCode)}';

    protected $description = 'Track a J&T Express order';

    public function handle(JntExpressService $jnt): int
    {
        return $this->withErrorHandling(function () use ($jnt): int {
            $orderId = $this->argument('order-id');
            $byTrackingNumber = (bool) $this->option('tracking-number');

            $this->line('Tracking order: ' . $orderId);

            $tracking = $byTrackingNumber
                ? $jnt->trackParcel(null, (string) $orderId)
                : $jnt->trackParcel((string) $orderId);

            if ($tracking->details->count() === 0) {
                $this->warn('No tracking information found for this order.');

                return self::SUCCESS;
            }

            $this->success('Tracking Information Found');

            $details = [];
            foreach ($tracking->details->toCollection() as $detail) {
                $details[] = [
                    $detail->scanTime,
                    $detail->scanType,
                    $detail->description,
                ];
            }

            $this->table(['Time', 'Status', 'Description'], $details);

            return self::SUCCESS;
        });
    }
}
