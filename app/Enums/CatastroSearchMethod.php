<?php

namespace App\Enums;

enum CatastroSearchMethod: string
{
    case Reference = 'reference';
    case Address = 'address';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
