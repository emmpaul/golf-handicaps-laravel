<?php

namespace App\Domain\Rounds\Queries;

use App\Domain\Handicaps\Queries\RoundLeaderboard;
use App\Models\Round;

/**
 * The latest round of the home page (spec home-dashboard, « Dernier round »): the most recent by
 * date of play, date of saving then id, with its course, its tees and its net leaderboard in the
 * order of the card, as the page of the round ranks it (DO6 of visual-redesign).
 */
final class LatestRound
{
    public function __construct(private readonly RoundLeaderboard $leaderboard) {}

    /**
     * @return array{id: string, playedOn: string, courseName: string, tees: string, leaderboard: list<array{playerId: string, name: string, colorSlot: int, gross: int, indexBeforeT: int|null, playingHandicap: int|null, net: int|null, rank: int|null}>}|null
     */
    public function get(): ?array
    {
        $round = Round::query()
            ->orderByDesc('played_on')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();

        if ($round === null) {
            return null;
        }

        return [
            'id' => $round->id,
            'playedOn' => $round->played_on->toDateString(),
            'courseName' => $round->course_name,
            'tees' => $round->tees,
            'leaderboard' => $this->leaderboard->for($round),
        ];
    }
}
