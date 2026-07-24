<?php

namespace App\Enums;

enum PaymentType: string
{
    case BuyerPayment = 'buyer_payment';
    case SellerPayout = 'seller_payout';
    case CommissionPayout = 'commission_payout';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
