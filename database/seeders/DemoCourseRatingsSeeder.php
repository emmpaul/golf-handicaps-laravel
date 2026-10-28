<?php

namespace Database\Seeders;

use App\Domain\Courses\Actions\SaveCourseRating;

/**
 * The course rating of the demonstration base: 71,4 / 144 on BETHPAGE BLACK / Red, typed through
 * SaveCourseRating so that the history of its players is recomputed. PAYNES VALLEY / Blue and
 * CALA NOVA / White stay without rating, to show both cases. Runs after DemoRoundsSeeder: a
 * course without round is refused.
 */
class DemoCourseRatingsSeeder extends DemoSeeder
{
    public function run(SaveCourseRating $saveCourseRating): void
    {
        $saveCourseRating->handle('BETHPAGE BLACK', 'Red', 714, 144);
    }
}
