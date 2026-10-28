<?php

namespace Database\Factories;

use App\Models\Round;
use App\Models\RoundHole;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RoundHole>
 */
class RoundHoleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'round_id' => Round::factory(),
            'hole' => 1,
            'par' => fake()->numberBetween(3, 5),
            'stroke_index' => 1,
            'distance' => fake()->numberBetween(100, 550),
        ];
    }
}
