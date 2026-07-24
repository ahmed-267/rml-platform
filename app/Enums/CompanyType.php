<?php

namespace App\Enums;

enum CompanyType: string
{
    case RmlInternal = 'rml_internal';
    case Seller = 'seller';
    case Buyer = 'buyer';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
