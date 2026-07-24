<?php

namespace App\Enums;

enum CommissionAppliesTo: string
{
    case SellerStaff = 'seller_staff';
    case IndividualAgent = 'individual_agent';
    case SellerCompany = 'seller_company';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
