<?php

namespace App\Domain\Courses\Actions;

use App\Jobs\LookUpCourseRating;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Queues the lookup of the rating of a course and tees that has no row yet, whatever the case
 * and the surrounding spaces (design D6). A pair that already has a row, looked up, being looked
 * up or typed by hand, is never looked up again without an action of the user. The job leaves
 * once the transaction of the caller is committed.
 */
final class QueueCourseRatingLookup
{
    /**
     * @return bool whether a lookup was queued
     */
    public function handle(string $courseName, string $tees): bool
    {
        // PostgreSQL infers the unique index on the expression: two rounds saved at once on a
        // new pair insert one row; the row keeps the text of the first.
        $now = now();
        $inserted = DB::selectOne(
            "insert into course_ratings (id, course_name, tees, status, created_at, updated_at)
            values (?, ?, ?, 'pending', ?, ?)
            on conflict ((lower(trim(course_name))), (lower(trim(tees)))) do nothing
            returning id",
            [(string) Str::uuid7(), $courseName, $tees, $now, $now],
        );

        if ($inserted === null) {
            return false;
        }

        LookUpCourseRating::dispatch($inserted->id)->afterCommit();

        return true;
    }
}
