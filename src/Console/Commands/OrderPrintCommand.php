<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Console\Commands;

use AIArmada\Jnt\Data\PrintWaybillData;
use AIArmada\Jnt\Exceptions\JntApiException;
use AIArmada\Jnt\Services\JntExpressService;
use Illuminate\Console\Command;
use Throwable;

final class OrderPrintCommand extends Command
{
    protected $signature = 'jnt:order:print
                          {order-id : Order ID to print}
                          {--tracking-number= : Optional tracking number (billCode)}
                          {--path=storage/waybills : Directory to save PDF}';

    protected $description = 'Print waybill for a J&T Express order';

    public function handle(JntExpressService $jnt): int
    {
        $orderId = $this->argument('order-id');
        $trackingNumber = $this->option('tracking-number');
        $path = $this->option('path');

        try {
            $this->line('Printing waybill for order: ' . $orderId);

            $result = (is_string($trackingNumber) && $trackingNumber !== '')
                ? $jnt->printOrder((string) $orderId, $trackingNumber)
                : $jnt->printOrder((string) $orderId);

            $waybill = PrintWaybillData::fromApiArray($result);

            if ($waybill->hasBase64Content()) {
                $filename = $orderId . '.pdf';
                $fullPath = base_path(sprintf('%s/%s', $path, $filename));

                if ($waybill->savePdf($fullPath)) {
                    $this->info('✓ Waybill saved successfully!');
                    $this->line('Location: ' . $fullPath);
                    $this->line('Size: ' . $waybill->getFormattedSize());
                } else {
                    $this->error('Failed to save waybill PDF.');

                    return self::FAILURE;
                }
            } elseif ($waybill->hasUrlContent()) {
                $this->info('✓ Waybill URL generated!');
                $this->line('Download URL: ' . $waybill->getDownloadUrl());
            } else {
                $this->warn('No waybill content available.');

                return self::FAILURE;
            }

            return self::SUCCESS;
        } catch (JntApiException $e) {
            $this->error('API Error: ' . $e->getMessage());

            return self::FAILURE;
        } catch (Throwable $e) {
            $this->error('Error: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
