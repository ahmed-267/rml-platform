<?php

namespace App\Enums;

enum CatastroProvider: string
{
    case National = 'national_catastro';
    case Navarra = 'navarra_regional';
    case Basque = 'basque_foral';
    case Manual = 'manual_verification';
    case Unknown = 'unknown';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
