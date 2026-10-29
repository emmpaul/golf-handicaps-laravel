<?php

namespace App\Domain\Courses\Queries;

use App\Models\Round;
use App\Models\RoundHole;
use Illuminate\Database\Query\JoinClause;

/**
 * The courses of the Courses page (spec course-ratings, « Page Parcours »): the known courses,
 * by name then tees whatever the case, each with the par total of its latest round and its
 * typed rating, or null, and the stroke indexes of that latest round, hole 1 to 18 (the Strokes
 * page, spec match-strokes, « Répartition des coups par trou »). Port of courseRatingOverview of
 * the Next.js application, without SGT.
 */
final class CourseOverview
{
    /**
     * @return list<array{courseName: string, tees: string, par: int, ratingT: int|null, slope: int|null, strokeIndexes: list<int>}>
     */
    public function get(): array
    {
        // One query: the latest round of each course and tees (the key and order of
        // KnownCourses) with the rating of its pair, matched in SQL, never by a lower case of PHP.
        $key = 'lower(trim(rounds.course_name)), lower(trim(rounds.tees))';

        return array_values(Round::query()
            ->leftJoin('course_ratings', fn (JoinClause $join): JoinClause => $join
                ->whereRaw('lower(trim(course_ratings.course_name)) = lower(trim(rounds.course_name))')
                ->whereRaw('lower(trim(course_ratings.tees)) = lower(trim(rounds.tees))'))
            ->selectRaw("distinct on ({$key}) rounds.*, course_ratings.rating_t, course_ratings.slope")
            ->with('holes')
            ->orderByRaw($key)
            ->orderByDesc('rounds.played_on')
            ->orderByDesc('rounds.created_at')
            ->orderByDesc('rounds.id')
            ->get()
            ->map(fn (Round $round): array => [
                'courseName' => $round->course_name,
                'tees' => $round->tees,
                'par' => $round->holes->sum(fn (RoundHole $hole): int => $hole->par),
                'ratingT' => $round->getAttribute('rating_t') === null ? null : (int) $round->getAttribute('rating_t'),
                'slope' => $round->getAttribute('slope') === null ? null : (int) $round->getAttribute('slope'),
                'strokeIndexes' => array_values($round->holes->map(fn (RoundHole $hole): int => $hole->stroke_index)->all()),
            ])
            ->all());
    }
}
