<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Services;

use AIArmada\Jnt\Data\WebhookData;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Service for handling J&T Express webhooks.
 *
 * Provides signature verification, webhook parsing, and response generation
 * for J&T Express webhook payloads. All webhook requests must be verified
 * before processing to ensure they originate from J&T Express.
 */
class WebhookService
{
    /**
     * Create a new webhook service instance.
     *
     * @param  string  $privateKey  The J&T private key for signature verification
     */
    public function __construct(
        protected string $privateKey
    ) {}

    /**
     * Verify the webhook signature from J&T Express.
     *
     * Uses J&T's signature algorithm: base64_encode(md5($bizContent . $privateKey, true))
     * with timing-safe comparison to prevent timing attacks.
     *
     * @param  string  $digest  The digest header value from J&T webhook request
     * @param  string  $bizContent  The raw bizContent string from request
     * @return bool True if signature is valid, false otherwise
     */
    public function verifySignature(string $digest, string $bizContent): bool
    {
        if ($digest === '' || $digest === '0') {
            return false;
        }

        if ($bizContent === '' || $bizContent === '0') {
            return false;
        }

        $expected = $this->generateSignature($bizContent);

        // Use timing-safe comparison to prevent timing attacks
        return hash_equals($expected, $digest);
    }

    /**
     * Generate a signature for the given bizContent.
     *
     * @param  string  $bizContent  The bizContent to sign
     * @return string The base64-encoded signature
     */
    public function generateSignature(string $bizContent): string
    {
        // J&T's signature algorithm: base64(md5(content + key))
        return base64_encode(md5($bizContent.$this->privateKey, true));
    }

    /**
     * Parse a webhook request into a WebhookData object.
     *
     * This method delegates to WebhookData for actual parsing and validation.
     * The request should have already been verified using verifySignature()
     * before calling this method.
     *
     * @param  Request  $request  The incoming webhook request
     * @return WebhookData The parsed and validated webhook data
     *
     * @throws \Illuminate\Validation\ValidationException If request validation fails
     */
    public function parseWebhook(Request $request): WebhookData
    {
        return WebhookData::fromRequest($request);
    }

    /**
     * Generate a success response for J&T Express.
     *
     * Returns the response format expected by J&T Express for successful
     * webhook processing: {"code":"1","msg":"success","data":"SUCCESS","requestId":"..."}
     *
     * @return array<string, string> The success response array
     */
    public function successResponse(): array
    {
        return [
            'code' => '1',
            'msg' => 'success',
            'data' => 'SUCCESS',
            'requestId' => (string) Str::uuid(),
        ];
    }

    /**
     * Generate a failure response for J&T Express.
     *
     * Returns the response format expected by J&T Express for failed
     * webhook processing: {"code":"0","msg":"...","data":null,"requestId":"..."}
     *
     * @param  string  $message  The error message to include
     * @return array<string, string|null> The failure response array
     */
    public function failureResponse(string $message = 'fail'): array
    {
        return [
            'code' => '0',
            'msg' => $message,
            'data' => null,
            'requestId' => (string) Str::uuid(),
        ];
    }

    /**
     * Extract the digest header from a request.
     *
     * J&T Express sends the signature in the 'digest' header.
     *
     * @param  Request  $request  The incoming request
     * @return string The digest value, or empty string if not present
     */
    public function extractDigest(Request $request): string
    {
        return (string) $request->header('digest', '');
    }

    /**
     * Verify and parse a webhook request in one step.
     *
     * This is a convenience method that combines signature verification
     * and webhook parsing. Returns null if signature verification fails.
     *
     * @param  Request  $request  The incoming webhook request
     * @return WebhookData|null The parsed webhook data, or null if signature invalid
     *
     * @throws \Illuminate\Validation\ValidationException If request validation fails
     * @throws InvalidArgumentException If bizContent is invalid JSON or missing required fields
     */
    public function verifyAndParse(Request $request): ?WebhookData
    {
        $digest = $this->extractDigest($request);
        $bizContent = (string) $request->input('bizContent', '');

        if (! $this->verifySignature($digest, $bizContent)) {
            return null;
        }

        return $this->parseWebhook($request);
    }
}
