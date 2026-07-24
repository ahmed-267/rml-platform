<?php

namespace App\Enums;

enum InvoiceType: string
{
    case BuyerInvoice = 'buyer_invoice';
    case BuyerReceipt = 'buyer_receipt';
    case SellerStatement = 'seller_statement';
    case CommissionStatement = 'commission_statement';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
