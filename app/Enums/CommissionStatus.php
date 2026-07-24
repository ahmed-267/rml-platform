<?php

namespace App\Enums;

enum CommissionStatus: string
{
    case Pending = 'pending';
    case Due = 'due';
    case Paid = 'paid';
    case Cancelled = 'cancelled';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
