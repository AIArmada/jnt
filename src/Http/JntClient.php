<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Http;

use AIArmada\Jnt\Exceptions\JntApiException;
use AIArmada\Jnt\Exceptions\JntNetworkException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class JntClient
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        protected readonly string $baseUrl,
        protected readonly string $apiAccount,
        protected readonly string $privateKey,
        protected readonly array $config = [],
    ) {}

    /**
     * @param  array<string, mixed>  $bizContent
     * @return array<string, mixed>
     */
    public function post(string $endpoint, array $bizContent): array
    {
        $jsonBizContent = json_encode($bizContent, JSON_UNESCAPED_UNICODE);

        if ($jsonBizContent === false) {
            throw JntApiException::invalidApiResponse($endpoint, 'Failed to encode bizContent to JSON');
        }

        $digest = $this->generateDigest($jsonBizContent);
        $timestamp = (int) (microtime(true) * 1000);

        $this->logRequest($endpoint, $bizContent);

        $retryTimes = $this->config['http']['retry_times'] ?? 3;
        $retrySleep = $this->config['http']['retry_sleep'] ?? 1000;

        try {
            $response = Http::timeout($this->config['http']['timeout'] ?? 30)
                ->connectTimeout($this->config['http']['connect_timeout'] ?? 10)
                ->retry($retryTimes, $retrySleep, fn ($exception, $request): bool =>
                    // Retry on connection exceptions
                    // Don't retry for other exceptions
                    $exception instanceof ConnectionException, throw: false)
                ->withHeaders([
                    'apiAccount' => $this->apiAccount,
                    'digest' => $digest,
                    'timestamp' => (string) $timestamp,
                ])
                ->asForm()
                ->post($this->baseUrl.$endpoint, [
                    'bizContent' => $jsonBizContent,
                ]);

            $this->logResponse($response);

            // If we got a 5xx error, retry manually
            if ($response->status() >= 500 && $response->status() < 600) {
                for ($attempt = 2; $attempt <= $retryTimes; $attempt++) {
                    usleep($retrySleep * 1000);

                    $response = Http::timeout($this->config['http']['timeout'] ?? 30)
                        ->connectTimeout($this->config['http']['connect_timeout'] ?? 10)
                        ->withHeaders([
                            'apiAccount' => $this->apiAccount,
                            'digest' => $digest,
                            'timestamp' => (string) $timestamp,
                        ])
                        ->asForm()
                        ->post($this->baseUrl.$endpoint, [
                            'bizContent' => $jsonBizContent,
                        ]);

                    $this->logResponse($response);

                    if ($response->status() < 500) {
                        break;
                    }
                }
            }

            if ($response->failed()) {
                $statusCode = $response->status();

                if ($statusCode >= 500) {
                    throw JntNetworkException::serverError($endpoint, $statusCode, $response->body());
                }

                if ($statusCode >= 400) {
                    throw JntNetworkException::clientError($endpoint, $statusCode, $response->body());
                }

                throw JntApiException::invalidApiResponse($endpoint, sprintf('HTTP %d: %s', $statusCode, $response->body()));
            }

            $data = $response->json();

            if ($data === null) {
                throw JntApiException::invalidApiResponse($endpoint, 'Failed to decode API response: invalid JSON');
            }

            // Check for API-level errors
            if (isset($data['code']) && (string) $data['code'] !== '1') {
                throw JntApiException::orderCreationFailed(
                    $data['msg'] ?? 'API request failed',
                    $data
                );
            }

            return $data;
        } catch (ConnectionException $connectionException) {
            throw JntNetworkException::connectionFailed($endpoint, $connectionException);
        }
    }

    public function verifyWebhookSignature(string $bizContent, string $digest): bool
    {
        $expectedDigest = $this->generateDigest($bizContent);

        return hash_equals($expectedDigest, $digest);
    }

    protected function shouldRetry(mixed $exception): bool
    {
        // Retry on connection errors
        return $exception instanceof ConnectionException;
    }

    protected function generateDigest(string $bizContent): string
    {
        $toSign = $bizContent.$this->privateKey;
        $md5Raw = md5($toSign, true);

        return base64_encode($md5Raw);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function logRequest(string $endpoint, array $data): void
    {
        if (! ($this->config['logging']['enabled'] ?? true)) {
            return;
        }

        $channel = $this->config['logging']['channel'] ?? 'stack';
        $level = $this->config['logging']['level'] ?? 'info';

        Log::channel($channel)->log($level, 'J&T API Request', [
            'endpoint' => $endpoint,
            'data' => $this->maskSensitiveData($data),
        ]);
    }

    protected function logResponse(Response $response): void
    {
        if (! ($this->config['logging']['enabled'] ?? true)) {
            return;
        }

        $channel = $this->config['logging']['channel'] ?? 'stack';
        $level = $this->config['logging']['level'] ?? 'info';

        Log::channel($channel)->log($level, 'J&T API Response', [
            'status' => $response->status(),
            'successful' => $response->successful(),
            'body' => mb_substr($response->body(), 0, 500),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function maskSensitiveData(array $data): array
    {
        $masked = $data;

        if (isset($masked['password'])) {
            $masked['password'] = '***MASKED***';
        }

        if (isset($masked['customerCode'])) {
            $masked['customerCode'] = mb_substr((string) $masked['customerCode'], 0, 3).'***';
        }

        return $masked;
    }
}
