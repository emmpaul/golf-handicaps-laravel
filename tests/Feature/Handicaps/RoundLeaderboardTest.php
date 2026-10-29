<?php

/*
 * Port of roundLeaderboard of tests/server/rounds/leaderboard.test.ts of the Next.js
 * application; spec rounds « Classement net d'un round », « Index d'avant le round »,
 * « Classement net avec rating »; design D5.
 *
 * Mutations that turn these tests red:
 * - index_after_t read instead of index_before_t: Sean, on his first round, gets an index of
 *   11,0, a playing handicap of 11, a net of 70 and the rank 1;
 * - the rating of the round not passed to NetRanking::rank: on 71,4 / 144 the handicaps are 1
 *   and 17, Gab and Manu share the rank 1;
 * - a par of 72 hard-coded instead of the par total of the round: on 71,4 / 144 the handicaps
 *   are 1 and 21.
 */

use App\Domain\Handicaps\Queries\RoundLeaderboard;
use App\Models\CourseRating;
use App\Models\Player;
use App\Models\Round;

beforeEach(function () {
    $this->gab = Player::factory()->create(['name' => 'Gab']);
    $this->manu = Player::factory()->create(['name' => 'Manu']);
});

/**
 * Gab (+1) and Manu (+17) on PAYNES VALLEY / Blue on 2026-10-10, without rating: their index
 * before the reference card is 1,0 and 17,0. Then the reference card on BETHPAGE BLACK / Red.
 */
function referenceCardAfterPaynesValley(Player $gab, Player $manu): Round
{
    $paynes = referenceCardOn('2026-10-10', [
        [$gab->id, strokesOverPar(1)],
        [$manu->id, strokesOverPar(17)],
    ]);
    $paynes['courseName'] = 'PAYNES VALLEY';
    $paynes['tees'] = 'Blue';
    createRoundCard($paynes);

    return createRoundCard(referenceCardData($gab->id, $manu->id));
}

test('the reference card is ranked with the index of each player just before it', function () {
    $sean = Player::factory()->create(['name' => 'Sean']);
    createRoundCard(referenceCardOn('2026-10-10', [
        [$this->gab->id, strokesOverPar(1)],
        [$this->manu->id, strokesOverPar(17)],
    ]));
    $card = referenceCardData($this->gab->id, $this->manu->id);
    $card['lines'][] = ['playerId' => $sean->id, 'strokes' => strokesOverPar(10)];
    $round = createRoundCard($card);

    expect(app(RoundLeaderboard::class)->for($round))->toBe([
        ['playerId' => $this->gab->id, 'name' => 'Gab', 'colorSlot' => 0, 'gross' => 72, 'indexBeforeT' => 10, 'playingHandicap' => 1, 'net' => 71, 'rank' => 1],
        ['playerId' => $this->manu->id, 'name' => 'Manu', 'colorSlot' => 1, 'gross' => 88, 'indexBeforeT' => 170, 'playingHandicap' => 17, 'net' => 71, 'rank' => 1],
        ['playerId' => $sean->id, 'name' => 'Sean', 'colorSlot' => 2, 'gross' => 81, 'indexBeforeT' => null, 'playingHandicap' => null, 'net' => null, 'rank' => null],
    ]);
});

test('the round after Paynes Valley is ranked with the course handicap of a confirmed rating only', function (Closure $rating, array $expectedRating, array $expectedRanking) {
    $rating();
    $round = referenceCardAfterPaynesValley($this->gab, $this->manu);

    $ranking = array_map(
        fn (array $row): array => [$row['name'], $row['gross'], $row['indexBeforeT'], $row['playingHandicap'], $row['net'], $row['rank']],
        app(RoundLeaderboard::class)->for($round),
    );

    expect(app(RoundLeaderboard::class)->rating($round))->toBe($expectedRating)
        ->and($ranking)->toBe($expectedRanking);
})->with([
    'a rating typed for BETHPAGE BLACK / Red' => [
        fn () => CourseRating::factory()->bethpageRed()->create(),
        ['rated' => true, 'ratingT' => 714, 'slope' => 144],
        [['Gab', 72, 10, 2, 70, 2], ['Manu', 88, 170, 22, 66, 1]],
    ],
    // Mutations: the round rated as soon as its row exists, whatever rating_t (a TypeError on
    // the null rating), or the rating read in tees_read (71,4 / 144 of the tee Red, handicaps
    // 2 and 22).
    'a proposal of 71,4 / 144 not confirmed leaves the round without rating' => [
        fn () => CourseRating::factory()->proposed()->create(),
        ['rated' => false, 'ratingT' => 710, 'slope' => 113],
        [['Gab', 72, 10, 1, 71, 1], ['Manu', 88, 170, 17, 71, 1]],
    ],
    // Mutation: the round rated only on a source manual, the handicaps then 1 and 17.
    'the proposal confirmed from SGT' => [
        fn () => CourseRating::factory()->confirmedFromSgt()->create(),
        ['rated' => true, 'ratingT' => 714, 'slope' => 144],
        [['Gab', 72, 10, 2, 70, 2], ['Manu', 88, 170, 22, 66, 1]],
    ],
]);
