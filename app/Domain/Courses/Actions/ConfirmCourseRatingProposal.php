<?php

namespace App\Domain\Courses\Actions;

use App\Domain\Courses\ConfirmReadTee;
use App\Domain\Courses\Exceptions\NoProposalToConfirm;
use App\Models\CourseRating;

/**
 * Confirms the tee proposed by the lookup: its rating and slope read on the SGT card become the
 * confirmed values of the pair, source sgt (spec course-ratings, « Confirmation d'une
 * proposition »; design D7).
 */
final class ConfirmCourseRatingProposal
{
    public function __construct(private readonly ConfirmReadTee $confirmReadTee) {}

    /**
     * @throws NoProposalToConfirm when the row has no tee proposed among the tees read
     */
    public function handle(CourseRating $courseRating): void
    {
        $this->confirmReadTee->handle($courseRating, null, new NoProposalToConfirm);
    }
}
