<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Data;

/**
 * Data Transfer Object for waybill printing responses.
 *
 * Represents the result of a printOrder() operation, containing
 * either base64-encoded PDF content (single parcel) or a URL to
 * download the PDF (multi-parcel).
 */
final readonly class PrintWaybillData
{
    public function __construct(
        public string $orderId,
        public ?string $trackingNumber,
        public ?string $base64Content,
        public ?string $urlContent,
        public bool $isMultiParcel,
        public ?string $templateName = null,
    ) {}

    /**
     * Create instance from J&T API response array.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromApiArray(array $data): self
    {
        // J&T returns different data for single vs multi-parcel
        // Single parcel: base64EncodeContent
        // Multi-parcel: urlContent
        $hasBase64 = isset($data['base64EncodeContent']) && ! empty($data['base64EncodeContent']);
        $hasUrl = isset($data['urlContent']) && ! empty($data['urlContent']);

        return new self(
            orderId: $data['txlogisticId'] ?? $data['orderId'] ?? '',
            trackingNumber: $data['billCode'] ?? $data['trackingNumber'] ?? null,
            base64Content: $hasBase64 ? $data['base64EncodeContent'] : null,
            urlContent: $hasUrl ? $data['urlContent'] : null,
            isMultiParcel: $hasUrl && ! $hasBase64,
            templateName: $data['templateName'] ?? null,
        );
    }

    /**
     * Convert to array representation.
     *
     * @return array<string, string|bool|null>
     */
    public function toArray(): array
    {
        return [
            'orderId' => $this->orderId,
            'trackingNumber' => $this->trackingNumber,
            'base64Content' => $this->base64Content,
            'urlContent' => $this->urlContent,
            'isMultiParcel' => $this->isMultiParcel,
            'templateName' => $this->templateName,
        ];
    }

    /**
     * Check if base64 PDF content is available.
     */
    public function hasBase64Content(): bool
    {
        return $this->base64Content !== null && $this->base64Content !== '';
    }

    /**
     * Check if PDF URL is available.
     */
    public function hasUrlContent(): bool
    {
        return $this->urlContent !== null && $this->urlContent !== '';
    }

    /**
     * Save PDF to file system.
     *
     * @param  string  $path  Absolute path where PDF should be saved
     * @return bool True if save was successful, false otherwise
     */
    public function savePdf(string $path): bool
    {
        if (! $this->hasBase64Content()) {
            return false;
        }

        $pdfContent = base64_decode((string) $this->base64Content, true);

        if ($pdfContent === false) {
            return false;
        }

        // Ensure directory exists
        $directory = dirname($path);
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        return file_put_contents($path, $pdfContent) !== false;
    }

    /**
     * Get decoded PDF content.
     *
     * @return string|null Binary PDF content or null if not available
     */
    public function getPdfContent(): ?string
    {
        if (! $this->hasBase64Content()) {
            return null;
        }

        $decoded = base64_decode((string) $this->base64Content, true);

        return $decoded !== false ? $decoded : null;
    }

    /**
     * Get PDF content size in bytes.
     *
     * @return int|null Size in bytes or null if content not available
     */
    public function getPdfSize(): ?int
    {
        $content = $this->getPdfContent();

        return $content !== null ? mb_strlen($content) : null;
    }

    /**
     * Check if PDF content is valid.
     *
     * Performs basic validation by checking PDF magic number.
     *
     * @return bool True if content appears to be valid PDF
     */
    public function isValidPdf(): bool
    {
        $content = $this->getPdfContent();

        if ($content === null) {
            return false;
        }

        // Check for PDF magic number (%PDF-)
        return str_starts_with($content, '%PDF-');
    }

    /**
     * Get human-readable PDF size.
     *
     * @return string|null Formatted size (e.g., "1.5 MB") or null
     */
    public function getFormattedSize(): ?string
    {
        $size = $this->getPdfSize();

        if ($size === null) {
            return null;
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $power = $size > 0 ? floor(log($size, 1024)) : 0;
        $power = min($power, count($units) - 1);

        return number_format($size / (1024 ** $power), 2).' '.$units[$power];
    }

    /**
     * Get download URL for multi-parcel shipments.
     *
     * @return string|null URL to download PDF or null if not multi-parcel
     */
    public function getDownloadUrl(): ?string
    {
        return $this->isMultiParcel ? $this->urlContent : null;
    }
}
