<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Console\Commands\Orders;

use AIArmada\Jnt\Console\JntCommand;
use Illuminate\Support\Facades\Http;
use Throwable;

class ConfigCheckCommand extends JntCommand
{
    protected $signature = 'jnt:config:check';

    protected $description = 'Validate J&T Express configuration and connectivity';

    public function handle(): int
    {
        return $this->withErrorHandling(function (): int {
            $this->section('J&T Express Configuration Check');

            $checks = [];

            $checks[] = $this->checkConfig('API Account', 'jnt.api_account');
            $checks[] = $this->checkPrivateKey();
            $checks[] = $this->checkEnvironment();
            $checks[] = $this->checkBaseUrls();

            $this->table(
                ['Configuration', 'Status', 'Details'],
                collect($checks)->map(fn (array $check): array => [
                    $check['name'],
                    $check['valid'] ? '✓' : '✗',
                    $check['message'],
                ])->toArray(),
            );

            $hasErrors = collect($checks)->contains('valid', false);

            if ($hasErrors) {
                $this->failure('Configuration validation', 'Please fix the errors above.');

                return self::FAILURE;
            }

            $this->line('Testing API connectivity...');
            $connectivityCheck = $this->testConnectivity();

            if (! $connectivityCheck['success']) {
                $this->failure('Connectivity test', $connectivityCheck['message']);

                return self::FAILURE;
            }

            $this->success('All checks passed', 'J&T Express is properly configured.');

            return self::SUCCESS;
        });
    }

    private function checkConfig(string $name, string $key): array
    {
        $value = config($key);

        if (empty($value)) {
            return [
                'name' => $name,
                'valid' => false,
                'message' => sprintf('Missing - Set %s in config or environment', $key),
            ];
        }

        return [
            'name' => $name,
            'valid' => true,
            'message' => 'Configured',
        ];
    }

    private function checkPrivateKey(): array
    {
        $privateKey = config('jnt.private_key');

        if (empty($privateKey)) {
            return [
                'name' => 'Private Key',
                'valid' => false,
                'message' => 'Missing - Required for signing requests',
            ];
        }

        $isRsaKey = str_contains((string) $privateKey, 'BEGIN RSA PRIVATE KEY')
            || str_contains((string) $privateKey, 'BEGIN PRIVATE KEY');
        $isHexString = ctype_xdigit((string) $privateKey) && mb_strlen((string) $privateKey) >= 16;

        if (! $isRsaKey && ! $isHexString) {
            return [
                'name' => 'Private Key',
                'valid' => false,
                'message' => 'Invalid format - Must be valid RSA private key or hex string',
            ];
        }

        return [
            'name' => 'Private Key',
            'valid' => true,
            'message' => $isRsaKey ? 'Valid RSA private key' : 'Valid hex string key',
        ];
    }

    private function checkEnvironment(): array
    {
        $environment = config('jnt.environment', 'production');

        if (! in_array($environment, ['production', 'testing', 'local', 'development'], true)) {
            return [
                'name' => 'Environment',
                'valid' => false,
                'message' => "Invalid - Must be 'production', 'testing', 'local', or 'development'",
            ];
        }

        return [
            'name' => 'Environment',
            'valid' => true,
            'message' => ucfirst((string) $environment),
        ];
    }

    private function checkBaseUrls(): array
    {
        $baseUrls = config('jnt.base_urls');

        if (empty($baseUrls)) {
            return [
                'name' => 'Base URLs',
                'valid' => false,
                'message' => 'Missing - Required for API calls',
            ];
        }

        if (! is_array($baseUrls) || ! isset($baseUrls['testing']) || ! isset($baseUrls['production'])) {
            return [
                'name' => 'Base URLs',
                'valid' => false,
                'message' => 'Invalid format - Must contain testing and production URLs',
            ];
        }

        $environment = config('jnt.environment');
        $currentUrl = $environment === 'production' ? $baseUrls['production'] : $baseUrls['testing'];

        if (! filter_var($currentUrl, FILTER_VALIDATE_URL)) {
            return [
                'name' => 'Base URLs',
                'valid' => false,
                'message' => "Invalid URL format for {$environment} environment",
            ];
        }

        return [
            'name' => 'Base URLs',
            'valid' => true,
            'message' => "Configured for {$environment}: {$currentUrl}",
        ];
    }

    private function testConnectivity(): array
    {
        try {
            $baseUrls = config('jnt.base_urls');
            $environment = config('jnt.environment');
            $baseUrl = $environment === 'production' ? $baseUrls['production'] : $baseUrls['testing'];

            $response = Http::timeout(5)->get($baseUrl);

            if (! $response->successful()) {
                return [
                    'success' => false,
                    'message' => sprintf('HTTP %d error from API endpoint', $response->status()),
                ];
            }

            return [
                'success' => true,
                'message' => 'API endpoint is reachable',
            ];
        } catch (Throwable $exception) {
            return [
                'success' => false,
                'message' => $exception->getMessage(),
            ];
        }
    }
}
