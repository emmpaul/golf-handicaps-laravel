<?php

namespace App\Domain\Courses\Actions;

use App\Domain\Courses\ConfirmReadTee;
use App\Domain\Courses\Exceptions\TeeNotRead;
use App\Models\CourseRating;

/**
 * Retains one of the tees read on the SGT card, by its exact name: its rating and slope become
 * the confirmed values of the pair, source sgt, and it becomes the tee retained (spec
 * course-ratings, « Choix d'un tee lu »; design D7).
 */
final class ChooseCourseRatingTee
{
    public function __construct(private readonly ConfirmReadTee $confirmReadTee) {}

    /**
     * @throws TeeNotRead when no tee read has that exact name
     */
    public function handle(CourseRating $courseRating, string $tee): void
    {
        $this->confirmReadTee->handle($courseRating, $tee, new TeeNotRead);
    }
}
