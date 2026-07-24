<?php

namespace App\Services\Payments\Providers;

use App\Contracts\Payments\PaymentProviderInterface;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\User;

class ManualBankTransferProvider implements PaymentProviderInterface
{
    public function providerName(): string
    {
        return 'manual_bank_transfer';
    }

    public function isConfigured(): bool
    {
        return filled(config('payments.bank_transfer.iban'));
    }

    public function createPayment(Payment $payment, User $payer, string $description, string $redirectUrl): array
    {
        return [
            'checkout_url' => null,
            'provider_payment_id' => $payment->payment_reference,
            'status' => PaymentStatus::Pending->value,
            'metadata' => [
                'instructions' => $this->instructions($payment),
            ],
        ];
    }

    public function getPaymentStatus(string $providerPaymentId): array
    {
        $payment = Payment::query()
            ->where('provider_payment_id', $providerPaymentId)
            ->orWhere('payment_reference', $providerPaymentId)
            ->first();

        $status = $payment?->status?->value ?? PaymentStatus::Pending->value;

        return [
            'status' => $status,
            'paid' => $status === PaymentStatus::Paid->value,
            'cancelled' => $status === PaymentStatus::Cancelled->value,
            'failed' => $status === PaymentStatus::Failed->value,
        ];
    }

    public function handleWebhook(array $payload): array
    {
        return [
            'provider_payment_id' => null,
            'status' => PaymentStatus::Pending->value,
            'paid' => false,
            'cancelled' => false,
            'failed' => false,
            'event_id' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function instructions(Payment $payment): array
    {
        $bank = config('payments.bank_transfer', []);

        return [
            'account_name' => $bank['account_name'] ?? null,
            'iban' => $bank['iban'] ?? null,
            'bic' => $bank['bic'] ?? null,
            'bank_name' => $bank['bank_name'] ?? null,
            'instructions' => $bank['instructions'] ?? null,
            'amount' => (float) $payment->amount,
            'currency' => $payment->currency ?? 'EUR',
            'payment_reference' => $payment->payment_reference,
        ];
    }
}
