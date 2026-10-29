<?php

namespace Database\Seeders;

use App\Domain\Courses\CourseRatingStatus;
use App\Models\CourseRating;

/**
 * The lookups of the demonstration base, written before its rounds so that CreateRound finds a row
 * for each course and tees and queues no lookup (design D12 of course-rating-sgt): BETHPAGE BLACK /
 * Red proposed from the SGT course 30, PAYNES VALLEY / Blue proposed from the SGT course 573,
 * CALA NOVA / White not found on SGT. No request reaches Simulator Golf Tour.
 */
class DemoCourseRatingsSeeder extends DemoSeeder
{
    public function run(): void
    {
        CourseRating::factory()->proposed()->create();

        // The name, the place and the par of the course 573 come from the SGT list; the tee Blue
        // at 72,9 / 131 is made up for the demonstration, not read on its card.
        CourseRating::factory()->state([
            'course_name' => 'PAYNES VALLEY',
            'tees' => 'Blue',
            'rating_t' => null,
            'slope' => null,
            'source' => null,
            'status' => CourseRatingStatus::Proposed,
            'sgt_course_id' => 573,
            'sgt_course_name' => 'Paynes Valley',
            'sgt_course_location' => 'Hollister, MO',
            'tees_read' => [['name' => 'Blue', 'ratingT' => 729, 'slope' => 131]],
            'proposed_tee' => 'Blue',
        ])->create();

        CourseRating::factory()->notFound()->create(['course_name' => 'CALA NOVA', 'tees' => 'White']);
    }
}
