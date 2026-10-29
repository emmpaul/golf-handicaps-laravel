<?php

namespace Database\Factories;

use App\Domain\Courses\CourseRatingStatus;
use App\Models\CourseRating;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Written as is, without recomputing the history: for the tests that read the ratings. The
 * definition is a rating typed by hand, confirmed.
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
            'source' => 'manual',
            'status' => CourseRatingStatus::Confirmed,
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

    /**
     * Looked up on Simulator Golf Tour, the job not done yet.
     */
    public function pending(): static
    {
        return $this->lookup(CourseRatingStatus::Pending);
    }

    /**
     * BETHPAGE BLACK / Red, found on the SGT course 30 whose card proposes the tee Red at
     * 71,4 / 144, not confirmed.
     */
    public function proposed(): static
    {
        return $this->lookup(CourseRatingStatus::Proposed, [
            'course_name' => 'BETHPAGE BLACK',
            'tees' => 'Red',
            ...self::bethpageBlackCard(),
            'proposed_tee' => 'Red',
        ]);
    }

    /**
     * SANCTUARY COVE / Blue, with the SGT courses 477 and 1951 of the same par to choose from.
     */
    public function chooseCourse(): static
    {
        return $this->lookup(CourseRatingStatus::ChooseCourse, [
            'course_name' => 'SANCTUARY COVE',
            'tees' => 'Blue',
            'candidates' => [
                ['id' => 477, 'name' => 'Sanctuary', 'location' => 'Sedalia, CO', 'par' => 72],
                ['id' => 1951, 'name' => 'The Pines Sanctuary Cove', 'location' => 'Hope Island, Queensland, Australia', 'par' => 72],
            ],
        ]);
    }

    /**
     * BETHPAGE BLACK / Gold, a tee absent from the card of the SGT course 30: none proposed.
     */
    public function chooseTee(): static
    {
        return $this->lookup(CourseRatingStatus::ChooseTee, [
            'course_name' => 'BETHPAGE BLACK',
            'tees' => 'Gold',
            ...self::bethpageBlackCard(),
        ]);
    }

    /**
     * Left to a rating typed by hand, for the reason of the note.
     */
    public function manual(string $note): static
    {
        return $this->lookup(CourseRatingStatus::Manual, ['note' => $note]);
    }

    public function notFound(): static
    {
        return $this->lookup(CourseRatingStatus::NotFound);
    }

    /**
     * Both attempts of the job failed to reach Simulator Golf Tour.
     */
    public function failed(): static
    {
        return $this->lookup(CourseRatingStatus::Failed, ['note' => 'SGT injoignable']);
    }

    /**
     * The proposal of BETHPAGE BLACK / Red confirmed: 71,4 / 144 from SGT.
     */
    public function confirmedFromSgt(): static
    {
        return $this->proposed()->state([
            'rating_t' => 714,
            'slope' => 144,
            'source' => 'sgt',
            'status' => CourseRatingStatus::Confirmed,
        ]);
    }

    /**
     * A state of the lookup: no confirmed rating yet.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function lookup(CourseRatingStatus $status, array $attributes = []): static
    {
        return $this->state([
            'rating_t' => null,
            'slope' => null,
            'source' => null,
            'status' => $status,
            ...$attributes,
        ]);
    }

    /**
     * The SGT course 30 and the five tees read on its card scorecard_30.jpg, ratings in tenths.
     *
     * @return array<string, mixed>
     */
    private static function bethpageBlackCard(): array
    {
        return [
            'sgt_course_id' => 30,
            'sgt_course_name' => 'Bethpage Black',
            'sgt_course_location' => 'Farmingdale, NY',
            'tees_read' => [
                ['name' => 'Blue', 'ratingT' => 775, 'slope' => 155],
                ['name' => 'White', 'ratingT' => 742, 'slope' => 148],
                ['name' => 'Red', 'ratingT' => 714, 'slope' => 144],
                ['name' => 'Junior', 'ratingT' => 710, 'slope' => 120],
                ['name' => 'PAR3', 'ratingT' => 540, 'slope' => 120],
            ],
        ];
    }
}
