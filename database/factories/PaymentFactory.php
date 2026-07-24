<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Models\Payment;
use App\Support\ReferenceGenerator;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'payment_reference' => fn () => ReferenceGenerator::payment(),
            'payer_user_id' => null,
            'payer_company_id' => null,
            'type' => PaymentType::BuyerPayment,
            'method' => PaymentMethod::Card,
            'provider' => null,
            'provider_payment_id' => null,
            'status' => PaymentStatus::Pending,
            'amount' => fake()->randomFloat(2, 100, 5000),
            'currency' => 'EUR',
            'due_date' => now()->addDays(7)->toDateString(),
            'paid_at' => null,
            'metadata' => null,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn () => [
            'status' => PaymentStatus::Paid,
            'paid_at' => now(),
        ]);
    }
}
