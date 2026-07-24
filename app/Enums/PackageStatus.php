<?php

namespace App\Enums;

enum PackageStatus: string
{
    case Draft = 'draft';
    case Available = 'available';
    case Locked = 'locked';
    case Sold = 'sold';
    case Cancelled = 'cancelled';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
