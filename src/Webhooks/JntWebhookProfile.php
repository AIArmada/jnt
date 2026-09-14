<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Webhooks;

use AIArmada\CommerceSupport\Webhooks\CommerceWebhookProfile;
use AIArmada\Jnt\Exceptions\JntValidationException;
use Illuminate\Http\Request;

/**
 * Profile for determining if J&T webhooks should be processed.
 */
class JntWebhookProfile extends CommerceWebhookProfile
{
    /**
     * Determine if the request should be processed.
     */
    public function shouldProcess(Request $request): bool
    {
        $bizContent = $request->input('bizContent');

        if (! is_string($bizContent) || $bizContent === '') {
            return false;
        }

        $maxBytes = max(1, (int) config('jnt.webhooks.max_biz_content_bytes', 1048576));

        if (mb_strlen($bizContent) > $maxBytes) {
            throw JntValidationException::fieldTooLong('bizContent', $maxBytes, mb_strlen($bizContent));
        }

        $decoded = json_decode($bizContent, true);

        if (! is_array($decoded)) {
            throw JntValidationException::invalidFormat('bizContent', 'valid JSON', $bizContent);
        }

        if (! isset($decoded['billCode'])) {
            throw JntValidationException::requiredFieldMissing('billCode');
        }

        if (! isset($decoded['details']) || ! is_array($decoded['details'])) {
            throw JntValidationException::invalidFieldValue('details', $decoded['details'] ?? null, 'array');
        }

        $maxDetails = max(1, (int) config('jnt.webhooks.max_details', 500));

        if (count($decoded['details']) > $maxDetails) {
            throw JntValidationException::fieldTooLong('details', $maxDetails, count($decoded['details']));
        }

        return true;
    }
}
