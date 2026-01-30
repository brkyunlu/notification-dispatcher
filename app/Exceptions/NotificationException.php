<?php

namespace App\Exceptions;

use Exception;

class NotificationException extends Exception
{
    /**
     * Create a new exception instance for duplicate idempotency key
     */
    public static function duplicateIdempotencyKey(string $key): self
    {
        return new self(
            "This request has already been processed. Idempotency key: {$key}",
            409
        );
    }

    /**
     * Create a new exception instance for notification not found
     */
    public static function notFound(string $id): self
    {
        return new self(
            "Notification with ID {$id} not found",
            404
        );
    }

    /**
     * Create a new exception instance for invalid status transition
     */
    public static function invalidStatusTransition(string $from, string $to): self
    {
        return new self(
            "Cannot transition from status '{$from}' to '{$to}'",
            422
        );
    }
}
