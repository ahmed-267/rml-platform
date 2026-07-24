<?php

namespace App\Services\Admin;

use App\Enums\CommissionStatus;
use App\Enums\PaymentStatus;
use App\Enums\PayoutStatus;
use App\Enums\UserRole;
use App\Mail\SellerCommissionPaidMail;
use App\Mail\SellerPayoutPaidMail;
use App\Models\Commission;
use App\Models\Payment;
use App\Models\Payout;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\Documents\InvoiceDocumentService;
use App\Services\Payments\PaymentSettlementService;
use App\Support\Permissions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class PaymentConfirmationService
{
    public function __construct(
        private readonly PaymentSettlementService $settlementService = new PaymentSettlementService,
        private readonly AuditLogService $auditLogService = new AuditLogService,
        private readonly InvoiceDocumentService $invoiceDocumentService = new InvoiceDocumentService,
    ) {}

    /**
     * @param  array{confirmation_note?: string|null, confirmation_reference?: string|null}  $meta
     */
    public function markBuyerPaymentPaid(User $actor, Payment $payment, array $meta = []): Payment
    {
        $this->assertCanManagePayments($actor);

        return $this->settlementService->confirmPaid($payment, $actor, array_merge($meta, [
            'confirmed_via' => 'admin_manual',
        ]));
    }

    public function markSellerPayoutPaid(User $actor, Payout $payout): Payout
    {
        $this->assertCanManagePayouts($actor);

        if ($payout->status === PayoutStatus::Paid) {
            return $payout;
        }

        return DB::transaction(function () use ($actor, $payout) {
            $payout = Payout::query()->whereKey($payout->id)->lockForUpdate()->firstOrFail();

            $payout->update([
                'status' => PayoutStatus::Paid,
                'paid_at' => now(),
            ]);

            if ($payout->payment_id) {
                Payment::query()->whereKey($payout->payment_id)->update([
                    'status' => PaymentStatus::Paid,
                    'paid_at' => now(),
                ]);
            }

            $this->invoiceDocumentService->ensureSellerPayoutStatement($payout);

            $this->auditLogService->log(
                'payout.marked_paid',
                $payout,
                ['status' => PayoutStatus::Pending->value],
                ['status' => PayoutStatus::Paid->value],
                $actor,
            );

            if ($payout->sellerUser?->email) {
                Mail::to($payout->sellerUser->email)->queue(new SellerPayoutPaidMail($payout));
            }

            return $payout->fresh(['sellerUser']) ?? $payout;
        });
    }

    public function markCommissionPaid(User $actor, Commission $commission): Commission
    {
        $this->assertCanManagePayouts($actor);

        if ($commission->status === CommissionStatus::Paid) {
            return $commission;
        }

        if (! in_array($commission->status, [CommissionStatus::Due, CommissionStatus::Pending], true)) {
            throw ValidationException::withMessages([
                'commission' => __('rml.admin.payments.cannot_mark_commission'),
            ]);
        }

        return DB::transaction(function () use ($actor, $commission) {
            $commission = Commission::query()->whereKey($commission->id)->lockForUpdate()->firstOrFail();

            $commission->update([
                'status' => CommissionStatus::Paid,
                'paid_at' => now(),
            ]);

            $this->invoiceDocumentService->ensureCommissionStatement($commission);

            $this->auditLogService->log(
                'commission.marked_paid',
                $commission,
                null,
                ['status' => CommissionStatus::Paid->value],
                $actor,
            );

            if ($commission->sellerUser?->email) {
                Mail::to($commission->sellerUser->email)->queue(new SellerCommissionPaidMail($commission));
            }

            return $commission->fresh(['sellerUser']) ?? $commission;
        });
    }

    public function cancelPendingPayment(User $actor, Payment $payment): Payment
    {
        $this->assertCanManagePayments($actor);

        return $this->settlementService->markCancelled($payment, $actor);
    }

    public function markFailed(User $actor, Payment $payment): Payment
    {
        $this->assertCanManagePayments($actor);

        return $this->settlementService->markFailed($payment, $actor);
    }

    private function assertCanManagePayments(User $actor): void
    {
        if (! $actor->can(Permissions::MANAGE_PAYMENTS) && ! $actor->can(Permissions::EDIT_PAYMENTS)) {
            abort(403);
        }
    }

    private function assertCanManagePayouts(User $actor): void
    {
        if (
            ! $actor->can(Permissions::MANAGE_PAYOUTS)
            && ! $actor->can(Permissions::MANAGE_PAYMENTS)
            && ! $actor->hasRole(UserRole::SuperAdmin->value)
        ) {
            abort(403);
        }
    }
}
