<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\RoundFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A round played on a course and tees: its own 18 holes, 1 to 4 lines of players in the order
 * of the card, and their gross scores.
 *
 * @property string $id
 * @property string $course_name
 * @property string $tees
 * @property CarbonImmutable $played_on
 * @property string|null $photo_path relative to the photos disk
 * @property CarbonImmutable|null $created_at
 */
#[Fillable(['course_name', 'tees', 'played_on', 'photo_path'])]
class Round extends Model
{
    /** @use HasFactory<RoundFactory> */
    use HasFactory, HasUuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'played_on' => 'immutable_date',
        ];
    }

    /**
     * @return HasMany<RoundHole, $this>
     */
    public function holes(): HasMany
    {
        return $this->hasMany(RoundHole::class)->orderBy('hole');
    }

    /**
     * The lines of the card, in their order.
     *
     * @return HasMany<RoundPlayer, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(RoundPlayer::class)->orderBy('position');
    }

    /**
     * @return HasMany<Score, $this>
     */
    public function scores(): HasMany
    {
        return $this->hasMany(Score::class)->orderBy('hole');
    }
}
