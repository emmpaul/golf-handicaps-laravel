<?php

namespace Database\Seeders;

use App\Domain\Courses\Actions\ConfirmCourseRatingProposal;
use App\Models\CourseRating;

/**
 * The confirmed rating of the demonstration base: the SGT proposal of BETHPAGE BLACK / Red (tee
 * Red, 71,4 / 144, written by DemoCourseRatingsSeeder) confirmed through
 * ConfirmCourseRatingProposal, so that the history of its players is recomputed (design D12 of
 * course-rating-sgt). Runs after DemoRoundsSeeder: the recomputation needs the rounds.
 */
class DemoConfirmedRatingSeeder extends DemoSeeder
{
    public function run(ConfirmCourseRatingProposal $confirmProposal): void
    {
        $confirmProposal->handle(
            CourseRating::query()->where('course_name', 'BETHPAGE BLACK')->where('tees', 'Red')->sole(),
        );
    }
}
