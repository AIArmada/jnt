<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Console\Commands;

use AIArmada\Jnt\Exceptions\JntApiException;
use AIArmada\Jnt\Exceptions\JntNetworkException;
use AIArmada\Jnt\Services\JntExpressService;
use Exception;
use Illuminate\Console\Command;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\table;
use function Laravel\Prompts\warning;

class OrderTrackCommand extends Command
{
    protected $signature = 'jnt:order:track {order-id : Order ID or tracking number to track}';

    protected $description = 'Track a J&T Express order';

    public function handle(JntExpressService $jnt): int
    {
        $orderId = $this->argument('order-id');

        try {
            $tracking = spin(
                fn () => $jnt->trackParcel($orderId),
                'Tracking order: '.$orderId
            );

            if ($tracking->details === []) {
                warning('No tracking information found for this order.');

                return self::SUCCESS;
            }

            info('✓ Tracking Information Found');

            // Display tracking details
            $details = [];
            foreach ($tracking->details as $detail) {
                $details[] = [
                    $detail->scanTime ?? 'N/A',
                    $detail->scanType ?? 'N/A',
                    $detail->desc ?? 'No description',
                ];
            }

            table(
                ['Time', 'Status', 'Description'],
                $details
            );

            return self::SUCCESS;
        } catch (JntNetworkException $e) {
            error('Network Error: '.$e->getMessage());

            return self::FAILURE;
        } catch (JntApiException $e) {
            error('API Error: '.$e->getMessage());

            return self::FAILURE;
        } catch (Exception $e) {
            error('Error: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
