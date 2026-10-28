<?php

namespace App\Models;

use Database\Factories\RoundHoleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One of the 18 holes of a round, with the par, index and distance of that round.
 *
 * @property string $id
 * @property string $round_id
 * @property int $hole 1 to 18
 * @property int $par
 * @property int $stroke_index
 * @property int|null $distance
 */
#[Fillable(['round_id', 'hole', 'par', 'stroke_index', 'distance'])]
class RoundHole extends Model
{
    /** @use HasFactory<RoundHoleFactory> */
    use HasFactory, HasUuids;

    public $timestamps = false;

    /**
     * @return BelongsTo<Round, $this>
     */
    public function round(): BelongsTo
    {
        return $this->belongsTo(Round::class);
    }
}
