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

beforeEach(function () {
    $this->gab = Player::factory()->create(['name' => 'Gab']);
    $this->manu = Player::factory()->create(['name' => 'Manu']);
});

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
        ['playerId' => $this->gab->id, 'name' => 'Gab', 'gross' => 72, 'indexBeforeT' => 10, 'playingHandicap' => 1, 'net' => 71, 'rank' => 1],
        ['playerId' => $this->manu->id, 'name' => 'Manu', 'gross' => 88, 'indexBeforeT' => 170, 'playingHandicap' => 17, 'net' => 71, 'rank' => 1],
        ['playerId' => $sean->id, 'name' => 'Sean', 'gross' => 81, 'indexBeforeT' => null, 'playingHandicap' => null, 'net' => null, 'rank' => null],
    ]);
});

test('a round on a rated course is ranked with the course handicap', function () {
    CourseRating::factory()->bethpageRed()->create();
    $paynes = referenceCardOn('2026-10-10', [
        [$this->gab->id, strokesOverPar(1)],
        [$this->manu->id, strokesOverPar(17)],
    ]);
    $paynes['courseName'] = 'PAYNES VALLEY';
    $paynes['tees'] = 'Blue';
    createRoundCard($paynes);
    $round = createRoundCard(referenceCardData($this->gab->id, $this->manu->id));

    expect(app(RoundLeaderboard::class)->for($round))->toBe([
        ['playerId' => $this->gab->id, 'name' => 'Gab', 'gross' => 72, 'indexBeforeT' => 10, 'playingHandicap' => 2, 'net' => 70, 'rank' => 2],
        ['playerId' => $this->manu->id, 'name' => 'Manu', 'gross' => 88, 'indexBeforeT' => 170, 'playingHandicap' => 22, 'net' => 66, 'rank' => 1],
    ]);
});
