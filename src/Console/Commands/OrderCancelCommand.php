<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Console\Commands;

use AIArmada\Jnt\Enums\CancellationReason;
use AIArmada\Jnt\Exceptions\JntApiException;
use AIArmada\Jnt\Services\JntExpressService;
use Exception;
use Illuminate\Console\Command;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;

class OrderCancelCommand extends Command
{
    protected $signature = 'jnt:order:cancel {order-id : Order ID to cancel} {--reason= : Cancellation reason}';

    protected $description = 'Cancel a J&T Express order';

    public function handle(JntExpressService $jnt): int
    {
        $orderId = $this->argument('order-id');
        $reasonInput = $this->option('reason');

        // If no reason provided, ask for it
        if (! $reasonInput) {
            $reasons = collect(CancellationReason::cases())
                ->mapWithKeys(fn ($reason): array => [$reason->value => $reason->value])
                ->toArray();

            $reasonInput = select('Select cancellation reason', $reasons);
        }

        // Try to match to enum, otherwise use as string
        $reason = CancellationReason::tryFrom($reasonInput) ?? $reasonInput;

        if (! confirm(sprintf('Cancel order %s?', $orderId), default: true)) {
            info('Cancellation aborted.');

            return self::SUCCESS;
        }

        try {
            spin(
                fn () => $jnt->cancelOrder($orderId, $reason),
                'Cancelling order...'
            );

            info('✓ Order cancelled successfully!');

            return self::SUCCESS;
        } catch (JntApiException $e) {
            error('API Error: '.$e->getMessage());

            return self::FAILURE;
        } catch (Exception $e) {
            error('Error: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
