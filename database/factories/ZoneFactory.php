<?php

namespace Database\Factories;

use App\Models\Scheme;
use App\Models\Zone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Zone>
 */
class ZoneFactory extends Factory
{
    protected $model = Zone::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $code = strtoupper(fake()->unique()->bothify('??#'));

        return [
            'scheme_id' => Scheme::factory(),
            'code' => $code,
            'name' => 'Zone '.$code,
            'description' => fake()->sentence(),
            'active' => true,
            'sort_order' => 0,
        ];
    }
}
