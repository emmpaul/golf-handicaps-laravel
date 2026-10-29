<?php

namespace App\Domain\Handicaps\Queries;

use App\Models\Player;
use App\Models\PlayerRoundStat;
use Illuminate\Support\Collection;

/**
 * The players of the group with their current index (spec home-dashboard, « Index des
 * joueurs »): by name whatever the case, the index just after the latest round by date of play,
 * date of saving then id, provisional below 3 rounds, and the delta with the index before that
 * round, null without round or on a first round. Port of the players of homeSummary of the
 * Next.js application. Two queries.
 */
final class PlayerStandings
{
    /** Below 3 rounds, the index is provisional (spec handicap-index). */
    public const int PROVISIONAL_BELOW = 3;

    /**
     * @return list<array{id: string, name: string, indexT: int|null, provisional: bool, deltaT: int|null}>
     */
    public function get(): array
    {
        $stats = $this->currentStats();

        return array_values(Player::query()
            ->orderByRaw('lower(name)')
            ->orderBy('id')
            ->get(['id', 'name'])
            ->map(function (Player $player) use ($stats): array {
                $stat = $stats->get($player->id);

                return [
                    'id' => $player->id,
                    'name' => $player->name,
                    'indexT' => $stat === null ? null : (int) $stat->index_after_t,
                    'provisional' => self::provisional($stat),
                    'deltaT' => $stat === null || $stat->index_before_t === null
                        ? null
                        : (int) $stat->index_after_t - (int) $stat->index_before_t,
                ];
            })
            ->all());
    }

    /**
     * A player with 1 or 2 rounds has a provisional index; without round, no index at all.
     *
     * @param  object{rounds: int}|null  $stat
     */
    public static function provisional(?object $stat): bool
    {
        $rounds = $stat === null ? 0 : (int) $stat->rounds;

        return $rounds > 0 && $rounds < self::PROVISIONAL_BELOW;
    }

    /**
     * Per player with at least one round: the index before and after its latest round played
     * and its number of rounds, in one query.
     *
     * @return Collection<string, object{player_id: string, index_before_t: int|null, index_after_t: int, rounds: int}>
     */
    public function currentStats(): Collection
    {
        /** @var Collection<string, object{player_id: string, index_before_t: int|null, index_after_t: int, rounds: int}> */
        return PlayerRoundStat::query()
            ->toBase()
            ->join('rounds', 'rounds.id', '=', 'player_round_stats.round_id')
            ->selectRaw('distinct on (player_round_stats.player_id) player_round_stats.player_id, player_round_stats.index_before_t, player_round_stats.index_after_t')
            ->selectRaw('count(*) over (partition by player_round_stats.player_id) as rounds')
            ->orderBy('player_round_stats.player_id')
            ->orderByDesc('rounds.played_on')
            ->orderByDesc('rounds.created_at')
            ->orderByDesc('rounds.id')
            ->get()
            ->keyBy('player_id');
    }
}
