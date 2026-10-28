<?php

namespace App\Domain\Players\Actions;

use App\Domain\Players\Exceptions\DuplicatePlayer;
use App\Domain\Players\PlayerName;
use App\Models\Player;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Adds a player of the group, without alias.
 */
final class AddPlayer
{
    /**
     * @throws DuplicatePlayer when another player has this name, whatever the case
     */
    public function handle(string $name): Player
    {
        try {
            return DB::transaction(fn (): Player => Player::query()->create(['name' => PlayerName::name($name)]));
        } catch (UniqueConstraintViolationException) {
            // The unique index on lower(name) is the guard, the form request checks first.
            throw new DuplicatePlayer;
        }
    }
}
