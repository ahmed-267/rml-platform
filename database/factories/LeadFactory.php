<?php

namespace Database\Factories;

use App\Enums\LeadStatus;
use App\Models\Lead;
use App\Models\Scheme;
use App\Models\User;
use App\Models\Zone;
use App\Support\ReferenceGenerator;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lead>
 */
class LeadFactory extends Factory
{
    protected $model = Lead::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'lead_reference' => fn () => ReferenceGenerator::lead(),
            'submitted_by_user_id' => User::factory(),
            'seller_company_id' => null,
            'scheme_id' => Scheme::factory(),
            'zone_id' => Zone::factory(),
            'status' => LeadStatus::Draft,
            'customer_first_name' => fake()->firstName(),
            'customer_last_name' => fake()->lastName(),
            'customer_phone' => fake()->e164PhoneNumber(),
            'customer_whatsapp' => fake()->e164PhoneNumber(),
            'customer_email' => fake()->safeEmail(),
            'address_line_1' => fake()->streetAddress(),
            'address_line_2' => null,
            'city' => fake()->city(),
            'postcode' => fake()->postcode(),
            'country' => 'ES',
            'property_type' => 'detached',
            'epc_rating' => fake()->randomElement(['D', 'E', 'F', 'G']),
            'size_m2' => fake()->randomFloat(2, 40, 180),
            'distance_km' => fake()->randomFloat(2, 5, 80),
        ];
    }

    public function listed(): static
    {
        return $this->state(fn () => [
            'status' => LeadStatus::Listed,
            'listed_at' => now(),
            'accepted_at' => now()->subDay(),
            'selling_price' => 450.00,
            'buying_price' => 300.00,
            'expected_margin' => 150.00,
        ]);
    }

    public function sold(): static
    {
        return $this->state(fn () => [
            'status' => LeadStatus::Sold,
            'listed_at' => now()->subDays(3),
            'sold_at' => now(),
            'accepted_at' => now()->subDays(5),
            'selling_price' => 520.00,
            'buying_price' => 340.00,
            'expected_margin' => 180.00,
        ]);
    }
}
