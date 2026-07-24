<?php

namespace App\Enums;

enum UserRole: string
{
    case SuperAdmin = 'super_admin';
    case AdminStaff = 'admin_staff';
    case InternalAuditor = 'internal_auditor';
    case SellerCompanyAdmin = 'seller_company_admin';
    case SellerStaff = 'seller_staff';
    case IndividualSellerAgent = 'individual_seller_agent';
    case BuyerAdmin = 'buyer_admin';

    public function label(): string
    {
        $translated = __('rml.roles.'.$this->value);

        if (is_string($translated) && $translated !== 'rml.roles.'.$this->value) {
            return $translated;
        }

        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::AdminStaff => 'Admin Staff',
            self::InternalAuditor => 'Internal Auditor',
            self::SellerCompanyAdmin => 'Seller Admin',
            self::SellerStaff => 'Seller Staff',
            self::IndividualSellerAgent => 'Seller Agent',
            self::BuyerAdmin => 'Buyer',
        };
    }

    public function portal(): string
    {
        return match ($this) {
            self::SuperAdmin, self::AdminStaff => 'admin',
            self::InternalAuditor => 'auditor',
            self::SellerCompanyAdmin, self::SellerStaff, self::IndividualSellerAgent => 'seller',
            self::BuyerAdmin => 'buyer',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
