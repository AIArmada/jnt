<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Exceptions;

use Throwable;

/**
 * Exception thrown when J&T package configuration is invalid or missing.
 *
 * This exception helps identify configuration issues during setup
 * or runtime operations.
 */
class JntConfigurationException extends JntException
{
    public function __construct(
        string $message,
        public readonly ?string $configKey = null,
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, null, null, $code, $previous);
    }

    /**
     * Create exception for missing API key.
     */
    public static function missingApiKey(): self
    {
        return new self(
            message: 'J&T API key is not configured. Please set JNT_API_KEY in your environment or config/jnt.php',
            configKey: 'jnt.api_key'
        );
    }

    /**
     * Create exception for invalid API key format.
     */
    public static function invalidApiKey(): self
    {
        return new self(
            message: 'J&T API key format is invalid. Please check your configuration',
            configKey: 'jnt.api_key'
        );
    }

    /**
     * Create exception for missing private key.
     */
    public static function missingPrivateKey(): self
    {
        return new self(
            message: 'J&T private key is not configured. Please set JNT_PRIVATE_KEY in your environment or config/jnt.php',
            configKey: 'jnt.private_key'
        );
    }

    /**
     * Create exception for invalid private key format.
     */
    public static function invalidPrivateKey(): self
    {
        return new self(
            message: 'J&T private key format is invalid. Please check your RSA private key configuration',
            configKey: 'jnt.private_key'
        );
    }

    /**
     * Create exception for missing public key.
     */
    public static function missingPublicKey(): self
    {
        return new self(
            message: 'J&T public key is not configured. Please set JNT_PUBLIC_KEY in your environment or config/jnt.php',
            configKey: 'jnt.public_key'
        );
    }

    /**
     * Create exception for invalid public key format.
     */
    public static function invalidPublicKey(): self
    {
        return new self(
            message: 'J&T public key format is invalid. Please check your RSA public key configuration',
            configKey: 'jnt.public_key'
        );
    }

    /**
     * Create exception for missing API account.
     */
    public static function missingApiAccount(): self
    {
        return new self(
            message: 'J&T API account is not configured. Please set JNT_API_ACCOUNT in your environment or config/jnt.php',
            configKey: 'jnt.api_account'
        );
    }

    /**
     * Create exception for missing webhook URL.
     */
    public static function missingWebhookUrl(): self
    {
        return new self(
            message: 'J&T webhook URL is not configured. Please set JNT_WEBHOOK_URL in your environment or config/jnt.php',
            configKey: 'jnt.webhook.url'
        );
    }

    /**
     * Create exception for invalid configuration value.
     */
    public static function invalidValue(string $configKey, string $reason): self
    {
        return new self(
            message: sprintf("Invalid configuration value for '%s': %s", $configKey, $reason),
            configKey: $configKey
        );
    }

    /**
     * Create exception for missing configuration key.
     */
    public static function missingKey(string $configKey): self
    {
        return new self(
            message: sprintf("Required configuration key '%s' is missing", $configKey),
            configKey: $configKey
        );
    }

    /**
     * Create exception for invalid environment.
     */
    public static function invalidEnvironment(string $environment): self
    {
        return new self(
            message: sprintf("Invalid J&T environment '%s'. Must be 'production' or 'sandbox'", $environment),
            configKey: 'jnt.environment'
        );
    }
}
