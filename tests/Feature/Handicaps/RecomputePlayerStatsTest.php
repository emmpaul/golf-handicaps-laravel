<?php

/*
 * Port of tests/server/rounds/{stats,history}.test.ts of the Next.js application.
 *
 * Mutations that turn these tests red:
 * - only the new round computed (the rounds of the player before it left as they were): the
 *   round of 2026-10-01 saved last leaves the indexes after 2026-10-10 and 2026-10-17 at 170
 *   and 145;
 * - the history replayed in the order of saving instead of the date of play: the round of
 *   2026-10-01 counts as the third, its index before is 145;
 * - the rating looked up with its case: « Bethpage Black / red » does not match the round on
 *   « BETHPAGE BLACK / Red », Gab and Manu stay at 10 and 170;
 * - lockForUpdate() removed: no select … for update on players is sent;
 * - the rating matched on course_name only: the round on « BETHPAGE BLACK / Blue » takes the
 *   71,4 / 144 of the Red tees, Gab and Manu at 5 and 130 instead of 10 and 170;
 * - the gross stored as the adjusted score: Manu with 10 on hole 1 has a gross of 89 instead of
 *   91.
 */

use App\Domain\Handicaps\Actions\RecomputePlayerStats;
use App\Models\CourseRating;
use App\Models\Player;
use App\Models\PlayerRoundStat;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->gab = Player::factory()->create(['name' => 'Gab']);
    $this->manu = Player::factory()->create(['name' => 'Manu']);
});

/**
 * The history of a player by date of play: [differential_t, index_before_t, index_after_t,
 * counts_in_current] per round.
 *
 * @return list<list<mixed>>
 */
function historyOf(Player $player): array
{
    return statsByDateOf($player, ['differential_t', 'index_before_t', 'index_after_t', 'counts_in_current']);
}

test('the reference card without rating gives Gab 1,0 and Manu 17,0', function () {
    $round = createRoundCard(referenceCardData($this->gab->id, $this->manu->id));

    expect(PlayerRoundStat::query()->where('round_id', $round->id)->orderBy('gross')
        ->get(['player_id', 'gross', 'adjusted', 'differential_t', 'index_before_t', 'index_after_t', 'counts_in_current'])
        ->toArray())->toBe([
            ['player_id' => $this->gab->id, 'gross' => 72, 'adjusted' => 72, 'differential_t' => 10, 'index_before_t' => null, 'index_after_t' => 10, 'counts_in_current' => true],
            ['player_id' => $this->manu->id, 'gross' => 88, 'adjusted' => 88, 'differential_t' => 170, 'index_before_t' => null, 'index_after_t' => 170, 'counts_in_current' => true],
        ]);
});

test('the reference card with 71,4 / 144 typed for « Bethpage Black / red » gives Gab 0,5 and Manu 13,0', function () {
    CourseRating::factory()->create(['course_name' => 'Bethpage Black', 'tees' => 'red', 'rating_t' => 714, 'slope' => 144]);

    $round = createRoundCard(referenceCardData($this->gab->id, $this->manu->id));

    expect(PlayerRoundStat::query()->where('round_id', $round->id)->orderBy('gross')->pluck('differential_t')->all())
        ->toBe([5, 130]);
});

test('a rating of « BETHPAGE BLACK / Red » leaves a round on « BETHPAGE BLACK / Blue » without rating', function () {
    CourseRating::factory()->bethpageRed()->create();
    $red = createRoundCard(referenceCardData($this->gab->id, $this->manu->id));
    $blueCard = referenceCardData($this->gab->id, $this->manu->id);
    $blueCard['tees'] = 'Blue';
    $blueCard['playedOn'] = '2026-10-24';
    $blue = createRoundCard($blueCard);

    expect(PlayerRoundStat::query()->where('round_id', $red->id)->orderBy('gross')->pluck('differential_t')->all())
        ->toBe([5, 130])
        ->and(PlayerRoundStat::query()->where('round_id', $blue->id)->orderBy('gross')->pluck('differential_t')->all())
        ->toBe([10, 170]);
});

test('Manu with 10 on hole 1 (par 4) has a gross of 91 and an adjusted score of 89', function () {
    $card = referenceCardData($this->gab->id, $this->manu->id);
    $card['lines'][1]['strokes'][0] = 10;

    createRoundCard($card);

    expect(PlayerRoundStat::query()->where('player_id', $this->manu->id)->first(['gross', 'adjusted'])?->toArray())
        ->toBe(['gross' => 91, 'adjusted' => 89]);
});

test('a round saved in the past replays the whole history', function () {
    createRoundCard(referenceCardOn('2026-10-10', [[$this->gab->id, strokesOverPar(17)]]));
    createRoundCard(referenceCardOn('2026-10-17', [[$this->gab->id, strokesOverPar(12)]]));
    createRoundCard(referenceCardOn('2026-10-01', [[$this->gab->id, strokesOverPar(20)]]));

    expect(historyOf($this->gab))->toBe([
        [200, null, 200, false],
        [170, 200, 185, false],
        [120, 185, 100, true],
    ]);
});

test('the 2 best of 6 rounds count in the current index', function () {
    foreach ([17, 12, 20, 15, 14, 18] as $day => $over) {
        createRoundCard(referenceCardOn(sprintf('2026-10-%02d', $day + 1), [[$this->gab->id, strokesOverPar($over)]]));
    }

    expect(array_column(historyOf($this->gab), 3))->toBe([false, true, false, false, true, false]);
});

test('the players are locked in the order of their id before their history is read', function () {
    $queries = [];
    DB::listen(function (QueryExecuted $query) use (&$queries): void {
        $queries[] = ['sql' => $query->sql, 'bindings' => $query->bindings];
    });

    app(RecomputePlayerStats::class)->handle([$this->manu->id, $this->gab->id, $this->manu->id]);

    $locks = array_values(array_filter($queries, fn (array $query): bool => str_contains($query['sql'], 'for update')));
    $sorted = [$this->gab->id, $this->manu->id];
    sort($sorted);

    expect($locks)->toHaveCount(1)
        ->and($locks[0]['sql'])->toContain('from "players"')
        ->and($locks[0]['sql'])->toContain('order by "id" asc')
        ->and($locks[0]['bindings'])->toBe($sorted)
        ->and($queries[0])->toBe($locks[0]);
});
