<?php

namespace App\Services\Payments;

use App\Enums\LeadStatus;
use App\Enums\PackageStatus;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Enums\PurchaseStatus;
use App\Enums\UserRole;
use App\Mail\BuyerPaymentConfirmedMail;
use App\Mail\LeadDetailsReleasedMail;
use App\Mail\PaymentPendingReviewMail;
use App\Mail\SellerCommissionDueMail;
use App\Mail\SellerPayoutDueMail;
use App\Models\Commission;
use App\Models\Payment;
use App\Models\Payout;
use App\Models\Purchase;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\Documents\InvoiceDocumentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class PaymentSettlementService
{
    public function __construct(
        private readonly AuditLogService $auditLogService = new AuditLogService,
        private readonly InvoiceDocumentService $invoiceDocumentService = new InvoiceDocumentService,
        private readonly SellerPayoutService $sellerPayoutService = new SellerPayoutService,
        private readonly CommissionDueService $commissionDueService = new CommissionDueService,
    ) {}

    /**
     * @param  array<string, mixed>  $meta
     */
    public function confirmPaid(Payment $payment, ?User $actor = null, array $meta = []): Payment
    {
        if ($payment->type !== PaymentType::BuyerPayment) {
            throw ValidationException::withMessages([
                'payment' => __('rml.admin.payments.not_buyer_payment'),
            ]);
        }

        if ($payment->status === PaymentStatus::Paid) {
            return $payment->fresh(['purchases.items.lead', 'invoices']) ?? $payment;
        }

        return DB::transaction(function () use ($payment, $actor, $meta) {
            $payment = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($payment->status === PaymentStatus::Paid) {
                return $payment->fresh(['purchases.items.lead', 'invoices']) ?? $payment;
            }

            $metadata = array_merge($payment->metadata ?? [], array_filter([
                'confirmation_note' => $meta['confirmation_note'] ?? null,
                'confirmation_reference' => $meta['confirmation_reference'] ?? null,
                'confirmed_via' => $meta['confirmed_via'] ?? ($actor ? 'admin' : 'webhook'),
            ], fn ($value) => $value !== null && $value !== ''));

            $payment->update([
                'status' => PaymentStatus::Paid,
                'paid_at' => now(),
                'metadata' => $metadata,
            ]);

            $purchases = Purchase::query()
                ->where('payment_id', $payment->id)
                ->with(['items.lead.submittedBy.sellerProfile', 'items.leadPackage', 'buyerUser'])
                ->lockForUpdate()
                ->get();

            $createdPayouts = [];
            $createdCommissions = [];

            foreach ($purchases as $purchase) {
                $purchase->update([
                    'status' => PurchaseStatus::Paid,
                    'purchased_at' => now(),
                ]);

                foreach ($purchase->items as $item) {
                    $lead = $item->lead;
                    if (! $lead) {
                        continue;
                    }

                    if ($lead->status !== LeadStatus::Sold) {
                        $lead->update([
                            'status' => LeadStatus::Sold,
                            'sold_at' => now(),
                        ]);
                    }

                    if ($item->leadPackage && $item->leadPackage->status !== PackageStatus::Sold) {
                        $item->leadPackage->update(['status' => PackageStatus::Sold]);
                    }

                    $payout = $this->sellerPayoutService->createDueForLead($lead, $purchase, $payment);
                    if ($payout) {
                        $createdPayouts[] = $payout;
                    }

                    $commission = $this->commissionDueService->createDueForLead($lead, $purchase);
                    if ($commission) {
                        $createdCommissions[] = $commission;
                    }
                }

                $this->invoiceDocumentService->ensureBuyerInvoice($purchase, $payment);
                $this->invoiceDocumentService->ensureBuyerReceipt($purchase, $payment);
            }

            $this->auditLogService->log(
                'payment.buyer_marked_paid',
                $payment,
                ['status' => PaymentStatus::Pending->value],
                [
                    'status' => PaymentStatus::Paid->value,
                    'paid_at' => now()->toIso8601String(),
                    'confirmed_via' => $metadata['confirmed_via'] ?? null,
                    'confirmation_note' => $metadata['confirmation_note'] ?? null,
                    'payment_reference' => $payment->payment_reference,
                ],
                $actor,
            );

            foreach ($purchases as $purchase) {
                $this->auditLogService->log(
                    'purchase.customer_details_released',
                    $purchase,
                    ['details_released' => false],
                    [
                        'details_released' => true,
                        'payment_id' => $payment->id,
                        'payment_reference' => $payment->payment_reference,
                        'lead_ids' => $purchase->items->pluck('lead_id')->filter()->values()->all(),
                    ],
                    $actor,
                );
            }

            $payment = $payment->fresh(['purchases.items.lead', 'invoices', 'payerUser']) ?? $payment;

            $this->dispatchPaidNotifications($payment, $createdPayouts, $createdCommissions);

            return $payment;
        });
    }

    public function markFailed(Payment $payment, ?User $actor = null, array $options = []): Payment
    {
        if ($payment->status === PaymentStatus::Paid) {
            throw ValidationException::withMessages([
                'payment' => __('rml.admin.payments.cannot_fail_paid'),
            ]);
        }

        return DB::transaction(function () use ($payment, $actor, $options) {
            $payment->update([
                'status' => PaymentStatus::Failed,
                'failed_at' => now(),
            ]);

            $keepPurchase = (bool) ($options['keep_purchase'] ?? false);
            if (! $keepPurchase && \App\Support\ReservationSettings::shouldReleaseOnPaymentFail()) {
                Purchase::query()
                    ->where('payment_id', $payment->id)
                    ->where('status', PurchaseStatus::Pending)
                    ->update(['status' => PurchaseStatus::Cancelled]);
            }

            $this->auditLogService->log(
                'payment.failed',
                $payment,
                null,
                [
                    'status' => PaymentStatus::Failed->value,
                    'keep_purchase' => $keepPurchase
                        || ! \App\Support\ReservationSettings::shouldReleaseOnPaymentFail(),
                ],
                $actor,
            );

            return $payment->fresh() ?? $payment;
        });
    }

    public function markCancelled(Payment $payment, ?User $actor = null, array $options = []): Payment
    {
        if ($payment->status === PaymentStatus::Paid) {
            throw ValidationException::withMessages([
                'payment' => __('rml.admin.payments.cannot_cancel'),
            ]);
        }

        if ($payment->status !== PaymentStatus::Pending && ! ($options['force'] ?? false)) {
            throw ValidationException::withMessages([
                'payment' => __('rml.admin.payments.cannot_cancel'),
            ]);
        }

        return DB::transaction(function () use ($payment, $actor, $options) {
            $payment->update([
                'status' => PaymentStatus::Cancelled,
                'cancelled_at' => now(),
            ]);

            if (! ($options['keep_purchase'] ?? false)) {
                Purchase::query()
                    ->where('payment_id', $payment->id)
                    ->where('status', PurchaseStatus::Pending)
                    ->update(['status' => PurchaseStatus::Cancelled]);
            }

            $this->auditLogService->log(
                'payment.cancelled',
                $payment,
                null,
                [
                    'status' => PaymentStatus::Cancelled->value,
                    'keep_purchase' => (bool) ($options['keep_purchase'] ?? false),
                ],
                $actor,
            );

            return $payment->fresh() ?? $payment;
        });
    }

    /**
     * @param  list<Payout>  $payouts
     * @param  list<Commission>  $commissions
     */
    private function dispatchPaidNotifications(Payment $payment, array $payouts, array $commissions): void
    {
        $buyer = $payment->payerUser;
        if ($buyer?->email) {
            Mail::to($buyer->email)->queue(new BuyerPaymentConfirmedMail($payment));
            Mail::to($buyer->email)->queue(new LeadDetailsReleasedMail($payment));
        }

        foreach ($payouts as $payout) {
            $seller = $payout->sellerUser;
            if ($seller?->email) {
                Mail::to($seller->email)->queue(new SellerPayoutDueMail($payout));
            }
        }

        foreach ($commissions as $commission) {
            $seller = $commission->sellerUser;
            if ($seller?->email) {
                Mail::to($seller->email)->queue(new SellerCommissionDueMail($commission));
            }
        }
    }

    public function notifyPendingReview(Payment $payment): void
    {
        $admins = User::query()
            ->role([UserRole::SuperAdmin->value, UserRole::AdminStaff->value])
            ->get();

        foreach ($admins as $admin) {
            if ($admin->email) {
                Mail::to($admin->email)->queue(new PaymentPendingReviewMail($payment));
            }
        }
    }
}
