<?php

namespace App\Domain\Rounds\Queries;

use App\Models\Player;
use App\Models\Round;
use App\Models\RoundPlayer;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * The players absent from the card of a round who played its course and tees (without case nor
 * surrounding spaces) within 7 days before or after it (spec virtual-match, « Suggestion sur la
 * page d'un round »; design D3). For each player, the closest round in date, then the latest by
 * date of play, of saving and id. Sorted by date of play, then by name. One query, nothing
 * written; played_on is a date, so no time zone is involved.
 */
final class VirtualMatchSuggestion
{
    /** The widest gap, in days, between the round and a suggested one. */
    private const int WINDOW_DAYS = 7;

    /**
     * @return list<array{roundId: string, playerId: string, name: string, colorSlot: int, playedOn: string}>
     */
    public function for(Round $round): array
    {
        $playedOn = $round->played_on->toDateString();

        $closest = RoundPlayer::query()
            ->toBase()
            ->join('rounds', 'rounds.id', '=', 'round_players.round_id')
            ->whereRaw('lower(trim(rounds.course_name)) = lower(trim(?))', [$round->course_name])
            ->whereRaw('lower(trim(rounds.tees)) = lower(trim(?))', [$round->tees])
            ->where('rounds.id', '!=', $round->id)
            ->whereRaw('abs(rounds.played_on - ?::date) <= ?', [$playedOn, self::WINDOW_DAYS])
            ->whereNotIn('round_players.player_id', RoundPlayer::query()
                ->toBase()
                ->select('player_id')
                ->where('round_id', $round->id))
            ->selectRaw('distinct on (round_players.player_id) round_players.round_id, round_players.player_id, rounds.played_on')
            ->orderBy('round_players.player_id')
            ->orderByRaw('abs(rounds.played_on - ?::date)', [$playedOn])
            ->orderByDesc('rounds.played_on')
            ->orderByDesc('rounds.created_at')
            ->orderByDesc('rounds.id');

        $suggestions = DB::query()
            ->fromSub($closest, 'closest')
            ->join('players', 'players.id', '=', 'closest.player_id')
            ->joinSub(Player::colorSlots(), 'player_slots', 'player_slots.id', '=', 'closest.player_id')
            ->orderBy('closest.played_on')
            ->orderBy('players.name')
            ->get(['closest.round_id as roundId', 'closest.player_id as playerId', 'players.name', 'player_slots.color_slot as colorSlot', 'closest.played_on as playedOn'])
            ->map(fn (stdClass $suggestion): array => [
                'roundId' => (string) $suggestion->roundId,
                'playerId' => (string) $suggestion->playerId,
                'name' => (string) $suggestion->name,
                'colorSlot' => (int) $suggestion->colorSlot,
                'playedOn' => (string) $suggestion->playedOn,
            ])
            ->all();

        return array_values($suggestions);
    }
}
