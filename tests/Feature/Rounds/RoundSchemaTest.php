<?php

/*
 * PostgreSQL is the second line behind RoundCardChecks: these rows are written past the checks.
 *
 * Mutations that turn these tests red:
 * - the composite foreign key of scores towards round_players removed: the score of a player
 *   outside the round is inserted;
 * - the CHECK on par removed: a par of 6 is inserted;
 * - the unique (round_id, stroke_index) removed: two holes share index 1;
 * - the CHECK on strokes removed: a score of 16 is inserted;
 * - the round_players foreign key towards players in cascade instead of restrict: deleting
 *   Manu deletes his line.
 */

use App\Models\Player;
use App\Models\Round;
use App\Models\RoundHole;
use App\Models\RoundPlayer;
use App\Models\Score;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;

beforeEach(function () {
    $this->gab = Player::factory()->create(['name' => 'Gab']);
    $this->manu = Player::factory()->create(['name' => 'Manu']);
});

test('a par of 6 is refused', function () {
    $round = Round::factory()->create();

    expect(fn () => RoundHole::factory()->for($round)->create(['hole' => 1, 'par' => 6, 'stroke_index' => 1]))
        ->toThrow(QueryException::class, 'round_holes_par_check');
});

test('a stroke index given twice in a round is refused', function () {
    $round = Round::factory()->create();
    RoundHole::factory()->for($round)->create(['hole' => 1, 'stroke_index' => 1]);

    expect(fn () => RoundHole::factory()->for($round)->create(['hole' => 2, 'stroke_index' => 1]))
        ->toThrow(UniqueConstraintViolationException::class, 'round_holes_round_id_stroke_index_unique');
});

test('a score of 16 is refused', function () {
    $round = Round::factory()->withPlayers($this->gab)->create();
    Score::query()->where('hole', 1)->delete();

    expect(fn () => Score::factory()->create([
        'round_id' => $round->id, 'player_id' => $this->gab->id, 'hole' => 1, 'strokes' => 16,
    ]))->toThrow(QueryException::class, 'scores_strokes_check');
});

test('a score of a player outside the round is refused', function () {
    $round = Round::factory()->withPlayers($this->gab)->create();

    expect(fn () => Score::factory()->create([
        'round_id' => $round->id, 'player_id' => $this->manu->id, 'hole' => 1, 'strokes' => 5,
    ]))->toThrow(QueryException::class, 'scores_round_id_player_id_foreign');
});

test('deleting a round deletes its holes, lines and scores', function () {
    $round = Round::factory()->withPlayers($this->gab, $this->manu)->create();
    $kept = Round::factory()->withPlayers($this->gab)->create();

    $round->delete();

    expect(RoundHole::query()->count())->toBe(18)
        ->and(RoundPlayer::query()->pluck('round_id')->all())->toBe([$kept->id])
        ->and(Score::query()->count())->toBe(18)
        ->and(Player::query()->count())->toBe(2);
});

test('deleting a player of a round is refused', function () {
    Round::factory()->withPlayers($this->gab, $this->manu)->create();

    expect(fn () => $this->manu->delete())
        ->toThrow(QueryException::class, 'round_players_player_id_foreign');
});
