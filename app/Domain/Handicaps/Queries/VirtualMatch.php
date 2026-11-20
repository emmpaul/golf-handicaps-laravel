<?php

namespace App\Domain\Handicaps\Queries;

use App\Domain\Handicaps\NetRanking;
use App\Domain\Handicaps\Pick;
use App\Domain\Handicaps\RatedCourse;
use App\Domain\Handicaps\Whs;
use App\Models\CourseRating;
use App\Models\Player;
use App\Models\RoundHole;
use App\Models\RoundPlayer;
use Illuminate\Database\Query\Builder;
use stdClass;

/**
 * The virtual match of a course and tees (spec virtual-match, design D2 and D3): the player
 * rounds of the course to add, and the picked ones ranked on net as on the page of their round,
 * each playing handicap from the index just before its round, the current rating and the par
 * total of its own round. The picks that are not player rounds of the course, or whose player is
 * already picked, are ignored. Three queries: the player rounds, the holes and scores of the
 * picks, the rating. Nothing is written.
 *
 * @phpstan-type PlayerRound array{roundId: string, playerId: string, name: string, colorSlot: int, playedOn: string, gross: int, indexBeforeT: int|null}
 */
final class VirtualMatch
{
    /**
     * @param  list<Pick>  $picks  in the order of the choice
     * @return array{
     *     courseName: string,
     *     tees: string,
     *     rating: array{rated: bool, ratingT: int|null, slope: int},
     *     candidates: list<array{roundId: string, playerId: string, name: string, colorSlot: int, playedOn: string, partners: list<string>, gross: int}>,
     *     picks: list<array{roundId: string, playerId: string, name: string, colorSlot: int, playedOn: string, strokes: list<int>}>,
     *     leaderboard: list<array{roundId: string, playerId: string, name: string, colorSlot: int, playedOn: string, gross: int, indexBeforeT: int|null, playingHandicap: int|null, net: int|null, rank: int|null}>,
     *     holes: list<array{par: int, strokeIndex: int}>,
     *     holesDiffer: bool,
     * }
     */
    public function for(string $courseName, string $tees, array $picks): array
    {
        $playerRounds = $this->playerRounds($courseName, $tees);
        $chosen = $this->chosen($playerRounds, $picks);
        [$holes, $strokes] = $this->holesAndStrokes($chosen);
        $rating = $this->rating($courseName, $tees);

        // The latest round picked by date of play then of saving: the first in the order of
        // the player rounds.
        $chosenRounds = array_column($chosen, 'roundId');
        $latest = array_find($playerRounds, fn (array $line): bool => in_array($line['roundId'], $chosenRounds, true));
        $latestHoles = $latest === null ? [] : $holes[$latest['roundId']];

        return [
            'courseName' => $courseName,
            'tees' => $tees,
            'rating' => $rating,
            'candidates' => array_map(fn (array $line): array => [
                'roundId' => $line['roundId'],
                'playerId' => $line['playerId'],
                'name' => $line['name'],
                'colorSlot' => $line['colorSlot'],
                'playedOn' => $line['playedOn'],
                'partners' => array_values(array_map(
                    fn (array $partner): string => $partner['name'],
                    array_filter($playerRounds, fn (array $partner): bool => $partner['roundId'] === $line['roundId'] && $partner['playerId'] !== $line['playerId']),
                )),
                'gross' => $line['gross'],
            ], $playerRounds),
            'picks' => array_map(fn (array $line): array => [
                'roundId' => $line['roundId'],
                'playerId' => $line['playerId'],
                'name' => $line['name'],
                'colorSlot' => $line['colorSlot'],
                'playedOn' => $line['playedOn'],
                'strokes' => $strokes[$line['roundId']][$line['playerId']],
            ], $chosen),
            'leaderboard' => $this->leaderboard($chosen, $holes, $rating),
            'holes' => $latestHoles,
            'holesDiffer' => array_any($chosen, fn (array $line): bool => $holes[$line['roundId']] !== $latestHoles),
        ];
    }

    /**
     * Every line of a round on the course and tees, without case nor surrounding spaces, the
     * latest round first by date of play then of saving, each round in the order of its card.
     *
     * @return list<PlayerRound>
     */
    private function playerRounds(string $courseName, string $tees): array
    {
        return array_values(RoundPlayer::query()
            ->join('rounds', 'rounds.id', '=', 'round_players.round_id')
            ->join('players', 'players.id', '=', 'round_players.player_id')
            ->joinSub(Player::colorSlots(), 'player_slots', 'player_slots.id', '=', 'round_players.player_id')
            ->join('player_round_stats', fn ($join) => $join
                ->on('player_round_stats.round_id', '=', 'round_players.round_id')
                ->on('player_round_stats.player_id', '=', 'round_players.player_id'))
            ->whereRaw('lower(trim(rounds.course_name)) = lower(trim(?))', [$courseName])
            ->whereRaw('lower(trim(rounds.tees)) = lower(trim(?))', [$tees])
            ->orderByDesc('rounds.played_on')
            ->orderByDesc('rounds.created_at')
            ->orderByDesc('rounds.id')
            ->orderBy('round_players.position')
            ->toBase()
            ->get(['round_players.round_id', 'round_players.player_id', 'players.name', 'player_slots.color_slot', 'rounds.played_on', 'player_round_stats.gross', 'player_round_stats.index_before_t'])
            ->map(fn (stdClass $row): array => [
                'roundId' => (string) $row->round_id,
                'playerId' => (string) $row->player_id,
                'name' => (string) $row->name,
                'colorSlot' => (int) $row->color_slot,
                'playedOn' => (string) $row->played_on,
                'gross' => (int) $row->gross,
                'indexBeforeT' => $row->index_before_t === null ? null : (int) $row->index_before_t,
            ])
            ->all());
    }

