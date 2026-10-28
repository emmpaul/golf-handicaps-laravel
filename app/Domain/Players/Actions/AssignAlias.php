<?php

namespace App\Domain\Players\Actions;

use App\Domain\Players\PlayerName;
use App\Models\Player;
use App\Models\PlayerAlias;
use Illuminate\Support\Facades\DB;

/**
 * Gives an alias to a player; the player who carried it, whatever the case, loses it.
 */
final class AssignAlias
{
    public function handle(Player $player, string $alias): PlayerAlias
    {
        $alias = PlayerName::alias($alias);

        return DB::transaction(function () use ($player, $alias): PlayerAlias {
            PlayerAlias::query()->whereRaw('lower(alias) = lower(?)', [$alias])->delete();

            return $player->aliases()->create(['alias' => $alias]);
        });
    }
}
