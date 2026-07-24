<?php

namespace App\Enums;

enum PackageType: string
{
    case Custom = 'custom';
    case Prebuilt = 'prebuilt';
    case MixedZone = 'mixed_zone';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
