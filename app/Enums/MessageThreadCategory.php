<?php

namespace App\Enums;

enum MessageThreadCategory: string
{
    case SellerIssue = 'seller_issue';
    case BuyerIssue = 'buyer_issue';
    case PaymentQuery = 'payment_query';
    case InformationRequest = 'information_request';
    case Complaint = 'complaint';
    case Dispute = 'dispute';
    case Internal = 'internal';
    case AuditQuestion = 'audit_question';
    case EvidenceIssue = 'evidence_issue';
    case LeadReview = 'lead_review';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
