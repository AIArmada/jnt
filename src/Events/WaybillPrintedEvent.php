<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Events;

use AIArmada\Jnt\Data\PrintWaybillData;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WaybillPrintedEvent
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(public readonly PrintWaybillData $waybill) {}

    public function getOrderId(): string
    {
        return $this->waybill->orderId;
    }

    public function getTrackingNumber(): ?string
    {
        return $this->waybill->trackingNumber;
    }

    public function hasTrackingNumber(): bool
    {
        return $this->waybill->trackingNumber !== null;
    }

    public function getTemplateName(): ?string
    {
        return $this->waybill->templateName;
    }

    public function hasBase64Content(): bool
    {
        return $this->waybill->hasBase64Content();
    }

    public function hasUrlContent(): bool
    {
        return $this->waybill->hasUrlContent();
    }

    public function getPdfContent(): ?string
    {
        return $this->waybill->getPdfContent();
    }

    public function getDownloadUrl(): ?string
    {
        return $this->waybill->getDownloadUrl();
    }

    public function getFileSize(): ?string
    {
        return $this->waybill->getFormattedSize();
    }

    public function savePdf(string $path): bool
    {
        return $this->waybill->savePdf($path);
    }
}
