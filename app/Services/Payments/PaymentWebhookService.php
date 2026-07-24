<?php

namespace App\Services\Payments;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\PaymentWebhookEvent;
use App\Services\AuditLogService;
use App\Services\Payments\Providers\StripePaymentProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\SignatureVerificationException;

class PaymentWebhookService
{
    public function __construct(
        private readonly PaymentProviderManager $providers = new PaymentProviderManager,
        private readonly PaymentSettlementService $settlementService = new PaymentSettlementService,
        private readonly AuditLogService $auditLogService = new AuditLogService,
        private readonly StripeCheckoutService $stripeCheckout = new StripeCheckoutService,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handleMollie(array $payload): void
    {
        $provider = $this->providers->mollie();
        $result = $provider->handleWebhook($payload);

        $providerPaymentId = $result['provider_payment_id'] ?? null;
        $eventId = $result['event_id'] ?? ($providerPaymentId.':'.($result['status'] ?? 'unknown'));

        if (! $providerPaymentId) {
            Log::info('Mollie webhook ignored — missing payment id', ['payload' => $payload]);

            return;
        }

        DB::transaction(function () use ($provider, $providerPaymentId, $eventId, $payload, $result) {
            $existing = PaymentWebhookEvent::query()
                ->where('provider', $provider->providerName())
                ->where('provider_event_id', $eventId)
                ->lockForUpdate()
                ->first();

            if ($existing?->processed_at) {
                return;
            }

            $event = $existing ?? PaymentWebhookEvent::query()->create([
                'provider' => $provider->providerName(),
                'provider_event_id' => $eventId,
                'provider_payment_id' => $providerPaymentId,
                'event_type' => 'payment.status',
                'status' => $result['status'] ?? null,
                'payload' => $payload,
            ]);

            $payment = Payment::query()
                ->where('provider_payment_id', $providerPaymentId)
                ->lockForUpdate()
                ->first();

            if (! $payment) {
                $event->update(['processed_at' => now()]);

                return;
            }

            if ($payment->status === PaymentStatus::Paid && ($result['paid'] ?? false)) {
                $event->update(['processed_at' => now(), 'status' => PaymentStatus::Paid->value]);

                return;
            }

            if ($result['paid'] ?? false) {
                $this->settlementService->confirmPaid($payment, null, [
                    'confirmed_via' => 'mollie_webhook',
                ]);
            } elseif ($result['failed'] ?? false) {
                $this->settlementService->markFailed($payment, null, ['keep_purchase' => true]);
            } elseif ($result['cancelled'] ?? false) {
                if ($payment->status === PaymentStatus::Pending) {
                    $this->settlementService->markCancelled($payment, null, ['keep_purchase' => true]);
                }
            }

            $event->update([
                'processed_at' => now(),
                'status' => $result['status'] ?? null,
                'payload' => $payload,
            ]);

            $this->auditLogService->log(
                'payment.webhook_processed',
                $payment,
                null,
                [
                    'provider' => 'mollie',
                    'provider_payment_id' => $providerPaymentId,
                    'status' => $result['status'] ?? null,
                    'event_id' => $eventId,
                ],
            );
        });
    }

    /**
     * @param  array{payload: string, signature: string}  $signed
     *
     * @throws SignatureVerificationException
     */
    public function handleStripe(array $signed): void
    {
        /** @var StripePaymentProvider $provider */
        $provider = $this->providers->stripe();
        $result = $provider->handleWebhook($signed);

        $eventId = $result['event_id'] ?? null;
        $eventType = $result['event_type'] ?? 'unknown';

        if (! $eventId) {
            Log::info('Stripe webhook ignored — missing event id');

            return;
        }

        // Ignore unknown event types after signature verification.
        if (! in_array($eventType, [
            'checkout.session.completed',
            'checkout.session.expired',
            'payment_intent.succeeded',
            'payment_intent.payment_failed',
        ], true)) {
            Log::info('Stripe webhook ignored — unhandled event type', ['type' => $eventType]);

            return;
        }

        DB::transaction(function () use ($provider, $result, $eventId, $eventType) {
            $existing = PaymentWebhookEvent::query()
                ->where('provider', $provider->providerName())
                ->where('provider_event_id', $eventId)
                ->lockForUpdate()
                ->first();

            if ($existing?->processed_at) {
                return;
            }

            $sessionId = $result['provider_payment_id'] ?? null;
            $paymentIntentId = $result['payment_intent_id'] ?? null;
            $metadata = is_array($result['metadata'] ?? null) ? $result['metadata'] : [];

            $event = $existing ?? PaymentWebhookEvent::query()->create([
                'provider' => $provider->providerName(),
                'provider_event_id' => $eventId,
                'provider_payment_id' => $sessionId ?: $paymentIntentId,
                'event_type' => $eventType,
                'status' => $result['status'] ?? null,
                'payload' => [
                    'event_type' => $eventType,
                    'session_id' => $sessionId,
                    'payment_intent_id' => $paymentIntentId,
                    'metadata' => $metadata,
                ],
            ]);

            $payment = $this->findStripePayment($sessionId, $paymentIntentId, $metadata);

            if (! $payment) {
                Log::warning('Stripe webhook payment not found', [
                    'event_id' => $eventId,
                    'session_id' => $sessionId,
                    'payment_intent_id' => $paymentIntentId,
                ]);
                $event->update(['processed_at' => now()]);

                return;
            }

            $payment = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($payment->status === PaymentStatus::Paid && ($result['paid'] ?? false)) {
                $event->update(['processed_at' => now(), 'status' => PaymentStatus::Paid->value]);

                return;
            }

            if ($result['paid'] ?? false) {
                $this->assertStripeAmountMatches($payment, $result);

                $payment->update([
                    'provider' => 'stripe',
                    'provider_payment_id' => $sessionId ?: $payment->provider_payment_id,
                    'provider_reference' => $sessionId ?: $payment->provider_reference,
                    'stripe_checkout_session_id' => $sessionId ?: $payment->stripe_checkout_session_id,
                    'stripe_payment_intent_id' => $paymentIntentId ?: $payment->stripe_payment_intent_id,
                    'provider_status' => $result['status'] ?? 'paid',
                    'provider_payload' => $result['raw'] ?? $payment->provider_payload,
                ]);

                $this->settlementService->confirmPaid($payment->fresh() ?? $payment, null, [
                    'confirmed_via' => 'stripe_webhook',
                    'confirmation_reference' => $paymentIntentId ?: $sessionId,
                ]);
            } elseif ($result['failed'] ?? false) {
                $payment->update([
                    'stripe_payment_intent_id' => $paymentIntentId ?: $payment->stripe_payment_intent_id,
                    'provider_status' => $result['status'] ?? 'failed',
                    'provider_payload' => $result['raw'] ?? $payment->provider_payload,
                ]);
                $this->settlementService->markFailed($payment->fresh() ?? $payment, null, [
                    'keep_purchase' => true,
                ]);
            } elseif ($result['cancelled'] ?? false) {
                $payment->update([
                    'stripe_checkout_session_id' => $sessionId ?: $payment->stripe_checkout_session_id,
                    'provider_status' => $result['status'] ?? 'expired',
                    'provider_payload' => $result['raw'] ?? $payment->provider_payload,
                ]);
                // Keep purchase pending so the buyer can retry checkout.
                if ($payment->status === PaymentStatus::Pending) {
                    $this->settlementService->markCancelled($payment->fresh() ?? $payment, null, [
                        'keep_purchase' => true,
                    ]);
                }
            }

            $event->update([
                'processed_at' => now(),
                'status' => $result['status'] ?? null,
                'provider_payment_id' => $sessionId ?: $paymentIntentId,
            ]);

            $this->auditLogService->log(
                'payment.webhook_processed',
                $payment,
                null,
                [
                    'provider' => 'stripe',
                    'event_type' => $eventType,
                    'session_id' => $sessionId,
                    'payment_intent_id' => $paymentIntentId,
                    'status' => $result['status'] ?? null,
                    'event_id' => $eventId,
                ],
            );
        });
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function findStripePayment(?string $sessionId, ?string $paymentIntentId, array $metadata): ?Payment
    {
        if (! empty($metadata['payment_id'])) {
            $byId = Payment::query()->find((int) $metadata['payment_id']);
            if ($byId) {
                return $byId;
            }
        }

        if ($sessionId) {
            $bySession = Payment::query()
                ->where(function ($q) use ($sessionId) {
                    $q->where('stripe_checkout_session_id', $sessionId)
                        ->orWhere('provider_payment_id', $sessionId);
                })
                ->first();
            if ($bySession) {
                return $bySession;
            }
        }

        if ($paymentIntentId) {
            return Payment::query()
                ->where('stripe_payment_intent_id', $paymentIntentId)
                ->first();
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function assertStripeAmountMatches(Payment $payment, array $result): void
    {
        $expectedCents = $this->stripeCheckout->amountToCents((float) $payment->amount);
        $actualCents = isset($result['amount_total']) ? (int) $result['amount_total'] : null;
        $currency = strtolower((string) ($result['currency'] ?? ''));
        $expectedCurrency = strtolower((string) (
            config('services.stripe.currency')
            ?: $payment->currency
            ?: 'eur'
        ));

        if ($actualCents !== null && $actualCents !== $expectedCents) {
            Log::error('Stripe webhook amount mismatch', [
                'payment_id' => $payment->id,
                'expected_cents' => $expectedCents,
                'actual_cents' => $actualCents,
            ]);

            throw new \RuntimeException('Stripe payment amount mismatch.');
        }

        if ($currency !== '' && $currency !== $expectedCurrency) {
            Log::error('Stripe webhook currency mismatch', [
                'payment_id' => $payment->id,
                'expected' => $expectedCurrency,
                'actual' => $currency,
            ]);

            throw new \RuntimeException('Stripe payment currency mismatch.');
        }
    }
}
