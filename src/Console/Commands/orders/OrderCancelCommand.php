<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Console\Commands\Orders;

use AIArmada\Jnt\Console\JntCommand;
use AIArmada\Jnt\Enums\CancellationReason;
use AIArmada\Jnt\Services\JntExpressService;

class OrderCancelCommand extends JntCommand
{
    protected $signature = 'jnt:order:cancel
                          {order-id : Order ID to cancel}
                          {--reason= : Cancellation reason}
                          {--tracking-number= : Optional tracking number (billCode)}';

    protected $description = 'Cancel a J&T Express order';

    public function handle(JntExpressService $jnt): int
    {
        return $this->withErrorHandling(function () use ($jnt): int {
            $orderId = $this->argument('order-id');
            $reasonInput = $this->option('reason');
            $trackingNumber = $this->option('tracking-number');

            if (! $reasonInput) {
                $reasons = collect(CancellationReason::cases())
                    ->map(fn (CancellationReason $reason): string => $reason->value)
                    ->all();

                $reasonInput = $this->choice('Select cancellation reason', $reasons);
            }

            $reason = CancellationReason::tryFrom($reasonInput) ?? $reasonInput;

            if (! $this->confirm(sprintf('Cancel order %s?', $orderId), true)) {
                $this->info('Cancellation aborted.');

                return self::SUCCESS;
            }

            if (is_string($trackingNumber) && $trackingNumber !== '') {
                $jnt->cancelOrder((string) $orderId, $reason, $trackingNumber);
            } else {
                $jnt->cancelOrder((string) $orderId, $reason);
            }

            $this->success('Order cancelled successfully');

            return self::SUCCESS;
        });
    }
}
