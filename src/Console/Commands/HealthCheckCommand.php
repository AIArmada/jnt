<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Console\Commands;

use AIArmada\Jnt\Services\JntExpressService;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;
use TypeError;

final class HealthCheckCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'jnt:health';

    /**
     * The console command description.
     */
    protected $description = 'Check J&T Express API connectivity and configuration (development/testing only)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🔍 J&T Express API Health Check');
        $this->line('<fg=yellow>⚠️  WARNING: This command should only be run in development/testing environments!</>');
        $this->newLine();

        $allHealthy = true;

        // Check J&T Express API
        $this->line('📦 <fg=cyan>Checking J&T Express API...</>');
        $jntHealthy = $this->checkJntApi();
        if (! $jntHealthy) {
            $allHealthy = false;
        }
        $this->newLine();

        // Display configuration status
        if ($this->option('verbose')) {
            $this->displayConfiguration();
            $this->newLine();
        }

        // Final summary
        if ($allHealthy) {
            $this->info('✅ All systems operational');

            return self::SUCCESS;
        }

        $this->error('❌ Some systems are experiencing issues');

        return self::FAILURE;
    }

    /**
     * Check J&T Express API health
     */
    protected function checkJntApi(): bool
    {
        $environment = config('jnt.environment', 'local');

        // NEVER test against production!
        if ($environment === 'production') {
            $this->error('   ❌ Health checks are disabled for production environment');
            $this->line('      Health checks should only be run in development/testing environments');

            return false;
        }

        // Check if required configuration is present
        if (! $this->checkRequiredConfig()) {
            return false;
        }

        try {
            $service = app(JntExpressService::class);
        } catch (RuntimeException $e) {
            $this->error('   ❌ Configuration error');

            if ($this->option('verbose')) {
                $this->line("      Error: {$e->getMessage()}");
            }

            return false;
        } catch (TypeError $e) {
            // Handle specific case where customer_code or password are missing
            if (str_contains($e->getMessage(), 'customerCode') || str_contains($e->getMessage(), 'password')) {
                $this->error('   ❌ Service requires customer_code and password');
                if ($this->option('verbose')) {
                    $this->line('      Set JNT_CUSTOMER_CODE and JNT_PASSWORD in your environment');
                }
            } else {
                $this->error('   ❌ Service configuration error');
                if ($this->option('verbose')) {
                    $this->line("      Error: {$e->getMessage()}");
                }
            }

            return false;
        }

        // Service can be instantiated, which means configuration is valid
        $this->info('   ✅ Service configured');

        // Try to make an API call for connectivity check
        try {
            $this->testConnectivity();
            $this->info('   ✅ API reachable');
        } catch (Throwable $e) {
            // API call failed - log as warning but don't fail the check
            $this->warn('   ⚠️  API connectivity issue');
            if ($this->option('verbose')) {
                $this->line("      Error: {$e->getMessage()}");
            }
            // Don't return false - configuration is valid, connectivity issues are warnings
        }

        return true;
    }

    /**
     * Check if required configuration is present
     */
    protected function checkRequiredConfig(): bool
    {
        $environment = config('jnt.environment', 'local');
        $hasErrors = false;

        // Basic required configs
        $basicConfigs = [
            'jnt.api_account' => 'API Account',
            'jnt.private_key' => 'Private Key',
        ];

        foreach ($basicConfigs as $configKey => $configName) {
            $value = config($configKey);
            if (empty($value)) {
                $this->error("   ❌ {$configName} not configured");
                $hasErrors = true;
            }
        }

        // Additional configs that may be required for production
        $additionalConfigs = [
            'jnt.customer_code' => 'Customer Code',
            'jnt.password' => 'Password',
        ];

        foreach ($additionalConfigs as $configKey => $configName) {
            $value = config($configKey);
            if (empty($value)) {
                if ($environment === 'production') {
                    $this->error("   ❌ {$configName} not configured (required for production)");
                    $hasErrors = true;
                } else {
                    $this->warn("   ⚠️  {$configName} not configured (may be required for some operations)");
                }
            }
        }

        // Check environment and base URL
        $baseUrls = config('jnt.base_urls', []);

        if ($environment === 'production' && empty($baseUrls['production'])) {
            $this->error('   ❌ Production Base URL not configured');
            $hasErrors = true;
        } elseif ($environment !== 'production' && empty($baseUrls['testing'])) {
            $this->error('   ❌ Testing Base URL not configured');
            $hasErrors = true;
        }

        if ($hasErrors) {
            if ($this->option('verbose')) {
                $this->line('      Please check your J&T Express configuration');
            }

            return false;
        }

        return true;
    }

    /**
     * Test API connectivity - ONLY for non-production environments
     */
    protected function testConnectivity(): void
    {
        $environment = config('jnt.environment', 'local');

        // NEVER test connectivity against production!
        if ($environment === 'production') {
            throw new Exception('Connectivity tests are disabled for production environment for safety');
        }

        $baseUrls = config('jnt.base_urls', []);
        $baseUrl = $baseUrls['testing'] ?? null;

        if (empty($baseUrl)) {
            throw new Exception('Testing Base URL not configured');
        }

        // Simple connectivity test - check if we can reach the API endpoint
        $response = Http::timeout(5)->get($baseUrl);

        if (! $response->successful()) {
            throw new Exception(sprintf('HTTP %d error from API endpoint', $response->status()));
        }
    }

    /**
     * Display current configuration
     */
    protected function displayConfiguration(): void
    {
        $this->line('⚙️  <fg=cyan>Configuration Status</>');

        // Environment
        $environment = config('jnt.environment', 'local');
        $this->line("   Environment: <fg=yellow>{$environment}</>");

        // API Account
        $apiAccount = config('jnt.api_account');
        $accountStatus = $apiAccount ? '<fg=green>Configured</>' : '<fg=red>Missing</>';
        $this->line("   API Account: {$accountStatus}");

        // Private Key
        $privateKey = config('jnt.private_key');
        $privateKeyStatus = $privateKey ? '<fg=green>Configured</>' : '<fg=red>Missing</>';
        $this->line("   Private Key: {$privateKeyStatus}");

        // Customer Code
        $customerCode = config('jnt.customer_code');
        $customerCodeStatus = $customerCode ? '<fg=green>Configured</>' : '<fg=red>Missing</>';
        $this->line("   Customer Code: {$customerCodeStatus}");

        // Password
        $password = config('jnt.password');
        $passwordStatus = $password ? '<fg=green>Configured</>' : '<fg=red>Missing</>';
        $this->line("   Password: {$passwordStatus}");

        // Base URLs
        $baseUrls = config('jnt.base_urls', []);
        $testingUrl = $baseUrls['testing'] ?? null;
        $productionUrl = $baseUrls['production'] ?? null;

        if ($testingUrl) {
            $this->line("   Testing URL: <fg=green>{$testingUrl}</>");
        } else {
            $this->line('   Testing URL: <fg=red>Missing</>');
        }

        if ($productionUrl) {
            $this->line("   Production URL: <fg=green>{$productionUrl}</>");
        } else {
            $this->line('   Production URL: <fg=red>Missing</>');
        }

        // Current Base URL based on environment
        $currentBaseUrl = $environment === 'production' ? $productionUrl : $testingUrl;
        if ($currentBaseUrl) {
            $this->line("   Current Base URL: <fg=green>{$currentBaseUrl}</>");
        } else {
            $this->line('   Current Base URL: <fg=red>Missing for current environment</>');
        }

        // Logging Configuration
        $loggingEnabled = config('jnt.logging.enabled', true);
        $loggingStatus = $loggingEnabled ? '<fg=green>Enabled</>' : '<fg=yellow>Disabled</>';
        $this->line("   Logging: {$loggingStatus}");

        // Webhooks Configuration
        $webhooksEnabled = config('jnt.webhooks.enabled', true);
        $webhooksStatus = $webhooksEnabled ? '<fg=green>Enabled</>' : '<fg=yellow>Disabled</>';
        $this->line("   Webhooks: {$webhooksStatus}");
    }
}
