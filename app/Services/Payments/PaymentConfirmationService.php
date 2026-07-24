<?php

namespace App\Services\Payments;

use App\Models\Payment;
use App\Models\User;
use App\Services\Admin\PaymentConfirmationService as AdminPaymentConfirmationService;

/**
 * Shared payment confirmation entry point for Stripe webhooks, provider refresh,
 * and admin manual confirmation. Delegates to settlement / admin services.
 */
class PaymentConfirmationService
{
    public function __construct(
        private readonly PaymentSettlementService $settlementService = new PaymentSettlementService,
        private readonly AdminPaymentConfirmationService $adminConfirmationService = new AdminPaymentConfirmationService,
    ) {}

    /**
     * @param  array<string, mixed>  $meta
     */
    public function confirmPaid(Payment $payment, ?User $actor = null, array $meta = []): Payment
    {
        if ($actor) {
            return $this->adminConfirmationService->markBuyerPaymentPaid($actor, $payment, $meta);
        }

        return $this->settlementService->confirmPaid($payment, null, $meta);
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function markFailed(Payment $payment, ?User $actor = null, array $options = []): Payment
    {
        return $this->settlementService->markFailed($payment, $actor, $options);
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function markCancelled(Payment $payment, ?User $actor = null, array $options = []): Payment
    {
        return $this->settlementService->markCancelled($payment, $actor, $options);
    }
}
