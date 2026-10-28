<?php

namespace App\Domain\Players\Actions;

use App\Domain\Players\Exceptions\DuplicatePlayer;
use App\Domain\Players\PlayerName;
use App\Models\Player;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Renames a player; its rounds and aliases stay as they are.
 */
final class RenamePlayer
{
    /**
     * @throws DuplicatePlayer when another player has this name, whatever the case
     */
    public function handle(Player $player, string $name): Player
    {
        try {
            DB::transaction(fn (): bool => $player->update(['name' => PlayerName::name($name)]));
        } catch (UniqueConstraintViolationException) {
            $player->refresh();

            throw new DuplicatePlayer;
        }

        return $player;
    }
}
