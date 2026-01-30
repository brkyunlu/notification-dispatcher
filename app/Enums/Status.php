<?php

namespace App\Enums;

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
     */
    public function isFinal(): bool
    {
        return in_array($this, [
            self::DELIVERED,
            self::FAILED,
            self::CANCELLED,
        ]);
    }
}
