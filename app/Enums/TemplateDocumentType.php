<?php

namespace App\Enums;

enum TemplateDocumentType: string
{
    case SellerAgreement = 'seller_agreement';
    case BuyerAgreement = 'buyer_agreement';
    case HomeownerAgreement = 'homeowner_agreement';
    case EligibilityRequirements = 'eligibility_requirements';
    case SellerTerms = 'seller_terms';
    case BuyerTerms = 'buyer_terms';
    case SellerGdpr = 'seller_gdpr';
    case BuyerGdpr = 'buyer_gdpr';
    case HomeownerConsent = 'homeowner_consent';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
