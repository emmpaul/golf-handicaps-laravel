<?php

namespace App\Domain\Courses\Actions;

use App\Domain\Courses\Exceptions\UnknownCourse;
use App\Domain\Courses\Queries\CourseRounds;
use App\Domain\Handicaps\Actions\RecomputePlayerStats;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Types or corrects the course rating and slope of a course and tees, then recomputes the
 * history of every player who has a round there. Port of saveManualRating of the Next.js
 * application.
 */
final class SaveCourseRating
{
    public function __construct(
        private readonly CourseRounds $courseRounds,
        private readonly RecomputePlayerStats $recomputePlayerStats,
    ) {}

    /**
     * @param  int  $ratingT  in tenths, 400 to 900
     * @param  int  $slope  55 to 155
     *
     * @throws UnknownCourse when no round is saved on the course and tees, whatever the case
     */
    public function handle(string $courseName, string $tees, int $ratingT, int $slope): void
    {
        DB::transaction(function () use ($courseName, $tees, $ratingT, $slope): void {
            if (! $this->courseRounds->exist($courseName, $tees)) {
                throw new UnknownCourse;
            }

            // PostgreSQL infers the unique index on the expression: two ratings typed at once
            // for the same course never raise a unique violation. The row keeps its first name.
            // A new row is a rating typed by hand, confirmed (design D1 of course-rating-sgt).
            $now = now();
            DB::insert(
                "insert into course_ratings (id, course_name, tees, rating_t, slope, source, status, created_at, updated_at)
                values (?, ?, ?, ?, ?, 'manual', 'confirmed', ?, ?)
                on conflict ((lower(trim(course_name))), (lower(trim(tees))))
                do update set rating_t = excluded.rating_t, slope = excluded.slope, updated_at = excluded.updated_at",
                [(string) Str::uuid7(), $courseName, $tees, $ratingT, $slope, $now, $now],
            );

            $this->recomputePlayerStats->handle($this->courseRounds->playerIds($courseName, $tees));
        });
    }
}
