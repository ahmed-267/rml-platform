<?php

namespace App\Services\Payments;

use App\Enums\CommissionAppliesTo;
use App\Enums\SellerType;
use App\Models\Commission;
use App\Models\Lead;
use App\Models\Purchase;
use App\Services\AuditLogService;
use App\Services\CommissionService;
use App\Services\Documents\InvoiceDocumentService;

class CommissionDueService
{
    public function __construct(
        private readonly CommissionService $commissionService = new CommissionService,
        private readonly AuditLogService $auditLogService = new AuditLogService,
        private readonly InvoiceDocumentService $invoiceDocumentService = new InvoiceDocumentService,
    ) {}

    public function createDueForLead(Lead $lead, Purchase $purchase): ?Commission
    {
        $submitter = $lead->submittedBy;
        if (! $submitter) {
            return null;
        }

        $existing = Commission::query()
            ->where('lead_id', $lead->id)
            ->where('seller_user_id', $submitter->id)
            ->first();

        if ($existing) {
            $commission = $this->commissionService->markDueIfEligible($existing, $lead->fresh(), $purchase->fresh());
            $this->invoiceDocumentService->ensureCommissionStatement($commission);

            return $commission;
        }

        $profile = $submitter->sellerProfile;
        $appliesTo = match ($profile?->seller_type) {
            SellerType::SellerStaff => CommissionAppliesTo::SellerStaff,
            SellerType::IndividualAgent => CommissionAppliesTo::IndividualAgent,
            default => CommissionAppliesTo::SellerCompany,
        };

        $commission = $this->commissionService->createForLead(
            $lead,
            $submitter->id,
            $appliesTo,
            $profile?->commission_rate !== null ? (float) $profile->commission_rate : null,
            $lead->seller_company_id,
        );

        $commission = $this->commissionService->markDueIfEligible($commission, $lead->fresh(), $purchase->fresh());
        $this->invoiceDocumentService->ensureCommissionStatement($commission);

        $this->auditLogService->log(
            'commission.due_created',
            $commission,
            null,
            [
                'lead_reference' => $lead->lead_reference,
                'purchase_reference' => $purchase->purchase_reference,
                'amount' => (float) $commission->commission_amount,
                'percentage' => (float) $commission->percentage,
            ],
        );

        return $commission;
    }
}
