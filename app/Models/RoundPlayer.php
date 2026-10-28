<?php

namespace App\Models;

use Database\Factories\RoundPlayerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
}
