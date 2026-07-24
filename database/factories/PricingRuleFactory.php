<?php

namespace Database\Factories;

use App\Models\PricingRule;
use App\Models\Scheme;
use App\Models\Zone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PricingRule>
 */
class PricingRuleFactory extends Factory
{
    protected $model = PricingRule::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'scheme_id' => Scheme::factory(),
            'zone_id' => Zone::factory(),
            'price_per_m2' => fake()->randomFloat(2, 2, 8),
            'basic_price' => 3.00,
            'zone_factor' => 1.5,
            'size_factor' => 1.25,
            'distance_factor' => 1.0,
            'active' => true,
            'effective_from' => now()->toDateString(),
        ];
    }
}
