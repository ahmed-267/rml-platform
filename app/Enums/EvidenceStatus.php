<?php

namespace App\Enums;

enum EvidenceStatus: string
{
    case Uploaded = 'uploaded';
    case Approved = 'approved';
    case Rejected = 'rejected';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
