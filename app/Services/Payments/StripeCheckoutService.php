<?php

namespace App\Services\Payments;

use App\Enums\LeadStatus;
use App\Enums\PaymentStatus;
use App\Enums\PurchaseItemType;
use App\Enums\PurchaseStatus;
use App\Models\Payment;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Stripe\Checkout\Session;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

class StripeCheckoutService
{
    public function isConfigured(): bool
    {
        $secret = (string) (config('services.stripe.secret') ?: config('payments.stripe.secret', ''));

        if ($secret === '' || str_contains($secret, 'your-') || str_contains($secret, 'xxxxxxxx')) {
            return false;
        }

        return (bool) preg_match('/^sk_(live|test)_[A-Za-z0-9]+$/', $secret);
    }

    public function client(): StripeClient
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException(__('rml.payments.stripe_not_configured'));
        }

        return new StripeClient((string) (config('services.stripe.secret') ?: config('payments.stripe.secret')));
    }

    /**
     * @return array{checkout_url: string, session_id: string, payment_intent_id: string|null, status: string, raw: array<string, mixed>}
     */
    public function createCheckoutSession(Payment $payment, User $payer): array
    {
        if ($payment->status === PaymentStatus::Paid) {
            throw ValidationException::withMessages([
                'payment' => __('rml.payments.already_paid'),
            ]);
        }

        if (! in_array($payment->status, [PaymentStatus::Pending, PaymentStatus::Failed, PaymentStatus::Cancelled], true)) {
            throw ValidationException::withMessages([
                'payment' => __('rml.payments.not_payable'),
            ]);
        }

        $payment->loadMissing(['purchases.items.lead', 'purchases.items.leadPackage']);

        $this->assertPurchasePayable($payment);

        $currency = strtolower((string) (
            config('services.stripe.currency')
            ?: config('payments.stripe.currency')
            ?: $payment->currency
            ?: 'eur'
        ));

        $amountCents = $this->amountToCents((float) $payment->amount);

        if ($amountCents < 50) {
            throw ValidationException::withMessages([
                'payment' => __('rml.payments.amount_too_low'),
            ]);
        }

        $lineItems = $this->buildLineItems($payment, $currency, $amountCents);
        $metadata = $this->buildMetadata($payment, $payer);

        $successUrl = route('buyer.payments.success', $payment).'?session_id={CHECKOUT_SESSION_ID}';
        $cancelUrl = route('buyer.payments.cancelled', $payment);

        try {
            $session = $this->client()->checkout->sessions->create([
                'mode' => 'payment',
                'payment_method_types' => ['card'],
                'line_items' => $lineItems,
                'success_url' => $successUrl,
                'cancel_url' => $cancelUrl,
                'client_reference_id' => $payment->payment_reference,
                'customer_email' => $payer->email ?: null,
                'metadata' => $metadata,
                'payment_intent_data' => [
                    'metadata' => $metadata,
                ],
            ]);
        } catch (ApiErrorException $e) {
            Log::warning('Stripe Checkout Session create failed', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);

            throw new RuntimeException(__('rml.payments.stripe_create_failed'));
        }

        if (! $session->url) {
            throw new RuntimeException(__('rml.payments.stripe_create_failed'));
        }

        return [
            'checkout_url' => $session->url,
            'session_id' => $session->id,
            'payment_intent_id' => is_string($session->payment_intent) ? $session->payment_intent : null,
            'status' => (string) ($session->status ?? 'open'),
            'raw' => $session->toArray(),
        ];
    }

    public function retrieveSession(string $sessionId): Session
    {
        return $this->client()->checkout->sessions->retrieve($sessionId, [
            'expand' => ['payment_intent'],
        ]);
    }

    public function amountToCents(float $amount): int
    {
        return (int) round($amount * 100);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildLineItems(Payment $payment, string $currency, int $amountCents): array
    {
        $purchase = $payment->purchases->first();
        $name = $this->lineItemName($purchase);

        return [[
            'quantity' => 1,
            'price_data' => [
                'currency' => $currency,
                'unit_amount' => $amountCents,
                'product_data' => [
                    'name' => $name,
                    'metadata' => [
                        'payment_reference' => $payment->payment_reference,
                    ],
                ],
            ],
        ]];
    }

    private function lineItemName(?Purchase $purchase): string
    {
        if (! $purchase) {
            return (string) __('rml.payments.stripe_line_purchase');
        }

        $item = $purchase->items->first();
        if ($item?->leadPackage?->package_reference) {
            return (string) __('rml.payments.stripe_line_package', [
                'reference' => $item->leadPackage->package_reference,
            ]);
        }

        if ($item?->item_type === PurchaseItemType::Package && $purchase->items->count() > 1) {
            return (string) __('rml.payments.stripe_line_package', [
                'reference' => $purchase->purchase_reference,
            ]);
        }

        if ($item?->lead?->lead_reference) {
            if ($purchase->items->count() === 1) {
                return (string) __('rml.payments.stripe_line_lead', [
                    'reference' => $item->lead->lead_reference,
                ]);
            }

            return (string) __('rml.payments.stripe_line_leads', [
                'reference' => $purchase->purchase_reference,
            ]);
        }

        return (string) __('rml.payments.stripe_line_purchase');
    }

    /**
     * @return array<string, string>
     */
    private function buildMetadata(Payment $payment, User $payer): array
    {
        $purchase = $payment->purchases->first();
        $item = $purchase?->items->first();

        return array_filter([
            'payment_id' => (string) $payment->id,
            'payment_reference' => (string) $payment->payment_reference,
            'purchase_id' => $purchase ? (string) $purchase->id : null,
            'purchase_reference' => $purchase?->purchase_reference,
            'buyer_user_id' => (string) $payer->id,
            'buyer_company_id' => $payment->payer_company_id ? (string) $payment->payer_company_id : null,
            'lead_id' => $item?->lead_id && $purchase?->items->count() === 1 ? (string) $item->lead_id : null,
            'package_id' => $item?->lead_package_id ? (string) $item->lead_package_id : null,
        ], fn ($value) => $value !== null && $value !== '');
    }

    private function assertPurchasePayable(Payment $payment): void
    {
        foreach ($payment->purchases as $purchase) {
            foreach ($purchase->items as $item) {
                $lead = $item->lead;
                if (! $lead) {
                    continue;
                }

                if ($lead->status !== LeadStatus::Sold) {
                    continue;
                }

                $soldToOtherBuyer = PurchaseItem::query()
                    ->where('lead_id', $lead->id)
                    ->where('purchase_id', '!=', $purchase->id)
                    ->whereHas('purchase', fn ($q) => $q->where('status', PurchaseStatus::Paid->value))
                    ->exists();

                if ($soldToOtherBuyer) {
                    throw ValidationException::withMessages([
                        'payment' => __('rml.buyer.leads.already_sold'),
                    ]);
                }
            }
        }
    }
}
