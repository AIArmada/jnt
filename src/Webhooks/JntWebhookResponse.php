<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Webhooks;

use AIArmada\Jnt\Services\WebhookService;
use Illuminate\Http\Request;
use Spatie\WebhookClient\WebhookConfig;
use Spatie\WebhookClient\WebhookResponse\RespondsToWebhook;
use Symfony\Component\HttpFoundation\Response;

final class JntWebhookResponse implements RespondsToWebhook
{
    public function __construct(
        private readonly WebhookService $webhookService
    ) {}

    public function respondToValidWebhook(Request $request, WebhookConfig $config): Response
    {
        return response()->json($this->webhookService->successResponse());
    }
}
