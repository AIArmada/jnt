<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Console\Commands\Webhooks;

use AIArmada\Jnt\Console\JntCommand;
use AIArmada\Jnt\Services\WebhookService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;

/**
 * CLI-only diagnostic: posts a signed sample payload to a webhook URL.
 *
 * Never expose this over HTTP; the target URL is operator input and the
 * signed payload must stay inside trusted environments.
 */
class WebhookTestCommand extends JntCommand
{
    protected $signature = 'jnt:webhook:test {--url= : Webhook URL to test}';

    protected $description = 'Test J&T Express webhook endpoint';

    public function handle(WebhookService $webhookService): int
    {
        return $this->withErrorHandling(function () use ($webhookService): int {
            $url = $this->option('url')
                ?: config('jnt.webhook.url', route('jnt.webhooks.status'));

            if (! self::isSafeTestUrl((string) $url)) {
                $this->failure('Refusing to post test webhook: URL must use http(s) without credentials and must not target cloud metadata or link-local addresses.');

                return self::FAILURE;
            }

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

    public static function isSafeTestUrl(string $url): bool
    {
        $parts = parse_url(mb_trim($url));

        if (! is_array($parts)) {
            return false;
        }

        $scheme = mb_strtolower((string) ($parts['scheme'] ?? ''));

        if (! in_array($scheme, ['http', 'https'], true)) {
            return false;
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            return false;
        }

        $host = mb_strtolower(mb_trim((string) ($parts['host'] ?? ''), '[]'));

        if ($host === '') {
            return false;
        }

        $blockedHosts = [
            'metadata.google.internal',
            'metadata.goog',
            'metadata.google.com',
            '169.254.169.254',
            'fd00:ec2::254',
            '100.100.100.200',
        ];

        if (in_array($host, $blockedHosts, true)) {
            return false;
        }

        if (str_starts_with($host, '169.254.')) {
            return false;
        }

        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false
            && str_starts_with(mb_strtolower($host), 'fe80:')) {
            return false;
        }

        return true;
    }
}
