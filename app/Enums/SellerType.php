<?php

namespace App\Enums;

enum SellerType: string
{
    case CompanyAdmin = 'company_admin';
    case SellerStaff = 'seller_staff';
    case IndividualAgent = 'individual_agent';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