    /**
     * The player rounds picked, in the order of the picks, at most one per player.
     *
     * @param  list<PlayerRound>  $playerRounds
     * @param  list<Pick>  $picks
     * @return list<PlayerRound>
     */
    private function chosen(array $playerRounds, array $picks): array
    {
        $byPick = [];
        foreach ($playerRounds as $line) {
            $byPick[$line['roundId'].'.'.$line['playerId']] = $line;
        }

        $chosen = [];
        foreach ($picks as $pick) {
            $line = $byPick[$pick->roundId.'.'.$pick->playerId] ?? null;
            if ($line !== null && ! array_key_exists($line['playerId'], $chosen)) {
                $chosen[$line['playerId']] = $line;
            }
        }

        return array_values($chosen);
    }

    /**
     * The holes of each round picked and the strokes of each player round picked, in one query.
     *
     * @param  list<PlayerRound>  $chosen
     * @return array{array<string, list<array{par: int, strokeIndex: int}>>, array<string, array<string, list<int>>>}
     */
    private function holesAndStrokes(array $chosen): array
    {
        if ($chosen === []) {
            return [[], []];
        }

        $rows = RoundHole::query()
            ->toBase()
            ->join('scores', fn ($join) => $join
                ->on('scores.round_id', '=', 'round_holes.round_id')
                ->on('scores.hole', '=', 'round_holes.hole'))
            ->where(function (Builder $query) use ($chosen): void {
                foreach ($chosen as $line) {
                    $query->orWhere(fn (Builder $pair) => $pair
                        ->where('scores.round_id', $line['roundId'])
                        ->where('scores.player_id', $line['playerId']));
                }
            })
            ->orderBy('round_holes.hole')
            ->get(['round_holes.round_id', 'round_holes.hole', 'round_holes.par', 'round_holes.stroke_index', 'scores.player_id', 'scores.strokes']);

        $holes = [];
        $strokes = [];
        foreach ($rows as $row) {
            $holes[$row->round_id][$row->hole - 1] = ['par' => (int) $row->par, 'strokeIndex' => (int) $row->stroke_index];
            $strokes[$row->round_id][$row->player_id][] = (int) $row->strokes;
        }

        return [array_map(array_values(...), $holes), $strokes];
    }

    /**
     * The confirmed rating of the course and tees, compared in SQL as RoundLeaderboard::rating;
     * without it, each round is rated on its own par total and 113.
     *
     * @return array{rated: bool, ratingT: int|null, slope: int}
     */
    private function rating(string $courseName, string $tees): array
    {
        $confirmed = CourseRating::query()
            ->whereRaw('lower(trim(course_name)) = lower(trim(?))', [$courseName])
            ->whereRaw('lower(trim(tees)) = lower(trim(?))', [$tees])
            ->first(['rating_t', 'slope']);

        // The CHECKs of course_ratings set rating_t and slope together.
        return $confirmed?->rating_t === null || $confirmed->slope === null
            ? ['rated' => false, 'ratingT' => null, 'slope' => 113]
            : ['rated' => true, 'ratingT' => $confirmed->rating_t, 'slope' => $confirmed->slope];
    }

    /**
     * The picks ranked by net, ties and lines without index in the order of the picks.
     *
     * @param  list<PlayerRound>  $chosen
     * @param  array<string, list<array{par: int, strokeIndex: int}>>  $holes
     * @param  array{rated: bool, ratingT: int|null, slope: int}  $rating
     * @return list<array{roundId: string, playerId: string, name: string, colorSlot: int, playedOn: string, gross: int, indexBeforeT: int|null, playingHandicap: int|null, net: int|null, rank: int|null}>
     */
    private function leaderboard(array $chosen, array $holes, array $rating): array
    {
        $lines = array_map(function (array $line) use ($holes, $rating): array {
            $par = array_sum(array_column($holes[$line['roundId']], 'par'));
            $indexBeforeT = $line['indexBeforeT'];
            // Without a confirmed rating, par × 10 and 113 as on the page of the round.
            $handicap = $indexBeforeT === null ? null : Whs::courseHandicap($indexBeforeT, new RatedCourse(
                $rating['ratingT'] ?? $par * 10,
                $rating['slope'],
                $par,
            ));

            return [
                'roundId' => $line['roundId'],
                'playerId' => $line['playerId'],
                'name' => $line['name'],
                'colorSlot' => $line['colorSlot'],
                'playedOn' => $line['playedOn'],
                'gross' => $line['gross'],
                'indexBeforeT' => $indexBeforeT,
                'playingHandicap' => $handicap,
                'net' => $handicap === null ? null : $line['gross'] - $handicap,
            ];
        }, $chosen);

        $ranks = NetRanking::competitionRanks(array_column($lines, 'net'));
        $ranked = array_map(fn (array $line, ?int $rank): array => [...$line, 'rank' => $rank], $lines, $ranks);

        // usort is stable: equal ranks keep the order of the picks.
        usort($ranked, fn (array $a, array $b): int => ($a['rank'] ?? PHP_INT_MAX) <=> ($b['rank'] ?? PHP_INT_MAX));

        return $ranked;
    }
}
