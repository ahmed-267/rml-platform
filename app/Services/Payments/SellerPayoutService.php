<?php

namespace App\Services\Payments;

use App\Enums\PayoutStatus;
use App\Enums\SellerType;
use App\Models\Lead;
use App\Models\Payment;
use App\Models\Payout;
use App\Models\Purchase;
use App\Services\AuditLogService;
use App\Services\Documents\InvoiceDocumentService;
use App\Support\PayoutSettings;
use App\Support\ReferenceGenerator;

class SellerPayoutService
{
    public function __construct(
        private readonly AuditLogService $auditLogService = new AuditLogService,
        private readonly InvoiceDocumentService $invoiceDocumentService = new InvoiceDocumentService,
    ) {}

    public function createDueForLead(Lead $lead, Purchase $purchase, Payment $payment): ?Payout
    {
        $buying = $lead->buying_price !== null ? (float) $lead->buying_price : 0.0;

        if (! $lead->seller_company_id && ! $lead->submitted_by_user_id) {
            return null;
        }

        $existing = Payout::query()
            ->where(function ($query) use ($lead) {
                if ($lead->seller_company_id) {
                    $query->where('seller_company_id', $lead->seller_company_id);
                } else {
                    $query->where('seller_user_id', $lead->submitted_by_user_id);
                }
            })
            ->where('notes', 'like', '%'.$lead->lead_reference.'%')
            ->where('status', PayoutStatus::Pending->value)
            ->first();

        if ($existing) {
            return $existing;
        }

        $notes = $buying > 0
            ? 'Seller payout due for '.$lead->lead_reference
            : 'Seller payout pending_review for '.$lead->lead_reference.' (buying price missing)';

        $lead->loadMissing('submittedBy.sellerProfile');
        $sellerType = $lead->submittedBy?->sellerProfile?->seller_type;
        $isIndividualAgent = $sellerType === SellerType::IndividualAgent;
        $isRmlInternal = ($lead->lead_source ?? null) === 'rml_internal'
            || ($lead->seller_company_id === null && ! $isIndividualAgent);

        if ($isRmlInternal && ! PayoutSettings::rmlInternalPayouts()) {
            return null;
        }
        if ($isIndividualAgent && ! PayoutSettings::agentPayouts()) {
            return null;
        }
        if (! $isIndividualAgent && ! $isRmlInternal && ! PayoutSettings::companyPayouts()) {
            return null;
        }
        // Staff under a company do not receive a separate RML payout — company does.
        if ($sellerType === SellerType::SellerStaff) {
            if (! PayoutSettings::staffPayoutToCompany()) {
                return null;
            }

            return null;
        }

        $payout = Payout::query()->create([
            'payout_reference' => ReferenceGenerator::payout(),
            'seller_company_id' => $isIndividualAgent ? null : $lead->seller_company_id,
            'seller_user_id' => $isIndividualAgent
                ? $lead->submitted_by_user_id
                : ($lead->seller_company_id ? null : $lead->submitted_by_user_id),
            'payment_id' => null,
            'status' => PayoutStatus::Pending,
            'amount' => $buying,
            'currency' => $payment->currency ?: 'EUR',
            'due_date' => now()->addDays(7)->toDateString(),
            'notes' => $notes,
        ]);

        $this->invoiceDocumentService->ensureSellerPayoutStatement($payout);
        $this->auditLogService->log(
            'payout.due_created',
            $payout,
            null,
            [
                'lead_reference' => $lead->lead_reference,
                'purchase_reference' => $purchase->purchase_reference,
                'amount' => $buying,
            ],
        );

        return $payout;
    }
}
