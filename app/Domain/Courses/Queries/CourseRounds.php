<?php

namespace App\Domain\Courses\Queries;

use App\Models\Round;
use App\Models\RoundPlayer;
use Illuminate\Database\Eloquent\Builder;

/**
 * The rounds of a course and tees, without case nor surrounding spaces: the expression of the
 * unique index of course_ratings and of KnownCourses, compared in SQL, never in PHP.
 */
final class CourseRounds
{
    public function exist(string $courseName, string $tees): bool
    {
        return $this->onCourse(Round::query(), $courseName, $tees)->exists();
    }

    /**
     * The players who have at least one round on the course and tees.
     *
     * @return list<string>
     */
    public function playerIds(string $courseName, string $tees): array
    {
        $query = RoundPlayer::query()->join('rounds', 'rounds.id', '=', 'round_players.round_id');

        /** @var list<string> */
        return array_values($this->onCourse($query, $courseName, $tees)
            ->distinct()
            ->orderBy('round_players.player_id')
            ->pluck('round_players.player_id')
            ->all());
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    private function onCourse(Builder $query, string $courseName, string $tees): Builder
    {
        return $query
            ->whereRaw('lower(trim(rounds.course_name)) = lower(trim(?))', [$courseName])
            ->whereRaw('lower(trim(rounds.tees)) = lower(trim(?))', [$tees]);
    }
}
