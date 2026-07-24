<?php

namespace App\Enums;

enum PurchaseItemType: string
{
    case Lead = 'lead';
    case Package = 'package';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
