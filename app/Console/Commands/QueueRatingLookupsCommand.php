<?php

namespace App\Console\Commands;

use App\Domain\Courses\Actions\QueueCourseRatingLookup;
use App\Models\Round;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Run by the migrate service of the stack after the recompute of the history, at every
 * deployment: each course and tees of a round without a row of course_ratings gets its lookup
 * queued, once (design D6 of course-rating-sgt). The row keeps the text of the oldest round of the
 * pair. A second run queues nothing. Only the worker reaches Simulator Golf Tour.
 */
#[Signature('golf:queue-rating-lookups')]
#[Description('Queue the rating lookup of every course and tees without a rating row')]
class QueueRatingLookupsCommand extends Command
{
    public function handle(QueueCourseRatingLookup $queueCourseRatingLookup): int
    {
        $key = 'lower(trim(course_name)), lower(trim(tees))';

        /** @var list<array{course_name: string, tees: string}> $pairs */
        $pairs = Round::query()
            ->selectRaw("distinct on ({$key}) course_name, tees")
            ->orderByRaw($key)
            ->orderBy('played_on')
            ->orderBy('created_at')
            ->orderBy('id')
            ->toBase()
            ->get()
            ->map(fn (object $pair): array => ['course_name' => $pair->course_name, 'tees' => $pair->tees])
            ->all();

        $count = DB::transaction(fn (): int => collect($pairs)
            ->filter(fn (array $pair): bool => $queueCourseRatingLookup->handle($pair['course_name'], $pair['tees']))
            ->count());

        $this->info($count > 1
            ? "{$count} recherches de rating mises en file."
            : "{$count} recherche de rating mise en file.");

        return self::SUCCESS;
    }
}
