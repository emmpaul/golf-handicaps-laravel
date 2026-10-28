<?php

namespace App\Models;

use Database\Factories\PlayerRoundStatFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The handicap history of a player on a round, derived from the rounds and the ratings:
 * App\Domain\Handicaps\Actions\RecomputePlayerStats rewrites it. Differentials and indexes are
 * integer tenths (174 = 17,4).
 *
 * @property string $id
 * @property string $round_id
 * @property string $player_id
 * @property int $gross
 * @property int $adjusted
 * @property int $differential_t
 * @property int|null $index_before_t null for the first round of the player
 * @property int $index_after_t
 * @property bool $counts_in_current
 */
#[Fillable(['round_id', 'player_id', 'gross', 'adjusted', 'differential_t', 'index_before_t', 'index_after_t', 'counts_in_current'])]
class PlayerRoundStat extends Model
{
    /** @use HasFactory<PlayerRoundStatFactory> */
    use HasFactory, HasUuids;

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'counts_in_current' => 'boolean',
        ];
    }

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
