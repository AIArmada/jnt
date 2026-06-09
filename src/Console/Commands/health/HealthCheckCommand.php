<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Console\Commands\Health;

use AIArmada\Jnt\Console\JntCommand;
use AIArmada\Jnt\Services\JntExpressService;
use Exception;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;
use TypeError;

class HealthCheckCommand extends JntCommand
{
    protected $signature = 'jnt:health';

    protected $description = 'Check J&T Express API connectivity and configuration (development/testing only)';

    public function handle(): int
    {
        return $this->withErrorHandling(function (): int {
            $this->info('J&T Express API Health Check');
            $this->line('WARNING: This command should only be run in development/testing environments!');
            $this->newLine();

            $allHealthy = true;

            $this->line('Checking J&T Express API...');
            $jntHealthy = $this->checkJntApi();
            if (! $jntHealthy) {
                $allHealthy = false;
            }
            $this->newLine();

            if ($this->output->isVerbose()) {
                $this->displayConfiguration();
                $this->newLine();
            }

            if ($allHealthy) {
                $this->success('All systems operational');

                return self::SUCCESS;
            }

            $this->failure('Some systems are experiencing issues');

            return self::FAILURE;
        });
    }

    protected function checkJntApi(): bool
    {
        $environment = config('jnt.environment', 'local');

        if ($environment === 'production') {
            $this->error('Health checks are disabled for production environment');
            $this->line('Health checks should only be run in development/testing environments');

            return false;
        }

        if (! $this->checkRequiredConfig()) {
            return false;
        }

        try {
            $service = app(JntExpressService::class);
        } catch (RuntimeException $e) {
            $this->error('Configuration error');

            if ($this->output->isVerbose()) {
                $this->line("Error: {$e->getMessage()}");
            }

            return false;
        } catch (TypeError $e) {
            if (str_contains($e->getMessage(), 'customerCode') || str_contains($e->getMessage(), 'password')) {
                $this->error('Service requires customer_code and password');
                if ($this->output->isVerbose()) {
                    $this->line('Set JNT_CUSTOMER_CODE and JNT_PASSWORD in your environment');
                }
            } else {
                $this->error('Service configuration error');
                if ($this->output->isVerbose()) {
                    $this->line("Error: {$e->getMessage()}");
                }
            }

            return false;
        }

        $this->success('Service configured');

        try {
            $this->testConnectivity();
            $this->success('API reachable');
        } catch (Throwable $e) {
            $this->warn('API connectivity issue');
            if ($this->output->isVerbose()) {
                $this->line("Error: {$e->getMessage()}");
            }
        }

        return true;
    }

    protected function checkRequiredConfig(): bool
    {
        $environment = config('jnt.environment', 'local');
        $hasErrors = false;

        $basicConfigs = [
            'jnt.api_account' => 'API Account',
            'jnt.private_key' => 'Private Key',
        ];

        foreach ($basicConfigs as $configKey => $configName) {
            $value = config($configKey);
            if (empty($value)) {
                $this->error("{$configName} not configured");
                $hasErrors = true;
            }
        }

        $additionalConfigs = [
            'jnt.customer_code' => 'Customer Code',
            'jnt.password' => 'Password',
        ];

        foreach ($additionalConfigs as $configKey => $configName) {
            $value = config($configKey);
            if (empty($value)) {
                if ($environment === 'production') {
                    $this->error("{$configName} not configured (required for production)");
                    $hasErrors = true;
                } else {
                    $this->warn("{$configName} not configured (may be required for some operations)");
                }
            }
        }

        $baseUrls = config('jnt.base_urls', []);

        if ($environment === 'production' && empty($baseUrls['production'])) {
            $this->error('Production Base URL not configured');
            $hasErrors = true;
        } elseif ($environment !== 'production' && empty($baseUrls['testing'])) {
            $this->error('Testing Base URL not configured');
            $hasErrors = true;
        }

        if ($hasErrors) {
            if ($this->output->isVerbose()) {
                $this->line('Please check your J&T Express configuration');
            }

            return false;
        }

        return true;
    }

    protected function testConnectivity(): void
    {
        $environment = config('jnt.environment', 'local');

        if ($environment === 'production') {
            throw new Exception('Connectivity tests are disabled for production environment for safety');
        }

        $baseUrls = config('jnt.base_urls', []);
        $baseUrl = $baseUrls['testing'] ?? null;

        if (empty($baseUrl)) {
            throw new Exception('Testing Base URL not configured');
        }

        $response = Http::timeout(5)->get($baseUrl);

        if (! $response->successful()) {
            throw new Exception(sprintf('HTTP %d error from API endpoint', $response->status()));
        }
    }

    protected function displayConfiguration(): void
    {
        $this->line('Configuration Status');

        $environment = config('jnt.environment', 'local');
        $this->line("   Environment: {$environment}");

        $apiAccount = config('jnt.api_account');
        $this->line('   API Account: ' . ($apiAccount ? 'Configured' : 'Missing'));

        $privateKey = config('jnt.private_key');
        $this->line('   Private Key: ' . ($privateKey ? 'Configured' : 'Missing'));

        $customerCode = config('jnt.customer_code');
        $this->line('   Customer Code: ' . ($customerCode ? 'Configured' : 'Missing'));

        $password = config('jnt.password');
        $this->line('   Password: ' . ($password ? 'Configured' : 'Missing'));

        $baseUrls = config('jnt.base_urls', []);
        $testingUrl = $baseUrls['testing'] ?? null;
        $productionUrl = $baseUrls['production'] ?? null;

        $this->line('   Testing URL: ' . ($testingUrl ?: 'Missing'));
        $this->line('   Production URL: ' . ($productionUrl ?: 'Missing'));

        $currentBaseUrl = $environment === 'production' ? $productionUrl : $testingUrl;
        $this->line('   Current Base URL: ' . ($currentBaseUrl ?: 'Missing for current environment'));

        $loggingEnabled = config('jnt.logging.enabled', true);
        $this->line('   Logging: ' . ($loggingEnabled ? 'Enabled' : 'Disabled'));

        $webhooksEnabled = config('jnt.webhooks.enabled', true);
        $this->line('   Webhooks: ' . ($webhooksEnabled ? 'Enabled' : 'Disabled'));
    }
}
