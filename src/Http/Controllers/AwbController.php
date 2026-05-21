<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Http\Controllers;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerSignedDownload;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

final class AwbController
{
    public function show(Request $request, string $orderId): SymfonyResponse
    {
        if (! $request->hasValidSignature()) {
            abort(403, 'Invalid or expired signature.');
        }

        /** @var array<string, mixed>|null $data */
        $data = OwnerSignedDownload::payloadFromRequestToken($request, 'jnt_awb');

        if ($data === null || ! isset($data['content']) || $data['content'] === false) {
            abort(404, 'AWB not found or expired.');
        }

        if (! OwnerSignedDownload::isAuthorizedPayload(
            payload: $data,
            resourceIdKey: 'order_id',
            expectedResourceId: $orderId,
            owner: OwnerContext::resolve(),
            userId: $request->user()?->getAuthIdentifier(),
        )) {
            abort(403, 'Unauthorized AWB access.');
        }

        $mimeType = match ($data['format'] ?? 'pdf') {
            'pdf' => 'application/pdf',
            'png' => 'image/png',
            'zpl' => 'application/octet-stream',
            default => 'application/pdf',
        };

        $filename = "jnt_awb_{$orderId}." . ($data['format'] ?? 'pdf');

        return new Response($data['content'], 200, [
            'Content-Type' => $mimeType,
            'Content-Disposition' => "inline; filename=\"{$filename}\"",
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }
}
