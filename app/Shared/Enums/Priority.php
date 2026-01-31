<?php

namespace App\Shared\Enums;

enum Priority: string
{
    case LOW = 'low';
    case NORMAL = 'normal';
    case HIGH = 'high';

    /**
     * Get all priority values
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get queue name for this priority
     */
    public function getQueueName(): string
    {
        return match ($this) {
            self::HIGH => 'notifications-high',
            self::NORMAL => 'notifications-normal',
            self::LOW => 'notifications-low',
        };
    }

    /**
     * Get number of workers for this priority
     */
    public function getWorkerCount(): int
    {
        return match ($this) {
            self::HIGH => 4,
            self::NORMAL => 2,
            self::LOW => 1,
        };
    }
}
