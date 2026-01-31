<?php

namespace App\Shared\Enums;

enum Status: string
{
    case PENDING = 'pending';
    case QUEUED = 'queued';
    case PROCESSING = 'processing';
    case SENT = 'sent';
    case DELIVERED = 'delivered';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';

    /**
     * Get all status values
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Check if status is final (no further processing)
     * 
     * DELIVERED: Successfully delivered to recipient
     * CANCELLED: Cancelled by user/system
     * 
     * Note: FAILED is NOT final - notifications can be retried after failure
     */
    public function isFinal(): bool
    {
        return in_array($this, [
            self::DELIVERED,
            self::CANCELLED,
        ]);
    }
}
