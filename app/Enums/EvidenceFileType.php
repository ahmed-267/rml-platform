<?php

namespace App\Enums;

enum EvidenceFileType: string
{
    case Photo = 'photo';
    case Video = 'video';
    case SignedHomeownerAgreement = 'signed_homeowner_agreement';
    case EligibilityDocument = 'eligibility_document';
    case RegistrationDocument = 'registration_document';
    case Other = 'other';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
