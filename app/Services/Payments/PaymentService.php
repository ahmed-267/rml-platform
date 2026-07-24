<?php

namespace App\Services\Payments;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Mail\BuyerPaymentCreatedMail;
use App\Models\Payment;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\Documents\InvoiceDocumentService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class PaymentService
{
    public function __construct(
        private readonly PaymentProviderManager $providers = new PaymentProviderManager,
        private readonly AuditLogService $auditLogService = new AuditLogService,
        private readonly InvoiceDocumentService $invoiceDocumentService = new InvoiceDocumentService,
        private readonly PaymentSettlementService $settlementService = new PaymentSettlementService,
    ) {}

    /**
     * Start provider checkout / bank transfer for an existing pending payment.
     *
     * @return array{checkout_url: string|null, bank_instructions: array<string, mixed>|null, card_configured: bool, mollie_configured: bool, provider: string, status: string, error?: string}
     */
    public function initiate(Payment $payment, User $payer): array
    {
        if ($payment->status === PaymentStatus::Paid) {
            throw ValidationException::withMessages([
                'payment' => __('rml.payments.already_paid'),
            ]);
        }

        // Allow retry after failed/cancelled Stripe attempts.
        if (in_array($payment->status, [PaymentStatus::Failed, PaymentStatus::Cancelled], true)) {
            $payment->update([
                'status' => PaymentStatus::Pending,
                'failed_at' => null,
                'cancelled_at' => null,
            ]);
            $payment->refresh();
        }

        if ($payment->status !== PaymentStatus::Pending) {
            throw ValidationException::withMessages([
                'payment' => __('rml.payments.not_payable'),
            ]);
        }

        $method = $payment->method ?? PaymentMethod::ManualBankTransfer;
        $provider = $this->providers->forMethod($method);

        if ($method === PaymentMethod::Card && ! $provider->isConfigured()) {
            $payment->update([
                'provider' => $provider->providerName(),
                'metadata' => array_merge($payment->metadata ?? [], [
                    'provider_not_configured' => true,
                ]),
            ]);

            return [
                'checkout_url' => null,
                'bank_instructions' => null,
                'card_configured' => false,
                'mollie_configured' => false,
                'provider' => $provider->providerName(),
                'status' => PaymentStatus::Pending->value,
                'error' => __('rml.payments.card_not_configured'),
            ];
        }

        $redirectUrl = route('buyer.payments.success', $payment);

        try {
            $result = $provider->createPayment(
                $payment,
                $payer,
                'RML purchase '.$payment->payment_reference,
                $redirectUrl,
            );
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages([
                'payment_method' => $e->getMessage(),
            ]);
        }

        $metadata = array_merge($payment->metadata ?? [], $result['metadata'] ?? []);
        unset($metadata['provider_not_configured']);

        $updates = [
            'provider' => $provider->providerName(),
            'provider_payment_id' => $result['provider_payment_id'] ?? $payment->provider_payment_id,
            'provider_reference' => $result['provider_payment_id'] ?? $payment->provider_reference,
            'status' => PaymentStatus::tryFrom($result['status'] ?? '') ?? $payment->status,
            'metadata' => $metadata,
            'provider_status' => $result['provider_status'] ?? ($result['metadata']['stripe_status'] ?? $payment->provider_status),
        ];

        if ($provider->providerName() === 'stripe') {
            $updates['stripe_checkout_session_id'] = $result['stripe_checkout_session_id']
                ?? $result['provider_payment_id']
                ?? $payment->stripe_checkout_session_id;
            $updates['stripe_payment_intent_id'] = $result['stripe_payment_intent_id']
                ?? $payment->stripe_payment_intent_id;
            $updates['provider_payload'] = $result['provider_payload'] ?? $payment->provider_payload;
        }

        $payment->update($updates);

        $purchase = $payment->purchases()->with('items')->first();
        if ($purchase) {
            $this->invoiceDocumentService->ensureBuyerInvoice($purchase, $payment->fresh() ?? $payment);
        }

        if ($method === PaymentMethod::ManualBankTransfer) {
            $this->settlementService->notifyPendingReview($payment->fresh() ?? $payment);
        }

        if ($payer->email) {
            Mail::to($payer->email)->queue(new BuyerPaymentCreatedMail($payment->fresh() ?? $payment));
        }

        $this->auditLogService->log(
            'payment.initiated',
            $payment,
            null,
            [
                'provider' => $provider->providerName(),
                'method' => $method->value,
                'provider_payment_id' => $result['provider_payment_id'] ?? null,
            ],
            $payer,
        );

        $cardConfigured = $this->providers->cardConfigured();

        return [
            'checkout_url' => $result['checkout_url'] ?? null,
            'bank_instructions' => $method === PaymentMethod::ManualBankTransfer
                ? $this->providers->manual()->instructions($payment->fresh() ?? $payment)
                : null,
            'card_configured' => $cardConfigured,
            'mollie_configured' => $cardConfigured,
            'provider' => $provider->providerName(),
            'status' => ($payment->fresh() ?? $payment)->status?->value ?? PaymentStatus::Pending->value,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function bankInstructions(Payment $payment): ?array
    {
        if ($payment->method !== PaymentMethod::ManualBankTransfer) {
            return null;
        }

        return $this->providers->manual()->instructions($payment);
    }

    public function refreshFromProvider(Payment $payment): Payment
    {
        $providerName = $payment->provider;
        $providerId = $payment->provider_payment_id
            ?? $payment->stripe_checkout_session_id;

        if (! $providerId || ! in_array($providerName, ['mollie', 'stripe'], true)) {
            return $payment;
        }

        $provider = $this->providers->byName($providerName);
        $status = $provider->getPaymentStatus($providerId);

        if ($status['paid']) {
            $meta = ['confirmed_via' => 'provider_refresh'];
            if (! empty($status['payment_intent_id'])) {
                $payment->update([
                    'stripe_payment_intent_id' => $status['payment_intent_id'],
                    'provider_status' => $status['status'] ?? $payment->provider_status,
                ]);
            }

            return $this->settlementService->confirmPaid($payment->fresh() ?? $payment, null, $meta);
        }

        if ($status['failed']) {
            return $this->settlementService->markFailed($payment, null, ['keep_purchase' => true]);
        }

        if ($status['cancelled']) {
            // Leave payable for retry; do not cancel purchase from return/refresh alone.
            return $payment;
        }

        return $payment;
    }
}
