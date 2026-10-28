<?php

namespace App\Domain\Players\Actions;

use App\Domain\Players\Exceptions\DuplicateInitialPlayer;
use App\Domain\Players\PlayerName;
use App\Models\Player;
use Illuminate\Support\Facades\DB;

/**
 * Creates the players of a comma separated list, each with its name upper case as alias, on a
 * base without any player. A name given twice, whatever the case, fails and writes nothing.
 */
final class CreateInitialPlayers
{
    public function __construct(
        private readonly AddPlayer $addPlayer,
        private readonly AssignAlias $assignAlias,
    ) {}

    /**
     * @return list<Player> the players created, none when the base already had one
     *
     * @throws DuplicateInitialPlayer
     */
    public function handle(string $raw): array
    {
        $names = array_values(array_filter(
            array_map(PlayerName::name(...), explode(',', $raw)),
            fn (string $name): bool => $name !== '',
        ));

        return DB::transaction(function () use ($names): array {
            // The list is checked whole even when the base already has its players.
            $create = ! Player::query()->exists();
            $seen = [];
            $created = [];

            foreach ($names as $name) {
                $key = mb_strtolower($name);

                if (isset($seen[$key])) {
                    throw new DuplicateInitialPlayer($name);
                }

                $seen[$key] = true;

                if ($create) {
                    $player = $this->addPlayer->handle($name);
                    $this->assignAlias->handle($player, $name);
                    $created[] = $player;
                }
            }

            return $created;
        });
    }
}
