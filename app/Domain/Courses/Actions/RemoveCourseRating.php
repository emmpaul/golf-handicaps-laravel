<?php

namespace App\Domain\Courses\Actions;

use App\Domain\Courses\CourseRatingStatus;
use App\Domain\Courses\Exceptions\CourseHasNoRating;
use App\Domain\Courses\Exceptions\UnknownCourse;
use App\Domain\Courses\Queries\CourseRounds;
use App\Domain\Handicaps\Actions\RecomputePlayerStats;
use App\Models\CourseRating;
use Illuminate\Support\Facades\DB;

/**
 * Removes the confirmed rating of a course and tees, whatever the case and the surrounding
 * spaces; the players who have a round there are recomputed on the par total and 113 (design D6).
 * The row stays and gets back the state of its last lookup (design D7 of course-rating-sgt).
 */
final class RemoveCourseRating
{
    public function __construct(
        private readonly CourseRounds $courseRounds,
        private readonly RecomputePlayerStats $recomputePlayerStats,
    ) {}

    /**
     * @throws UnknownCourse when no round is saved on the course and tees, whatever the case
     * @throws CourseHasNoRating when the course and tees have no confirmed rating
     */
    public function handle(string $courseName, string $tees): void
    {
        DB::transaction(function () use ($courseName, $tees): void {
            if (! $this->courseRounds->exist($courseName, $tees)) {
                throw new UnknownCourse;
            }

            $rating = CourseRating::query()
                ->whereRaw('lower(trim(course_name)) = lower(trim(?))', [$courseName])
                ->whereRaw('lower(trim(tees)) = lower(trim(?))', [$tees])
                ->lockForUpdate()
                ->first();

            if ($rating?->rating_t === null) {
                throw new CourseHasNoRating;
            }

            $rating->update([
                'rating_t' => null,
                'slope' => null,
                'source' => null,
                'note' => null,
                'status' => $this->recoveredStatus($rating),
            ]);

            $this->recomputePlayerStats->handle($this->courseRounds->playerIds($courseName, $tees));
        });
    }

    /**
     * The state of the last lookup: still pending while a relaunched lookup runs, else its
     * proposal, else its tees read, else its candidates, else a rating to type by hand.
     */
    private function recoveredStatus(CourseRating $rating): CourseRatingStatus
    {
        $teesRead = array_column($rating->tees_read ?? [], 'name');

        return match (true) {
            $rating->status === CourseRatingStatus::Pending => CourseRatingStatus::Pending,
            $rating->proposed_tee !== null && in_array($rating->proposed_tee, $teesRead, true) => CourseRatingStatus::Proposed,
            $teesRead !== [] => CourseRatingStatus::ChooseTee,
            ($rating->candidates ?? []) !== [] => CourseRatingStatus::ChooseCourse,
            default => CourseRatingStatus::Manual,
        };
    }
}
