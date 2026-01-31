<?php

namespace App\Modules\Notification\Exceptions;

/**
 * Exception class for Notification module errors
 */
class NotificationException extends \RuntimeException
{
    /**
     * Additional error details
     */
    public array $details = [];

    /**
     * Create exception for duplicate idempotency key
     */
    public static function duplicateIdempotency(string $key): self
    {
        $exception = new self(
            'This request has already been processed.',
            409
        );
        $exception->details = ['idempotency_key' => $key];
        return $exception;
    }

    /**
     * Create exception for notification not found
     */
    public static function notFound(string $id): self
    {
        return new self(
            "Notification not found.",
            404
        );
    }

    /**
     * Create exception for invalid status transition (e.g., cannot cancel)
     */
    public static function cannotCancel(string $currentStatus, array $allowedStatuses = ['pending', 'queued']): self
    {
        $exception = new self(
            'Cannot cancel notification in current status.',
            422
        );
        $exception->details = [
            'current_status' => $currentStatus,
            'allowed_statuses' => $allowedStatuses,
        ];
        return $exception;
    }

    /**
     * Create exception for template not found
     */
    public static function templateNotFound(): self
    {
        return new self(
            'Template not found.',
            404
        );
    }

    /**
     * Create exception for inactive template
     */
    public static function templateInactive(): self
    {
        return new self(
            'Template is not active.',
            400
        );
    }

    /**
     * Create exception for channel mismatch
     */
    public static function channelMismatch(string $templateChannel): self
    {
        return new self(
            "Channel mismatch. Template is for {$templateChannel} channel.",
            400
        );
    }

    /**
     * Add details to the exception
     */
    public function withDetails(array $details): self
    {
        $this->details = array_merge($this->details, $details);
        return $this;
    }
}
