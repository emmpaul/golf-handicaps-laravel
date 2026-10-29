<?php

namespace App\Domain\Courses\Queries;

use App\Domain\Courses\CourseRatingStatus;
use App\Domain\Courses\Sgt\SgtLinks;
use App\Models\Round;
use App\Models\RoundHole;
use Illuminate\Database\Query\JoinClause;

/**
 * The courses of the Courses page (spec course-ratings, « Page Parcours »): the known courses,
 * by name then tees whatever the case, each with the par total of its latest round, its
 * confirmed rating, or null, the state of its lookup on Simulator Golf Tour (design D8 of
 * course-rating-sgt), and the stroke indexes of that latest round, hole 1 to 18 (the Strokes
 * page, spec match-strokes, « Répartition des coups par trou »). Port of courseRatingOverview of
 * the Next.js application.
 *
 * @phpstan-type Tee array{name: string, ratingT: int, slope: int}
 * @phpstan-type Candidate array{id: int, name: string, location: string, par: int, scorecardUrl: string}
 * @phpstan-type Lookup array{id: string|null, status: string|null, label: string|null, note: string|null, source: string|null, sgtCourseName: string|null, sgtCourseLocation: string|null, proposal: Tee|null, teesRead: list<Tee>, candidates: list<Candidate>, scorecardUrl: string|null, courseViewUrl: string|null}
 * @phpstan-type Course array{courseName: string, tees: string, par: int, ratingT: int|null, slope: int|null, strokeIndexes: list<int>, id: string|null, status: string|null, label: string|null, note: string|null, source: string|null, sgtCourseName: string|null, sgtCourseLocation: string|null, proposal: Tee|null, teesRead: list<Tee>, candidates: list<Candidate>, scorecardUrl: string|null, courseViewUrl: string|null}
 */
final class CourseOverview
{
    /**
     * @return list<Course>
     */
    public function get(): array
    {
        // One query: the latest round of each course and tees (the key and order of
        // KnownCourses) with the row of course_ratings of its pair, matched in SQL, never by a
        // lower case of PHP. The columns of the row are aliased: rounds has an id too.
        $key = 'lower(trim(rounds.course_name)), lower(trim(rounds.tees))';

        return array_values(Round::query()
            ->leftJoin('course_ratings', fn (JoinClause $join): JoinClause => $join
                ->whereRaw('lower(trim(course_ratings.course_name)) = lower(trim(rounds.course_name))')
                ->whereRaw('lower(trim(course_ratings.tees)) = lower(trim(rounds.tees))'))
            ->selectRaw("distinct on ({$key}) rounds.*, course_ratings.id as rating_id, course_ratings.rating_t,
                course_ratings.slope, course_ratings.source as rating_source, course_ratings.status as rating_status,
                course_ratings.note as rating_note, course_ratings.sgt_course_id, course_ratings.sgt_course_name,
                course_ratings.sgt_course_location, course_ratings.candidates, course_ratings.tees_read,
                course_ratings.proposed_tee")
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
                'ratingT' => self::nullableInt($round->getAttribute('rating_t')),
                'slope' => self::nullableInt($round->getAttribute('slope')),
                'strokeIndexes' => array_values($round->holes->map(fn (RoundHole $hole): int => $hole->stroke_index)->all()),
                ...self::lookup($round),
            ])
            ->all());
    }

    /**
     * The lookup of the pair: its state, the SGT course retained and what its card gave. A pair
     * without row (the sweep not run yet) has no id nor status. The note of a confirmed row is
     * not shown (decision Q18).
     *
     * @return Lookup
     */
    private static function lookup(Round $round): array
    {
        $rawStatus = $round->getAttribute('rating_status');
        $status = is_string($rawStatus) ? CourseRatingStatus::from($rawStatus) : null;
        $sgtCourseId = self::nullableInt($round->getAttribute('sgt_course_id'));
        $sgtCourseName = self::nullableString($round->getAttribute('sgt_course_name'));
        // jsonb keeps its keys in its own order: each one is written back in the order of D1.
        $teesRead = array_map(
            fn (array $tee): array => ['name' => (string) $tee['name'], 'ratingT' => (int) $tee['ratingT'], 'slope' => (int) $tee['slope']],
            self::decoded($round->getAttribute('tees_read')),
        );
        $proposedTee = $round->getAttribute('proposed_tee');

        return [
            'id' => self::nullableString($round->getAttribute('rating_id')),
            'status' => $status?->value,
            'label' => $status?->label(),
            'note' => $status === CourseRatingStatus::Confirmed ? null : self::nullableString($round->getAttribute('rating_note')),
            'source' => self::nullableString($round->getAttribute('rating_source')),
            'sgtCourseName' => $sgtCourseName,
            'sgtCourseLocation' => self::nullableString($round->getAttribute('sgt_course_location')),
            'proposal' => collect($teesRead)->first(fn (array $tee): bool => $proposedTee !== null && $tee['name'] === $proposedTee),
            'teesRead' => $teesRead,
            'candidates' => array_map(fn (array $candidate): array => [
                'id' => (int) $candidate['id'],
                'name' => (string) $candidate['name'],
                'location' => (string) $candidate['location'],
                'par' => (int) $candidate['par'],
                'scorecardUrl' => SgtLinks::scorecardUrl((int) $candidate['id']),
            ], self::decoded($round->getAttribute('candidates'))),
            'scorecardUrl' => $sgtCourseId === null ? null : SgtLinks::scorecardUrl($sgtCourseId),
            // The slug of the SGT name (« Sanctuary »), not of the name of the round (« THE SANCTUARY »).
            'courseViewUrl' => $sgtCourseName === null ? null : SgtLinks::courseViewUrl($sgtCourseName),
        ];
    }

    private static function nullableInt(mixed $value): ?int
    {
        return $value === null ? null : (int) $value;
    }

    private static function nullableString(mixed $value): ?string
    {
        return $value === null ? null : (string) $value;
    }

    /**
     * A jsonb column read raw by the join, outside the casts of CourseRating.
     *
     * @return list<array<string, scalar>>
     */
    private static function decoded(mixed $json): array
    {
        if (! is_string($json)) {
            return [];
        }

        /** @var list<array<string, scalar>> */
        return json_decode($json, true, flags: JSON_THROW_ON_ERROR);
    }
}
