<?php

namespace App\Enums;

enum HomeownerEnquiryStatus: string
{
    case New = 'new';
    case Contacted = 'contacted';
    case Qualified = 'qualified';
    case Rejected = 'rejected';
    case Converted = 'converted';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
