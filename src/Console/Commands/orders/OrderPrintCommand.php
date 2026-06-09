<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Console\Commands\Orders;

use AIArmada\Jnt\Console\JntCommand;
use AIArmada\Jnt\Data\PrintWaybillData;
use AIArmada\Jnt\Services\JntExpressService;

class OrderPrintCommand extends JntCommand
{
    protected $signature = 'jnt:order:print
                          {order-id : Order ID to print}
                          {--tracking-number= : Optional tracking number (billCode)}
                          {--path=storage/waybills : Directory to save PDF}';

    protected $description = 'Print waybill for a J&T Express order';

    public function handle(JntExpressService $jnt): int
    {
        return $this->withErrorHandling(function () use ($jnt): int {
            $orderId = $this->argument('order-id');
            $trackingNumber = $this->option('tracking-number');
            $path = $this->option('path');

            $this->line('Printing waybill for order: ' . $orderId);

            $result = (is_string($trackingNumber) && $trackingNumber !== '')
                ? $jnt->printOrder((string) $orderId, $trackingNumber)
                : $jnt->printOrder((string) $orderId);

            $waybill = PrintWaybillData::fromApiArray($result);

            if ($waybill->hasBase64Content()) {
                $filename = $orderId . '.pdf';
                $fullPath = base_path(sprintf('%s/%s', $path, $filename));

                if ($waybill->savePdf($fullPath)) {
                    $this->success('Waybill saved successfully');
                    $this->line('Location: ' . $fullPath);
                    $this->line('Size: ' . $waybill->getFormattedSize());
                } else {
                    $this->failure('Failed to save waybill PDF');

                    return self::FAILURE;
                }
            } elseif ($waybill->hasUrlContent()) {
                $this->success('Waybill URL generated');
                $this->line('Download URL: ' . $waybill->getDownloadUrl());
            } else {
                $this->failure('No waybill content available');

                return self::FAILURE;
            }

            return self::SUCCESS;
        });
    }
}
