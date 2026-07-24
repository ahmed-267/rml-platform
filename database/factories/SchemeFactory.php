<?php

namespace Database\Factories;

use App\Models\Scheme;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Scheme>
 */
class SchemeFactory extends Factory
{
    protected $model = Scheme::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name' => Str::title($name),
            'slug' => Str::slug($name),
            'description' => fake()->sentence(),
            'active' => true,
            'sort_order' => 0,
        ];
    }
}
