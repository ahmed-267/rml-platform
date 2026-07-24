<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Card = 'card';
    case ManualBankTransfer = 'manual_bank_transfer';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
