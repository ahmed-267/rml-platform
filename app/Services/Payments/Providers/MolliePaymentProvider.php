<?php

namespace App\Services\Payments\Providers;

use App\Contracts\Payments\PaymentProviderInterface;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Mollie\Api\Exceptions\RequestException;
use Mollie\Laravel\Facades\Mollie;
use RuntimeException;

class MolliePaymentProvider implements PaymentProviderInterface
{
    public function providerName(): string
    {
        return 'mollie';
    }

    public function isConfigured(): bool
    {
        $key = (string) (config('mollie.key') ?: config('payments.mollie.key', ''));

        if ($key === '' || str_contains($key, 'your-') || str_contains($key, 'xxxxxxxx')) {
            return false;
        }

        return (bool) preg_match('/^(live|test)_[A-Za-z0-9]+$/', $key);
    }

    public function createPayment(Payment $payment, User $payer, string $description, string $redirectUrl): array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException(__('rml.payments.mollie_not_configured'));
        }

        try {
            $molliePayment = Mollie::api()->payments->create([
                'amount' => [
                    'currency' => $payment->currency ?: 'EUR',
                    'value' => number_format((float) $payment->amount, 2, '.', ''),
                ],
                'description' => $description,
                'redirectUrl' => $redirectUrl,
                'webhookUrl' => config('payments.mollie.webhook_url') ?: route('webhooks.mollie'),
                'metadata' => [
                    'payment_id' => $payment->id,
                    'payment_reference' => $payment->payment_reference,
                    'payer_user_id' => $payer->id,
                ],
            ]);

            return [
                'checkout_url' => $molliePayment->getCheckoutUrl(),
                'provider_payment_id' => $molliePayment->id,
                'status' => $this->mapStatus((string) $molliePayment->status),
                'metadata' => [
                    'mollie_status' => $molliePayment->status,
                ],
            ];
        } catch (RequestException $e) {
            Log::warning('Mollie createPayment failed', ['error' => $e->getMessage()]);

            throw new RuntimeException(__('rml.payments.mollie_create_failed'));
        }
    }

    public function getPaymentStatus(string $providerPaymentId): array
    {
        if (! $this->isConfigured()) {
            return [
                'status' => PaymentStatus::Pending->value,
                'paid' => false,
                'cancelled' => false,
                'failed' => false,
            ];
        }

        try {
            $molliePayment = Mollie::api()->payments->get($providerPaymentId);
            $mapped = $this->mapStatus((string) $molliePayment->status);

            return [
                'status' => $mapped,
                'paid' => $mapped === PaymentStatus::Paid->value,
                'cancelled' => $mapped === PaymentStatus::Cancelled->value,
                'failed' => $mapped === PaymentStatus::Failed->value,
                'raw' => [
                    'id' => $molliePayment->id,
                    'status' => $molliePayment->status,
                ],
            ];
        } catch (RequestException $e) {
            Log::warning('Mollie getPaymentStatus failed', [
                'provider_payment_id' => $providerPaymentId,
                'error' => $e->getMessage(),
            ]);

            return [
                'status' => PaymentStatus::Pending->value,
                'paid' => false,
                'cancelled' => false,
                'failed' => false,
            ];
        }
    }

    public function handleWebhook(array $payload): array
    {
        $providerPaymentId = (string) ($payload['id'] ?? '');

        if ($providerPaymentId === '') {
            return [
                'provider_payment_id' => null,
                'status' => PaymentStatus::Pending->value,
                'paid' => false,
                'cancelled' => false,
                'failed' => false,
                'event_id' => null,
            ];
        }

        $status = $this->getPaymentStatus($providerPaymentId);

        return [
            'provider_payment_id' => $providerPaymentId,
            'status' => $status['status'],
            'paid' => $status['paid'],
            'cancelled' => $status['cancelled'],
            'failed' => $status['failed'],
            'event_id' => $providerPaymentId.':'.$status['status'],
        ];
    }

    private function mapStatus(string $mollieStatus): string
    {
        return match ($mollieStatus) {
            'paid' => PaymentStatus::Paid->value,
            'canceled', 'cancelled', 'expired' => PaymentStatus::Cancelled->value,
            'failed' => PaymentStatus::Failed->value,
            default => PaymentStatus::Pending->value,
        };
    }
}
