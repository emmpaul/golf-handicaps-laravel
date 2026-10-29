<?php

namespace Database\Seeders;

use App\Domain\Courses\Actions\SaveCourseRating;

/**
 * The confirmed rating of the demonstration base: 71,4 / 144 on BETHPAGE BLACK / Red, written
 * through an Action so that the history of its players is recomputed. Runs after DemoRoundsSeeder:
 * a course without round is refused.
 *
 * Until ConfirmCourseRatingProposal exists (task 7.1 of course-rating-sgt), the rating is typed
 * through SaveCourseRating; task 9.1 confirms the SGT proposal instead.
 */
class DemoConfirmedRatingSeeder extends DemoSeeder
{
    public function run(SaveCourseRating $saveCourseRating): void
    {
        $saveCourseRating->handle('BETHPAGE BLACK', 'Red', 714, 144);
    }
}
