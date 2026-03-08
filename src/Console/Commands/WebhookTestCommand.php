<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Console\Commands;

use AIArmada\Jnt\Services\WebhookService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Throwable;

final class WebhookTestCommand extends Command
{
    protected $signature = 'jnt:webhook:test {--url= : Webhook URL to test}';

    protected $description = 'Test J&T Express webhook endpoint';

    public function handle(WebhookService $webhookService): int
    {
        $url = $this->option('url')
            ?: config('jnt.webhook.url', route('jnt.webhooks.status'));

        $this->info('Testing webhook endpoint: ' . $url);

        // Generate sample webhook payload
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

        // Generate signature
        $signature = $webhookService->generateSignature((string) $samplePayload['bizContent']);

        try {
            $this->line('Sending test webhook...');

            $response = Http::withHeaders(['digest' => $signature])
                ->post($url, $samplePayload);

            $this->line('Status: ' . $response->status());

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
                $this->info('✓ Webhook test successful!');
            } else {
                $this->error('✗ Webhook test failed!');

                return self::FAILURE;
            }

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error('Error: ' . $exception->getMessage());

            return self::FAILURE;
        }
    }
}
