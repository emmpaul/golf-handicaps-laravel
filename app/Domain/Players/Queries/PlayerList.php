<?php

namespace App\Domain\Players\Queries;

use App\Models\Player;

/**
 * The players a line of the round editor can choose: by name whatever the case, each with its
 * colour slot (design D6), the colour of its column in the grid.
 */
final class PlayerList
{
    /**
     * @return list<array{id: string, name: string, colorSlot: int}>
     */
    public function get(): array
    {
        return array_values(Player::query()
            ->select(['players.id', 'players.name'])
            ->withColorSlot()
            ->orderByRaw('lower(players.name)')
            ->orderBy('players.id')
            ->get()
            ->map(fn (Player $player): array => [
                'id' => $player->id,
                'name' => $player->name,
                'colorSlot' => (int) $player->color_slot,
            ])
            ->all());
    }
}
