<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Console\Commands;

use AIArmada\Jnt\Services\JntExpressService;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class ConfigCheckCommand extends Command
{
    protected $signature = 'jnt:config:check';

    protected $description = 'Validate J&T Express configuration and connectivity';

    public function handle(JntExpressService $jnt): int
    {
        $this->line('J&T Express Configuration Check');

        $checks = [];

        // Check API Account
        $checks[] = $this->checkConfig('API Account', 'jnt.api_account');

        // Check Private Key
        $checks[] = $this->checkPrivateKey();

        // Check Environment
        $checks[] = $this->checkEnvironment();

        // Check Base URLs
        $checks[] = $this->checkBaseUrls();

        // Display results table
        $this->table(
            ['Configuration', 'Status', 'Details'],
            collect($checks)->map(fn ($check): array => [
                $check['name'],
                $check['valid'] ? '✓' : '✗',
                $check['message'],
            ])->toArray()
        );

        // Check for any errors
        $hasErrors = collect($checks)->contains('valid', false);

        if ($hasErrors) {
            $this->error('Configuration validation failed. Please fix the errors above.');

            return self::FAILURE;
        }

        // Test API connectivity
        $this->line('Testing API connectivity...');
        $connectivityCheck = $this->testConnectivity();

        if (! $connectivityCheck['success']) {
            $this->error('Connectivity test failed: '.$connectivityCheck['message']);

            return self::FAILURE;
        }

        $this->info('✓ All checks passed! J&T Express is properly configured.');

        return self::SUCCESS;
    }

    /**
     * @return array<string, mixed>
     */
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

    /**
     * @return array<string, mixed>
     */
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

        // Check if it's either RSA format or a hex string (for J&T testing)
        $isRsaKey = str_contains((string) $privateKey, 'BEGIN RSA PRIVATE KEY') ||
                   str_contains((string) $privateKey, 'BEGIN PRIVATE KEY');
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

    /**
     * @return array<string, mixed>
     */
    private function checkEnvironment(): array
    {
        $environment = config('jnt.environment', 'production');

        if (! in_array($environment, ['production', 'testing', 'local', 'development'])) {
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

    /**
     * @return array<string, mixed>
     */
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

    /**
     * @return array<string, mixed>
     */
    private function testConnectivity(): array
    {
        try {
            $baseUrls = config('jnt.base_urls');
            $environment = config('jnt.environment');
            $baseUrl = $environment === 'production' ? $baseUrls['production'] : $baseUrls['testing'];

            // Simple connectivity test - check if we can reach the API
            $response = Http::timeout(5)->get($baseUrl);

            // Check if response is successful (2xx status code)
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
        } catch (Exception $exception) {
            return [
                'success' => false,
                'message' => $exception->getMessage(),
            ];
        }
    }
}
