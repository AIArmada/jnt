<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Console\Commands;

use AIArmada\Jnt\Data\PrintWaybillData;
use AIArmada\Jnt\Exceptions\JntApiException;
use AIArmada\Jnt\Services\JntExpressService;
use Exception;
use Illuminate\Console\Command;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\warning;

class OrderPrintCommand extends Command
{
    protected $signature = 'jnt:order:print {order-id : Order ID to print} {--path=storage/waybills : Directory to save PDF}';

    protected $description = 'Print waybill for a J&T Express order';

    public function handle(JntExpressService $jnt): int
    {
        $orderId = $this->argument('order-id');
        $path = $this->option('path');

        try {
            $result = spin(
                fn () => $jnt->printOrder($orderId),
                'Printing waybill for order: '.$orderId
            );

            $waybill = PrintWaybillData::fromApiArray($result);

            if ($waybill->hasBase64Content()) {
                $filename = $orderId.'.pdf';
                $fullPath = base_path(sprintf('%s/%s', $path, $filename));

                if ($waybill->savePdf($fullPath)) {
                    info('✓ Waybill saved successfully!');
                    $this->line('Location: '.$fullPath);
                    $this->line('Size: '.$waybill->getFormattedSize());
                } else {
                    error('Failed to save waybill PDF.');

                    return self::FAILURE;
                }
            } elseif ($waybill->hasUrlContent()) {
                info('✓ Waybill URL generated!');
                $this->line('Download URL: '.$waybill->getDownloadUrl());
            } else {
                warning('No waybill content available.');

                return self::FAILURE;
            }

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
