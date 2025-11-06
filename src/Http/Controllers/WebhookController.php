<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Http\Controllers;

use AIArmada\Jnt\Events\TrackingStatusReceived;
use AIArmada\Jnt\Exceptions\JntValidationException;
use AIArmada\Jnt\Services\WebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Controller for handling J&T Express webhook requests.
 *
 * This controller processes incoming webhook notifications from J&T Express
 * regarding tracking status updates. All requests must pass through the
 * VerifyWebhookSignature middleware before reaching this controller.
 */
class WebhookController
{
    /**
     * Create a new webhook controller instance.
     */
    public function __construct(
        protected WebhookService $webhookService
    ) {}

    /**
     * Handle incoming J&T Express webhook notification.
     *
     * This endpoint receives tracking status updates from J&T Express servers.
     * The request signature has already been verified by middleware at this point.
     *
     * @param  Request  $request  The incoming webhook request
     * @return JsonResponse The webhook response in J&T's expected format
     */
    public function handle(Request $request): JsonResponse
    {
        try {
            // Parse webhook payload
            $webhookData = $this->webhookService->parseWebhook($request);

            // Log webhook reception if enabled
            if (config('jnt.webhooks.log_payloads', false)) {
                Log::info('J&T webhook received', [
                    'billCode' => $webhookData->billCode,
                    'txlogisticId' => $webhookData->txlogisticId,
                    'detailsCount' => count($webhookData->details),
                    'latestStatus' => $webhookData->getLatestDetail()->scanType ?? 'unknown',
                ]);
            }

            // Dispatch event for application to handle
            TrackingStatusReceived::dispatch($webhookData);

            // Return success response to J&T
            $response = $this->webhookService->successResponse();

            return response()->json($response);
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Invalid request structure
            Log::warning('J&T webhook validation failed', [
                'errors' => $e->errors(),
            ]);

            $response = $this->webhookService->failureResponse('Invalid request structure');

            return response()->json($response, 422);
        } catch (JntValidationException $e) {
            // Invalid bizContent
            Log::warning('J&T webhook processing failed', [
                'error' => $e->getMessage(),
                'field' => $e->field ?? 'unknown',
            ]);

            $response = $this->webhookService->failureResponse('Invalid payload');

            return response()->json($response, 422);
        } catch (Throwable $e) {
            // Unexpected error
            Log::error('J&T webhook processing error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $response = $this->webhookService->failureResponse('Internal server error');

            return response()->json($response, 500);
        }
    }
}
