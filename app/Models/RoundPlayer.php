<?php

namespace App\Models;

use Database\Factories\RoundPlayerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Query\JoinClause;

/**
 * A line of a round's card: the player and the position of the line, from 0.
 *
 * @property string $id
 * @property string $round_id
 * @property string $player_id
 * @property int $position
 * @property-read Player $player never null: player_id is a required foreign key in restrict
 */
#[Fillable(['round_id', 'player_id', 'position'])]
class RoundPlayer extends Model
{
    /** @use HasFactory<RoundPlayerFactory> */
    use HasFactory, HasUuids;

    public $timestamps = false;

    /**
     * @return BelongsTo<Round, $this>
     */
    public function round(): BelongsTo
    {
        return $this->belongsTo(Round::class);
    }

    /**
     * @return BelongsTo<Player, $this>
     */
    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    /**
     * Joins to each line its player (players), its colour (player_slots.color_slot) and its
     * gross and index just before the round (player_round_stats), as the net leaderboard of a
     * round and the virtual match read them.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function withPlayerStats(Builder $query): void
    {
        $query->join('players', 'players.id', '=', 'round_players.player_id')
            ->joinSub(Player::colorSlots(), 'player_slots', 'player_slots.id', '=', 'round_players.player_id')
            ->join('player_round_stats', fn (JoinClause $join) => $join
                ->on('player_round_stats.round_id', '=', 'round_players.round_id')
                ->on('player_round_stats.player_id', '=', 'round_players.player_id'));
    }
}
