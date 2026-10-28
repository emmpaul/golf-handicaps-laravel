<?php

namespace App\Models;

use Database\Factories\ScoreFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The gross strokes of a player of the round on one hole.
 *
 * @property string $id
 * @property string $round_id
 * @property string $player_id
 * @property int $hole 1 to 18
 * @property int $strokes
 */
#[Fillable(['round_id', 'player_id', 'hole', 'strokes'])]
class Score extends Model
{
    /** @use HasFactory<ScoreFactory> */
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
