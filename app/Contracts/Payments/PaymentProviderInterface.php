<?php

namespace App\Contracts\Payments;

use App\Models\Payment;
use App\Models\User;

interface PaymentProviderInterface
{
    public function providerName(): string;

    public function isConfigured(): bool;

    /**
     * @return array{checkout_url: string|null, provider_payment_id: string|null, status: string, metadata?: array<string, mixed>}
     */
    public function createPayment(Payment $payment, User $payer, string $description, string $redirectUrl): array;

    /**
     * @return array{status: string, paid: bool, cancelled: bool, failed: bool, raw?: array<string, mixed>}
     */
    public function getPaymentStatus(string $providerPaymentId): array;

    /**
     * @param  array<string, mixed>  $payload
     * @return array{provider_payment_id: string|null, status: string, paid: bool, cancelled: bool, failed: bool, event_id?: string|null}
     */
    public function handleWebhook(array $payload): array;
}
