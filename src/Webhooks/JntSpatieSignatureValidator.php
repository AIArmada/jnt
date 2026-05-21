<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Webhooks;

use AIArmada\CommerceSupport\Webhooks\CommerceSignatureValidator;
use Illuminate\Http\Request;

final class JntSpatieSignatureValidator extends CommerceSignatureValidator
{
    protected function getSignatureHeader(): string
    {
        return 'digest';
    }

    protected function validateSignature(Request $request, string $signature, string $secret): bool
    {
        if (! config('jnt.webhooks.verify_signature', true)) {
            return true;
        }

        $runtimeSecret = (string) config('jnt.private_key', '');

        if ($runtimeSecret !== '') {
            $secret = $runtimeSecret;
        }

        if ($secret === '') {
            return false;
        }

        $bizContent = $this->getPayloadForSigning($request);

        if ($bizContent === '' || $bizContent === '0') {
            return false;
        }

        return parent::validateSignature($request, $signature, $secret);
    }

    protected function getPayloadForSigning(Request $request): string
    {
        return (string) $request->input('bizContent', '');
    }

    protected function computeSignature(string $payload, string $secret): string
    {
        return base64_encode(md5($payload . $secret, true));
    }

    protected function getHashAlgorithm(): string
    {
        // Not used because computeSignature is overridden for J&T MD5 + Base64 signing.
        return 'md5';
    }
}
