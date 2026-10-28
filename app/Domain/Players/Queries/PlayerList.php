<?php

namespace App\Domain\Players\Queries;

use App\Models\Player;

/**
 * The players a line of the round editor can choose: by name whatever the case.
 */
final class PlayerList
{
    /**
     * @return list<array{id: string, name: string}>
     */
    public function get(): array
    {
        return array_values(Player::query()
            ->orderByRaw('lower(name)')
            ->orderBy('id')
            ->get(['id', 'name'])
            ->map(fn (Player $player): array => ['id' => $player->id, 'name' => $player->name])
            ->all());
    }
}
