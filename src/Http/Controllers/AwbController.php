<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

final class AwbController
{
    public function show(Request $request, string $orderId): SymfonyResponse
    {
        if (! $request->hasValidSignature()) {
            abort(403, 'Invalid or expired signature.');
        }

        $cacheKey = "jnt_awb:{$orderId}";

        /** @var array{content: string|false, format: string}|null $data */
        $data = Cache::get($cacheKey);

        if ($data === null || ! isset($data['content']) || $data['content'] === false) {
            abort(404, 'AWB not found or expired.');
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
