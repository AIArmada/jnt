<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Console\Commands\Webhooks;

use AIArmada\Jnt\Console\JntCommand;
use AIArmada\Jnt\Services\WebhookService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;

class WebhookTestCommand extends JntCommand
{
    protected $signature = 'jnt:webhook:test {--url= : Webhook URL to test}';

    protected $description = 'Test J&T Express webhook endpoint';

    public function handle(WebhookService $webhookService): int
    {
        return $this->withErrorHandling(function () use ($webhookService): int {
            $url = $this->option('url')
                ?: config('jnt.webhook.url', route('jnt.webhooks.status'));

            $this->info('Testing webhook endpoint: ' . $url);

            $samplePayload = [
                'bizContent' => json_encode([
                    'billCode' => 'TEST' . time(),
                    'txlogisticId' => 'TEST-ORDER-' . time(),
                    'details' => [
                        [
                            'scanTime' => CarbonImmutable::now()->toIso8601String(),
                            'scanType' => 'collect',
                            'desc' => 'Package collected - Test webhook',
                        ],
                    ],
                ]),
            ];

            $signature = $webhookService->generateSignature((string) $samplePayload['bizContent']);

            $this->line('Sending test webhook...');

            $response = Http::withHeaders(['digest' => $signature])
                ->post($url, $samplePayload);

            $this->info('Status: ' . $response->status());

            $json = $response->json();
            if (is_array($json)) {
                $code = $json['code'] ?? null;
                $msg = $json['msg'] ?? null;

                $this->line('Response summary: ' . json_encode([
                    'code' => $code,
                    'msg' => $msg,
                ]));
            } else {
                $body = $response->body();
                $this->line('Response summary: ' . json_encode([
                    'body_length' => mb_strlen($body),
                    'body_sha256' => hash('sha256', $body),
                ]));

                if ($this->output->isVerbose()) {
                    $this->line('Response (truncated): ' . mb_substr($body, 0, 500));
                }
            }

            if ($response->successful()) {
                $this->success('Webhook test successful');
            } else {
                $this->failure('Webhook test failed');

                return self::FAILURE;
            }

            return self::SUCCESS;
        });
    }
}
