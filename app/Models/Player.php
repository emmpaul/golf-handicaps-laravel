<?php

namespace App\Models;

use Database\Factories\PlayerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A player of the group; its name is unique whatever the case (index on lower(name)).
 *
 * @property string $id
 * @property string $name
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
}
