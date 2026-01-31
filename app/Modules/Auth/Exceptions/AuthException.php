<?php

namespace App\Modules\Auth\Exceptions;

/**
 * Exception class for Auth module errors
 */
class AuthException extends \RuntimeException
{
    /**
     * Create exception for missing API key
     */
    public static function missingApiKey(): self
    {
        return new self(
            'API key is required. Use Authorization: Bearer <api_key> header.',
            401
        );
    }

    /**
     * Create exception for invalid API key
     */
    public static function invalidApiKey(): self
    {
        return new self(
            'Invalid API key.',
            401
        );
    }

    /**
     * Create exception for deactivated API key
     */
    public static function deactivated(): self
    {
        return new self(
            'API key has been deactivated.',
            401
        );
    }

    /**
     * Create exception for expired API key
     */
    public static function expired(): self
    {
        return new self(
            'API key has expired.',
            401
        );
    }

    /**
     * Create exception for insufficient permissions
     */
    public static function insufficientPermissions(string $required): self
    {
        return new self(
            "Insufficient permissions. Required: {$required}",
            403
        );
    }
}
