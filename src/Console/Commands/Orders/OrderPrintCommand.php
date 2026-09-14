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
                $directory = self::safeDirectory((string) $path);

                if ($directory === null) {
                    $this->failure('Invalid --path: must be a relative directory under the application base path without "..".');

                    return self::FAILURE;
                }

                $filename = self::safeFilename((string) $orderId);
                $fullPath = $directory . DIRECTORY_SEPARATOR . $filename;

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

    public static function safeFilename(string $orderId): string
    {
        $base = preg_replace('/[^A-Za-z0-9._-]+/', '_', basename($orderId)) ?? '';

        $base = mb_trim($base, '._');

        if ($base === '') {
            $base = 'waybill';
        }

        return mb_substr($base, 0, 100) . '.pdf';
    }

    public static function safeDirectory(string $path): ?string
    {
        $normalized = str_replace('\\', '/', mb_trim($path));

        if ($normalized === ''
            || str_starts_with($normalized, '/')
            || preg_match('#^[A-Za-z]:#', $normalized) === 1
            || in_array($normalized, ['.', '..'], true)
        ) {
            return null;
        }

        foreach (explode('/', $normalized) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                return null;
            }
        }

        return base_path($normalized);
    }
}
