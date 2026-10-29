<?php

/*
 * Spec home-dashboard, « Imports à vérifier »: « Plusieurs imports prêts », « Import ancien »,
 * « Rien à vérifier »; design D3.
 *
 * Mutations that turn these tests red:
 * - `round_id is null` removed from PendingImports: the ready import saved as a round counts,
 *   3 instead of 2;
 * - PendingImports bounded to the 20 latest imports (as ImportList::LIMIT): the ready import
 *   behind 21 newer failed ones is not seen, 0 instead of 1.
 */

use App\Domain\Imports\Queries\PendingImports;
use App\Models\Import;
use App\Models\Round;
use Carbon\CarbonImmutable;

/**
 * @return array{created_at: CarbonImmutable}
 */
function sentOn(string $at): array
{
    return ['created_at' => CarbonImmutable::parse($at, 'UTC')];
}

test('only the ready imports without round count, the latest by date of sending is named', function () {
    // The import of 2026-10-17 is created first, so with the smaller id.
    $latest = Import::factory()->ready()->create(sentOn('2026-10-17 12:00:00'));
    Import::factory()->ready()->create(sentOn('2026-10-16 12:00:00'));
    Import::factory()->savedAs(Round::factory()->create())->create(sentOn('2026-10-18 12:00:00'));
    Import::factory()->reading()->create(sentOn('2026-10-18 12:00:00'));
    Import::factory()->failed('Lecture impossible')->create(sentOn('2026-10-18 12:00:00'));

    expect(app(PendingImports::class)->get())->toBe(['count' => 2, 'latestId' => $latest->id]);
});

test('without import nothing is to check', function () {
    expect(app(PendingImports::class)->get())->toBe(['count' => 0, 'latestId' => null]);
});

test('a ready import behind 21 newer failed ones still counts', function () {
    $ready = Import::factory()->ready()->create(sentOn('2026-10-01 12:00:00'));
    foreach (range(1, 21) as $day) {
        Import::factory()->failed('Lecture impossible')->create(sentOn(sprintf('2026-10-%02d 13:00:00', $day)));
    }

    expect(app(PendingImports::class)->get())->toBe(['count' => 1, 'latestId' => $ready->id]);
});
