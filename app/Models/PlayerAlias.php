<?php

namespace App\Models;

use Database\Factories\PlayerAliasFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A spelling that names a player, stored upper case; unique whatever the case across players.
 *
 * @property string $id
 * @property string $player_id
 * @property string $alias
 */
#[Fillable(['player_id', 'alias'])]
class PlayerAlias extends Model
{
    /** @use HasFactory<PlayerAliasFactory> */
    use HasFactory, HasUuids;

    /**
     * @return BelongsTo<Player, $this>
     */
    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }
}
