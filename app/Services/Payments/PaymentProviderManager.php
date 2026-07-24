<?php

namespace App\Services\Payments;

use App\Contracts\Payments\PaymentProviderInterface;
use App\Enums\PaymentMethod;
use App\Services\Payments\Providers\ManualBankTransferProvider;
use App\Services\Payments\Providers\MolliePaymentProvider;
use App\Services\Payments\Providers\StripePaymentProvider;
use InvalidArgumentException;

class PaymentProviderManager
{
    public function __construct(
        private readonly ManualBankTransferProvider $manual = new ManualBankTransferProvider,
        private readonly MolliePaymentProvider $mollie = new MolliePaymentProvider,
        private readonly StripePaymentProvider $stripe = new StripePaymentProvider,
    ) {}

    public function forMethod(PaymentMethod|string $method): PaymentProviderInterface
    {
        $value = $method instanceof PaymentMethod ? $method->value : $method;

        return match ($value) {
            PaymentMethod::ManualBankTransfer->value => $this->manual,
            PaymentMethod::Card->value => $this->card(),
            default => throw new InvalidArgumentException('Unsupported payment method: '.$value),
        };
    }

    public function byName(string $name): PaymentProviderInterface
    {
        return match ($name) {
            'manual_bank_transfer' => $this->manual,
            'mollie' => $this->mollie,
            'stripe' => $this->stripe,
            default => throw new InvalidArgumentException('Unknown payment provider: '.$name),
        };
    }

    public function card(): PaymentProviderInterface
    {
        $provider = strtolower((string) config('payments.card_provider', 'stripe'));

        return match ($provider) {
            'mollie' => $this->mollie,
            default => $this->stripe,
        };
    }

    public function cardConfigured(): bool
    {
        return $this->card()->isConfigured();
    }

    public function cardProviderName(): string
    {
        return $this->card()->providerName();
    }

    public function manual(): ManualBankTransferProvider
    {
        return $this->manual;
    }

    public function mollie(): MolliePaymentProvider
    {
        return $this->mollie;
    }

    public function stripe(): StripePaymentProvider
    {
        return $this->stripe;
    }
}
