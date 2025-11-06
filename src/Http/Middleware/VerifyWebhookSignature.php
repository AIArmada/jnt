<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Http\Middleware;

use AIArmada\Jnt\Services\WebhookService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to verify J&T Express webhook signatures.
 *
 * This middleware ensures that incoming webhook requests are authentic
 * by verifying the digital signature provided by J&T Express in the
 * 'digest' header using the configured private key.
 */
class VerifyWebhookSignature
{
    /**
     * Create a new middleware instance.
     */
    public function __construct(
        protected WebhookService $webhookService
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  Request  $request  The incoming request
     * @param  Closure(Request): Response  $next  The next middleware
     * @return Response The response
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Extract digest and bizContent
        $digest = $this->webhookService->extractDigest($request);
        $bizContent = (string) $request->input('bizContent', '');

        // Verify signature
        if (! $this->webhookService->verifySignature($digest, $bizContent)) {
            Log::warning('J&T webhook signature verification failed', [
                'ip' => $request->ip(),
                'digest_present' => $digest !== '' && $digest !== '0',
                'bizContent_present' => $bizContent !== '' && $bizContent !== '0',
            ]);

            $response = $this->webhookService->failureResponse('Invalid signature');

            return response()->json($response, 401);
        }

        return $next($request);
    }
}
