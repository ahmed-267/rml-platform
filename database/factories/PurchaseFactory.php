<?php

namespace Database\Factories;

use App\Enums\PurchaseStatus;
use App\Models\Company;
use App\Models\Purchase;
use App\Models\User;
use App\Support\ReferenceGenerator;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Purchase>
 */
class PurchaseFactory extends Factory
{
    protected $model = Purchase::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'purchase_reference' => fn () => ReferenceGenerator::purchase(),
            'buyer_company_id' => Company::factory()->buyer(),
            'buyer_user_id' => User::factory(),
            'status' => PurchaseStatus::Pending,
            'total_amount' => fake()->randomFloat(2, 200, 4000),
            'total_size_m2' => fake()->randomFloat(2, 50, 400),
            'payment_id' => null,
            'purchased_at' => null,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn () => [
            'status' => PurchaseStatus::Paid,
            'purchased_at' => now(),
        ]);
    }
}
