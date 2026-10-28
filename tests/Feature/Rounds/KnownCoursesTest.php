<?php

/*
 * Mutations that turn these tests red:
 * - the latest round sorted by date of play only: of the two rounds of 2026-10-17, the one
 *   saved first gives its holes (par 4 at hole 1 instead of 5);
 * - the latest round sorted by date of saving only: the round of 2026-10-10, saved last, gives
 *   its holes (par 3 at hole 1);
 * - the course key with its case: « BETHPAGE BLACK / Red » and « Bethpage Black / red » make
 *   two entries;
 * - the tees left out of the key: Red and Blue make one entry.
 */

use App\Domain\Rounds\Actions\CreateRound;
use App\Domain\Rounds\Queries\KnownCourses;
use App\Domain\Rounds\RoundCard;
use App\Models\Player;

beforeEach(function () {
    $this->gab = Player::factory()->create(['name' => 'Gab']);
    $this->manu = Player::factory()->create(['name' => 'Manu']);
});

/**
 * @param  array<string, mixed>  $changes  courseName, tees, playedOn, par1
 */
function saveReferenceRound(Player $gab, Player $manu, array $changes, string $savedAt): void
{
    $card = referenceCardData($gab->id, $manu->id);
    $card['courseName'] = $changes['courseName'];
    $card['tees'] = $changes['tees'];
    $card['playedOn'] = $changes['playedOn'] ?? $card['playedOn'];
    $card['holes'][0]['par'] = $changes['par1'] ?? $card['holes'][0]['par'];

    test()->travelTo($savedAt);
    app(CreateRound::class)->handle(RoundCard::fromArray($card));
}

test('the same course with other tees makes two entries', function () {
    saveReferenceRound($this->gab, $this->manu, ['courseName' => 'Bethpage Black', 'tees' => 'Red'], '2026-10-18 10:00:00');
    saveReferenceRound($this->gab, $this->manu, ['courseName' => 'Bethpage Black', 'tees' => 'Blue'], '2026-10-18 11:00:00');

    expect(array_map(
        fn (array $course): array => [$course['courseName'], $course['tees']],
        app(KnownCourses::class)->get(),
    ))->toBe([['Bethpage Black', 'Blue'], ['Bethpage Black', 'Red']]);
});

test('another case makes one entry with the holes of the latest round', function () {
    saveReferenceRound($this->gab, $this->manu, ['courseName' => 'BETHPAGE BLACK', 'tees' => 'Red'], '2026-10-18 10:00:00');
    saveReferenceRound($this->gab, $this->manu, ['courseName' => 'Bethpage Black', 'tees' => 'red', 'par1' => 5], '2026-10-18 11:00:00');
    saveReferenceRound($this->gab, $this->manu, ['courseName' => 'BETHPAGE BLACK', 'tees' => 'Red', 'playedOn' => '2026-10-10', 'par1' => 3], '2026-10-19 09:00:00');

    expect(app(KnownCourses::class)->get())->toBe([[
        'courseName' => 'Bethpage Black',
        'tees' => 'red',
        'pars' => [5, 4, 3, 5, 4, 4, 5, 3, 4, 4, 4, 4, 5, 3, 4, 4, 3, 4],
        'strokeIndexes' => [8, 16, 18, 2, 4, 10, 6, 14, 12, 9, 11, 7, 3, 17, 1, 5, 13, 15],
        'distances' => [420, 344, 128, 444, 398, 377, 480, 155, 392, 353, 405, 392, 470, 149, 401, 423, 173, 337],
    ]]);
});
