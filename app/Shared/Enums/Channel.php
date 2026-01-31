<?php

namespace App\Shared\Enums;

enum Channel: string
{
    case SMS = 'sms';
    case EMAIL = 'email';
    case PUSH = 'push';

    /**
     * Get all channel values
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
