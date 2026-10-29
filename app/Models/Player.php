<?php

namespace App\Models;

use Database\Factories\PlayerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * A player of the group; its name is unique whatever the case (index on lower(name)).
 *
 * @property string $id
 * @property string $name
 * @property int $color_slot only when read with withColorSlot()
 */
#[Fillable(['name'])]
class Player extends Model
{
    /** @use HasFactory<PlayerFactory> */
    use HasFactory, HasUuids;

    /**
     * @return HasMany<PlayerAlias, $this>
     */
    public function aliases(): HasMany
    {
        return $this->hasMany(PlayerAlias::class);
    }

    /**
     * The colour of each player (spec visual-theme « Couleur de joueur », design D6): its rank of
     * creation over the whole table, ties broken by id, modulo the six colours of the design.
     * Nothing is stored: a rename changes nothing, and a player is never deleted.
     */
    public static function colorSlots(): QueryBuilder
    {
        return DB::table('players')
            ->select('id')
            ->selectRaw('(row_number() over (order by created_at, id) - 1) % 6 as color_slot');
    }

    /**
     * Adds color_slot to the players read, whatever the filter of the query.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function withColorSlot(Builder $query): void
    {
        if ($query->getQuery()->columns === null) {
            $query->select('players.*');
        }

        $query->joinSub(self::colorSlots(), 'player_slots', 'player_slots.id', '=', 'players.id')
            ->addSelect('player_slots.color_slot');
    }
}
