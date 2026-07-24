<?php

namespace App\Services\Payments\Providers;

use App\Contracts\Payments\PaymentProviderInterface;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\User;
use App\Services\Payments\StripeCheckoutService;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;

class StripePaymentProvider implements PaymentProviderInterface
{
    public function __construct(
        private readonly StripeCheckoutService $checkout = new StripeCheckoutService,
    ) {}

    public function providerName(): string
    {
        return 'stripe';
    }

    public function isConfigured(): bool
    {
        return $this->checkout->isConfigured();
    }

    public function createPayment(Payment $payment, User $payer, string $description, string $redirectUrl): array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException(__('rml.payments.stripe_not_configured'));
        }

        $session = $this->checkout->createCheckoutSession($payment, $payer);

        return [
            'checkout_url' => $session['checkout_url'],
            'provider_payment_id' => $session['session_id'],
            'status' => PaymentStatus::Pending->value,
            'metadata' => [
                'stripe_checkout_session_id' => $session['session_id'],
                'stripe_payment_intent_id' => $session['payment_intent_id'],
                'stripe_status' => $session['status'],
                'provider_not_configured' => false,
            ],
            'stripe_checkout_session_id' => $session['session_id'],
            'stripe_payment_intent_id' => $session['payment_intent_id'],
            'provider_status' => $session['status'],
            'provider_payload' => $session['raw'],
        ];
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
            $session = $this->checkout->retrieveSession($providerPaymentId);
            $paymentStatus = (string) ($session->payment_status ?? '');
            $status = (string) ($session->status ?? '');

            $paid = $paymentStatus === 'paid';
            $cancelled = in_array($status, ['expired'], true);
            $failed = false;

            $mapped = match (true) {
                $paid => PaymentStatus::Paid->value,
                $cancelled => PaymentStatus::Cancelled->value,
                default => PaymentStatus::Pending->value,
            };

            $paymentIntentId = null;
            if (is_string($session->payment_intent)) {
                $paymentIntentId = $session->payment_intent;
            } elseif (is_object($session->payment_intent) && isset($session->payment_intent->id)) {
                $paymentIntentId = (string) $session->payment_intent->id;
            }

            return [
                'status' => $mapped,
                'paid' => $paid,
                'cancelled' => $cancelled,
                'failed' => $failed,
                'payment_intent_id' => $paymentIntentId,
                'amount_total' => $session->amount_total,
                'currency' => $session->currency,
                'raw' => $session->toArray(),
            ];
        } catch (ApiErrorException $e) {
            Log::warning('Stripe getPaymentStatus failed', [
                'session_id' => $providerPaymentId,
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

    /**
     * @param  array{payload?: string, signature?: string}|array<string, mixed>  $payload
     * @return array{provider_payment_id: string|null, status: string, paid: bool, cancelled: bool, failed: bool, event_id?: string|null, event_type?: string|null, payment_intent_id?: string|null, amount_total?: int|null, currency?: string|null, metadata?: array<string, mixed>, raw?: array<string, mixed>}
     */
    public function handleWebhook(array $payload): array
    {
        $rawPayload = (string) ($payload['payload'] ?? '');
        $signature = (string) ($payload['signature'] ?? '');
        $secret = (string) (config('services.stripe.webhook_secret') ?: config('payments.stripe.webhook_secret', ''));

        if ($rawPayload === '' || $signature === '' || $secret === '') {
            return $this->emptyWebhookResult();
        }

        try {
            $event = Webhook::constructEvent($rawPayload, $signature, $secret);
        } catch (SignatureVerificationException|\UnexpectedValueException $e) {
            Log::warning('Stripe webhook signature verification failed', [
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        $type = (string) $event->type;
        $object = $event->data->object;
        $objectArray = is_object($object) && method_exists($object, 'toArray')
            ? $object->toArray()
            : (array) $object;

        return match ($type) {
            'checkout.session.completed' => $this->mapCheckoutSession($objectArray, $event->id, $type, paidHint: true),
            'checkout.session.expired' => $this->mapCheckoutSession($objectArray, $event->id, $type, cancelledHint: true),
            'payment_intent.succeeded' => $this->mapPaymentIntent($objectArray, $event->id, $type, paidHint: true),
            'payment_intent.payment_failed' => $this->mapPaymentIntent($objectArray, $event->id, $type, failedHint: true),
            default => [
                ...$this->emptyWebhookResult(),
                'event_id' => $event->id,
                'event_type' => $type,
                'raw' => $objectArray,
            ],
        };
    }

    /**
     * @param  array<string, mixed>  $session
     * @return array<string, mixed>
     */
    private function mapCheckoutSession(
        array $session,
        string $eventId,
        string $eventType,
        bool $paidHint = false,
        bool $cancelledHint = false,
    ): array {
        $paymentStatus = (string) ($session['payment_status'] ?? '');
        $status = (string) ($session['status'] ?? '');
        $paid = $paidHint && $paymentStatus === 'paid';
        $cancelled = $cancelledHint || $status === 'expired';

        $mapped = match (true) {
            $paid => PaymentStatus::Paid->value,
            $cancelled => PaymentStatus::Cancelled->value,
            default => PaymentStatus::Pending->value,
        };

        $paymentIntent = $session['payment_intent'] ?? null;
        $paymentIntentId = is_string($paymentIntent)
            ? $paymentIntent
            : (is_array($paymentIntent) ? ($paymentIntent['id'] ?? null) : null);

        return [
            'provider_payment_id' => $session['id'] ?? null,
            'status' => $mapped,
            'paid' => $paid,
            'cancelled' => $cancelled && ! $paid,
            'failed' => false,
            'event_id' => $eventId,
            'event_type' => $eventType,
            'payment_intent_id' => $paymentIntentId,
            'amount_total' => isset($session['amount_total']) ? (int) $session['amount_total'] : null,
            'currency' => isset($session['currency']) ? (string) $session['currency'] : null,
            'metadata' => is_array($session['metadata'] ?? null) ? $session['metadata'] : [],
            'raw' => $session,
        ];
    }

    /**
     * @param  array<string, mixed>  $intent
     * @return array<string, mixed>
     */
    private function mapPaymentIntent(
        array $intent,
        string $eventId,
        string $eventType,
        bool $paidHint = false,
        bool $failedHint = false,
    ): array {
        $status = (string) ($intent['status'] ?? '');
        $paid = $paidHint && $status === 'succeeded';
        $failed = $failedHint || in_array($status, ['canceled', 'requires_payment_method'], true);

        $mapped = match (true) {
            $paid => PaymentStatus::Paid->value,
            $failed => PaymentStatus::Failed->value,
            default => PaymentStatus::Pending->value,
        };

        return [
            'provider_payment_id' => null,
            'status' => $mapped,
            'paid' => $paid,
            'cancelled' => false,
            'failed' => $failed && ! $paid,
            'event_id' => $eventId,
            'event_type' => $eventType,
            'payment_intent_id' => $intent['id'] ?? null,
            'amount_total' => isset($intent['amount_received'])
                ? (int) $intent['amount_received']
                : (isset($intent['amount']) ? (int) $intent['amount'] : null),
            'currency' => isset($intent['currency']) ? (string) $intent['currency'] : null,
            'metadata' => is_array($intent['metadata'] ?? null) ? $intent['metadata'] : [],
            'raw' => $intent,
        ];
    }

    /**
     * @return array{provider_payment_id: null, status: string, paid: false, cancelled: false, failed: false, event_id: null}
     */
    private function emptyWebhookResult(): array
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
}
