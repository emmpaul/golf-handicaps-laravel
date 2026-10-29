<?php

namespace App\Domain\Courses\Actions;

use App\Domain\Courses\Exceptions\CandidateNotListed;
use App\Domain\Courses\RequeueCourseRatingLookup;
use App\Models\CourseRating;

/**
 * Retains one of the SGT candidates of the row, with its name and its location, and relaunches
 * the lookup from the reading of its card, without matching the names again (spec
 * course-ratings, « Choix d'un parcours SGT candidat »; design D7).
 */
final class ChooseSgtCandidate
{
    public function __construct(private readonly RequeueCourseRatingLookup $requeue) {}

    /**
     * @throws CandidateNotListed when the SGT course is not one of the candidates of the row
     */
    public function handle(CourseRating $courseRating, int $sgtCourseId): void
    {
        $this->requeue->handle($courseRating, function (CourseRating $rating) use ($sgtCourseId): array {
            $candidate = collect($rating->candidates ?? [])->first(fn (array $listed): bool => $listed['id'] === $sgtCourseId);

            return $candidate ?? throw new CandidateNotListed;
        });
    }
}
