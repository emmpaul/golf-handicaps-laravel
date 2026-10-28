<?php

namespace App\Domain\Players\Queries;

use App\Models\Player;
use App\Models\PlayerAlias;
use App\Models\PlayerRoundStat;
use Illuminate\Support\Collection;

/**
 * The players of the group for the Players page: by name whatever the case, each with its
 * aliases in alphabetical order and its current index (spec players, « Index sur la page
 * Joueurs »): the one just after its latest round by date of play, date of saving then id,
 * provisional below 3 rounds, null without round. Port of playerOverviews of the Next.js
 * application.
 */
final class PlayerOverview
{
    /** Below 3 rounds, the index is provisional (spec handicap-index). */
    private const int PROVISIONAL_BELOW = 3;

    /**
     * @return list<array{id: string, name: string, aliases: list<array{id: string, alias: string}>, indexT: int|null, provisional: bool}>
     */
    public function get(): array
    {
        $indexes = $this->currentIndexes();

        return array_values(Player::query()
            ->with(['aliases' => fn ($query) => $query->orderBy('alias')])
            ->orderByRaw('lower(name)')
            ->orderBy('id')
            ->get()
            ->map(function (Player $player) use ($indexes): array {
                $index = $indexes->get($player->id);
                $rounds = $index === null ? 0 : (int) $index->rounds;

                return [
                    'id' => $player->id,
                    'name' => $player->name,
                    'aliases' => array_values($player->aliases
                        ->map(fn (PlayerAlias $alias): array => ['id' => $alias->id, 'alias' => $alias->alias])
                        ->all()),
                    'indexT' => $index === null ? null : (int) $index->index_after_t,
                    'provisional' => $rounds > 0 && $rounds < self::PROVISIONAL_BELOW,
                ];
            })
            ->all());
    }

    /**
     * Per player with at least one round: the index after its latest round played and its
     * number of rounds, in one query.
     *
     * @return Collection<string, object{player_id: string, index_after_t: int, rounds: int}>
     */
    private function currentIndexes(): Collection
    {
        /** @var Collection<string, object{player_id: string, index_after_t: int, rounds: int}> */
        return PlayerRoundStat::query()
            ->toBase()
            ->join('rounds', 'rounds.id', '=', 'player_round_stats.round_id')
            ->selectRaw('distinct on (player_round_stats.player_id) player_round_stats.player_id, player_round_stats.index_after_t')
            ->selectRaw('count(*) over (partition by player_round_stats.player_id) as rounds')
            ->orderBy('player_round_stats.player_id')
            ->orderByDesc('rounds.played_on')
            ->orderByDesc('rounds.created_at')
            ->orderByDesc('rounds.id')
            ->get()
            ->keyBy('player_id');
    }
}
