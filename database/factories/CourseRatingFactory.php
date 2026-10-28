<?php

namespace Database\Factories;

use App\Models\CourseRating;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Written as is, without recomputing the history: for the tests that read the ratings.
 *
 * @extends Factory<CourseRating>
 */
class CourseRatingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'course_name' => mb_strtoupper(fake()->unique()->city()).' GOLF CLUB',
            'tees' => fake()->randomElement(['Red', 'White', 'Blue']),
            'rating_t' => fake()->numberBetween(680, 740),
            'slope' => fake()->numberBetween(110, 145),
        ];
    }

    /**
     * BETHPAGE BLACK / Red, the course of the reference card: 71,4 / 144.
     */
    public function bethpageRed(): static
    {
        return $this->state(fn (): array => [
            'course_name' => 'BETHPAGE BLACK',
            'tees' => 'Red',
            'rating_t' => 714,
            'slope' => 144,
        ]);
    }

    /**
     * The lowest values accepted: 40,0 / 55.
     */
    public function bounds(): static
    {
        return $this->state(fn (): array => ['rating_t' => 400, 'slope' => 55]);
    }
}
