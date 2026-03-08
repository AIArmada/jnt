<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Webhooks;

use Illuminate\Http\Request;
use Spatie\WebhookClient\SignatureValidator\SignatureValidator;
use Spatie\WebhookClient\WebhookConfig;

final class JntSpatieSignatureValidator implements SignatureValidator
{
    public function isValid(Request $request, WebhookConfig $config): bool
    {
        if (! config('jnt.webhooks.verify_signature', true)) {
            return true;
        }

        $signature = (string) $request->header('digest', '');
        $bizContent = (string) $request->input('bizContent', '');
        $privateKey = (string) config('jnt.private_key', '');

        if ($signature === '' || $signature === '0') {
            return false;
        }

        if ($bizContent === '' || $bizContent === '0') {
            return false;
        }

        if ($privateKey === '') {
            return false;
        }

        $expected = base64_encode(md5($bizContent . $privateKey, true));

        return hash_equals($expected, $signature);
    }
}
