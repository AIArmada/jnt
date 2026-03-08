<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Health;

use AIArmada\CommerceSupport\Health\CommerceHealthCheck;
use AIArmada\Jnt\Exceptions\JntConfigurationException;
use AIArmada\Jnt\Http\JntClient;
use Spatie\Health\Checks\Result;
use Throwable;

/**
 * Health check for J&T Express API.
 */
class JntHealthCheck extends CommerceHealthCheck
{
    public ?string $name = 'J&T Express API';

    /**
     * Timeout in seconds.
     */
    protected int $timeout = 15;

    /**
     * Set the timeout.
     */
    public function timeout(int $seconds): self
    {
        $this->timeout = $seconds;

        return $this;
    }

    /**
     * Perform the health check.
     */
    protected function performCheck(): Result
    {
        $apiAccount = (string) config('jnt.api_account', '');
        $privateKey = (string) config('jnt.private_key', '');
        $customerCode = (string) config('jnt.customer_code', '');
        $password = (string) config('jnt.password', '');

        if ($apiAccount === '' || $privateKey === '') {
            return $this->warning('J&T API credentials not configured');
        }

        if ($customerCode === '' || $password === '') {
            return $this->warning('J&T order credentials not configured');
        }

        $environment = (string) config('jnt.environment', 'testing');
        $baseUrls = config('jnt.base_urls', []);

        if (! is_array($baseUrls)) {
            return $this->warning('J&T API base URLs are not configured');
        }

        $normalizedEnvironment = match ($environment) {
            'production' => 'production',
            'testing', 'local', 'development' => 'testing',
            default => null,
        };

        if ($normalizedEnvironment === null) {
            return $this->warning(JntConfigurationException::invalidEnvironment($environment)->getMessage());
        }

        $baseUrl = $baseUrls[$normalizedEnvironment] ?? null;

        if (! is_string($baseUrl) || $baseUrl === '') {
            return $this->warning('J&T API base URL is not configured');
        }

        try {
            // Verify the client can be instantiated
            $client = new JntClient($baseUrl, $apiAccount, $privateKey, config('jnt', []));
            unset($client);

            // If we get here, the client is configured
            return $this->success('J&T Express API is configured', [
                'environment' => $normalizedEnvironment,
                'base_url' => $baseUrl,
            ]);
        } catch (Throwable $e) {
            return $this->failure('Failed to initialize J&T Express client: ' . $e->getMessage());
        }
    }
}
