<?php

namespace App\Domain\Players\Queries;

use App\Models\Player;
use App\Models\PlayerAlias;

/**
 * The players of the group for the Players page: by name whatever the case, each with its
 * aliases in alphabetical order.
 */
final class PlayerOverview
{
    /**
     * @return list<array{id: string, name: string, aliases: list<array{id: string, alias: string}>}>
     */
    public function get(): array
    {
        return array_values(Player::query()
            ->with(['aliases' => fn ($query) => $query->orderBy('alias')])
            ->orderByRaw('lower(name)')
            ->orderBy('id')
            ->get()
            ->map(fn (Player $player): array => [
                'id' => $player->id,
                'name' => $player->name,
                'aliases' => array_values($player->aliases
                    ->map(fn (PlayerAlias $alias): array => ['id' => $alias->id, 'alias' => $alias->alias])
                    ->all()),
            ])
            ->all());
    }
}
