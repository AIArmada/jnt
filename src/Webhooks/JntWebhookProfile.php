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

        $decoded = json_decode($bizContent, true);

        if (! is_array($decoded)) {
            throw JntValidationException::invalidFormat('bizContent', 'valid JSON', $bizContent);
        }

        if (! isset($decoded['billCode'])) {
            throw JntValidationException::requiredFieldMissing('billCode');
        }

        if (! isset($decoded['details']) || ! is_array($decoded['details'])) {
            throw JntValidationException::invalidFieldValue('details', 'array', gettype($decoded['details'] ?? null));
        }

        return true;
    }
}
