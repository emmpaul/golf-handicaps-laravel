<?php

/*
 * PostgreSQL keeps player_round_stats tied to the lines of the rounds, even when a recompute is
 * forgotten.
 *
 * Mutations that turn these tests red:
 * - the composite foreign key towards round_players removed: the row of a player outside the
 *   round is inserted, and removing Manu's line leaves his row;
 * - the round_id foreign key without cascade: deleting the round is refused;
 * - the unique (round_id, player_id) removed: a second row of Gab on the round is inserted;
 * - the CHECK ≤ 540 on index_after_t removed: an index after of 541 is inserted.
 */

use App\Models\Player;
use App\Models\PlayerRoundStat;
use App\Models\Round;
use App\Models\RoundPlayer;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;

beforeEach(function () {
    $this->gab = Player::factory()->create(['name' => 'Gab']);
    $this->manu = Player::factory()->create(['name' => 'Manu']);
    $this->round = Round::factory()->withPlayers($this->gab, $this->manu)->create();
});

test('the row of a player outside the round is refused', function () {
    $sean = Player::factory()->create(['name' => 'Sean']);

    expect(fn () => PlayerRoundStat::factory()->create(['round_id' => $this->round->id, 'player_id' => $sean->id]))
        ->toThrow(QueryException::class, 'player_round_stats_round_id_player_id_foreign');
});

test('a second row of a player on the same round is refused', function () {
    PlayerRoundStat::factory()->create(['round_id' => $this->round->id, 'player_id' => $this->gab->id]);

    expect(fn () => PlayerRoundStat::factory()->create(['round_id' => $this->round->id, 'player_id' => $this->gab->id]))
        ->toThrow(UniqueConstraintViolationException::class, 'player_round_stats_round_id_player_id_unique');
});

test('an index after the round above 54,0 is refused', function () {
    PlayerRoundStat::factory()->create(['round_id' => $this->round->id, 'player_id' => $this->gab->id, 'index_after_t' => 540]);

    expect(fn () => PlayerRoundStat::factory()->create(['round_id' => $this->round->id, 'player_id' => $this->manu->id, 'index_after_t' => 541]))
        ->toThrow(QueryException::class, 'player_round_stats_index_after_t_check');
});

test('removing the line of a player from a round removes his row', function () {
    PlayerRoundStat::factory()->create(['round_id' => $this->round->id, 'player_id' => $this->gab->id]);
    PlayerRoundStat::factory()->create(['round_id' => $this->round->id, 'player_id' => $this->manu->id]);

    RoundPlayer::query()->where('round_id', $this->round->id)->where('player_id', $this->manu->id)->delete();

    expect(PlayerRoundStat::query()->pluck('player_id')->all())->toBe([$this->gab->id]);
});

test('deleting a round deletes its rows', function () {
    $kept = Round::factory()->withPlayers($this->gab)->create();
    PlayerRoundStat::factory()->create(['round_id' => $this->round->id, 'player_id' => $this->gab->id]);
    PlayerRoundStat::factory()->create(['round_id' => $kept->id, 'player_id' => $this->gab->id]);

    $this->round->delete();

    expect(PlayerRoundStat::query()->pluck('round_id')->all())->toBe([$kept->id]);
});
