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
}
