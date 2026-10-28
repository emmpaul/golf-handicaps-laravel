<?php

namespace App\Domain\Rounds\Queries;

use App\Models\Round;
use App\Models\RoundHole;

/**
 * The known courses (spec rounds, « Parcours connus »): one per course and tees, without case
 * nor surrounding spaces, with the pars, indexes and distances of the latest round played there,
 * by date of play then by date of saving.
 */
final class KnownCourses
{
    /**
     * @return list<array{courseName: string, tees: string, pars: list<int>, strokeIndexes: list<int>, distances: list<int|null>}>
     */
    public function get(): array
    {
        $key = 'lower(trim(course_name)), lower(trim(tees))';

        return array_values(Round::query()
            ->selectRaw("distinct on ({$key}) *")
            ->with('holes')
            ->orderByRaw($key)
            ->orderByDesc('played_on')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Round $round): array => [
                'courseName' => $round->course_name,
                'tees' => $round->tees,
                'pars' => array_values($round->holes->map(fn (RoundHole $hole): int => $hole->par)->all()),
                'strokeIndexes' => array_values($round->holes->map(fn (RoundHole $hole): int => $hole->stroke_index)->all()),
                'distances' => array_values($round->holes->map(fn (RoundHole $hole): ?int => $hole->distance)->all()),
            ])
            ->all());
    }
}
