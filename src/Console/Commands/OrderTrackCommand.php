<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Console\Commands;

use AIArmada\Jnt\Exceptions\JntApiException;
use AIArmada\Jnt\Exceptions\JntNetworkException;
use AIArmada\Jnt\Services\JntExpressService;
use Illuminate\Console\Command;
use Throwable;

final class OrderTrackCommand extends Command
{
    protected $signature = 'jnt:order:track
                          {order-id : Order ID to track}
                          {--tracking-number : Treat the argument as a tracking number (billCode)}';

    protected $description = 'Track a J&T Express order';

    public function handle(JntExpressService $jnt): int
    {
        $orderId = $this->argument('order-id');
        $byTrackingNumber = (bool) $this->option('tracking-number');

        try {
            $this->line('Tracking order: ' . $orderId);

            $tracking = $byTrackingNumber
                ? $jnt->trackParcel(null, (string) $orderId)
                : $jnt->trackParcel((string) $orderId);

            if ($tracking->details->count() === 0) {
                $this->warn('No tracking information found for this order.');

                return self::SUCCESS;
            }

            $this->info('✓ Tracking Information Found');

            // Display tracking details
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
        } catch (JntNetworkException $e) {
            $this->error('Network Error: ' . $e->getMessage());

            return self::FAILURE;
        } catch (JntApiException $e) {
            $this->error('API Error: ' . $e->getMessage());

            return self::FAILURE;
        } catch (Throwable $e) {
            $this->error('Error: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
