<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Exceptions;

use Exception;
use Throwable;

class JntException extends Exception
{
    public function __construct(
        string $message,
        public readonly ?string $errorCode = null,
        public readonly mixed $data = null,
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    public static function apiError(string $message, ?string $errorCode = null, mixed $data = null): self
    {
        return new self($message, $errorCode, $data);
    }

    public static function invalidConfiguration(string $message): self
    {
        return new self($message);
    }

    public static function invalidSignature(): self
    {
        return new self('Invalid signature verification');
    }

    public static function missingCredentials(string $credential): self
    {
        return new self('Missing required credential: '.$credential);
    }
}
