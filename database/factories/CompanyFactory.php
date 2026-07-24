<?php

namespace Database\Factories;

use App\Enums\ApprovalStatus;
use App\Enums\CompanyType;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    protected $model = Company::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'type' => CompanyType::Seller,
            'approval_status' => ApprovalStatus::Approved,
            'contact_name' => fake()->name(),
            'email' => fake()->companyEmail(),
            'phone' => fake()->e164PhoneNumber(),
            'whatsapp' => fake()->e164PhoneNumber(),
            'address' => fake()->streetAddress(),
            'city' => fake()->city(),
            'postcode' => fake()->postcode(),
            'country' => 'ES',
            'approved_at' => now(),
        ];
    }

    public function seller(): static
    {
        return $this->state(fn () => ['type' => CompanyType::Seller]);
    }

    public function buyer(): static
    {
        return $this->state(fn () => ['type' => CompanyType::Buyer]);
    }
}
