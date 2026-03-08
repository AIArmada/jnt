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
                ->post($this->baseUrl . $endpoint, [
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
                        ->post($this->baseUrl . $endpoint, [
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
                throw new JntApiException(
                    message: 'J&T API request failed: ' . ($data['msg'] ?? 'Unknown error'),
                    errorCode: (string) $data['code'],
                    apiResponse: $data,
                    endpoint: $endpoint,
                );
            }

            return $data;
        } catch (ConnectionException $connectionException) {
            throw JntNetworkException::connectionFailed($endpoint, $connectionException);
        }
    }

    protected function shouldRetry(mixed $exception): bool
    {
        // Retry on connection errors
        return $exception instanceof ConnectionException;
    }

    protected function generateDigest(string $bizContent): string
    {
        $toSign = $bizContent . $this->privateKey;
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

        $payload = [
            'status' => $response->status(),
            'successful' => $response->successful(),
        ];

        $json = $response->json();

        if (is_array($json)) {
            $payload['code'] = $json['code'] ?? null;
            $payload['msg'] = $json['msg'] ?? null;

            if (isset($json['data']) && is_array($json['data'])) {
                $payload['data_keys'] = array_keys($json['data']);
            }
        } else {
            $body = $response->body();
            $payload['body_length'] = mb_strlen($body);
            $payload['body_sha256'] = hash('sha256', $body);
        }

        Log::channel($channel)->log($level, 'J&T API Response', $payload);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function maskSensitiveData(array $data): array
    {
        return $this->maskSensitiveDataRecursive($data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function maskSensitiveDataRecursive(array $data): array
    {
        $masked = [];

        foreach ($data as $key => $value) {
            $keyString = is_string($key) ? mb_strtolower($key) : '';

            if (in_array($keyString, ['password', 'private_key', 'digest', 'signature'], true)) {
                $masked[$key] = '***MASKED***';

                continue;
            }

            if (in_array($keyString, ['customer_code', 'customercode', 'apiaccount', 'api_account'], true)) {
                $asString = (string) $value;
                $masked[$key] = $asString === '' ? '' : mb_substr($asString, 0, 3) . '***';

                continue;
            }

            if (in_array($keyString, ['phone', 'mobile', 'tel', 'telephone'], true)) {
                $asString = preg_replace('/\D+/', '', (string) $value) ?? '';
                $masked[$key] = $asString === '' ? '' : '***' . mb_substr($asString, -2);

                continue;
            }

            if (in_array($keyString, ['email'], true)) {
                $masked[$key] = '***MASKED***';

                continue;
            }

            if (in_array($keyString, ['address', 'addr', 'postcode', 'post_code', 'postal_code'], true)) {
                $masked[$key] = '***MASKED***';

                continue;
            }

            if (is_array($value)) {
                $masked[$key] = $this->maskSensitiveDataRecursive($value);

                continue;
            }

            $masked[$key] = $value;
        }

        return $masked;
    }
}
