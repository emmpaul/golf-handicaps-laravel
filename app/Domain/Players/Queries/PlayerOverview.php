<?php

namespace App\Domain\Players\Queries;

use App\Domain\Handicaps\Queries\PlayerStandings;
use App\Models\Player;
use App\Models\PlayerAlias;

/**
 * The players of the group for the Players page: by name whatever the case, each with its
 * aliases in alphabetical order and its current index (spec players, « Index sur la page
 * Joueurs »): the one just after its latest round by date of play, date of saving then id,
 * provisional below 3 rounds, null without round. Port of playerOverviews of the Next.js
 * application.
 */
final class PlayerOverview
{
    public function __construct(private readonly PlayerStandings $standings) {}

    /**
     * @return list<array{id: string, name: string, aliases: list<array{id: string, alias: string}>, indexT: int|null, provisional: bool}>
     */
    public function get(): array
    {
        $indexes = $this->standings->currentStats();

        return array_values(Player::query()
            ->with(['aliases' => fn ($query) => $query->orderBy('alias')])
            ->orderByRaw('lower(name)')
            ->orderBy('id')
            ->get()
            ->map(function (Player $player) use ($indexes): array {
                $index = $indexes->get($player->id);

                return [
                    'id' => $player->id,
                    'name' => $player->name,
                    'aliases' => array_values($player->aliases
                        ->map(fn (PlayerAlias $alias): array => ['id' => $alias->id, 'alias' => $alias->alias])
                        ->all()),
                    'indexT' => $index === null ? null : (int) $index->index_after_t,
                    'provisional' => PlayerStandings::provisional($index),
                ];
            })
            ->all());
    }
}
