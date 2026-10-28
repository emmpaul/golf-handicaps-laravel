<?php

namespace App\Domain\Rounds\Queries;

use App\Models\Round;
use App\Models\RoundPlayer;
use App\Models\Score;

/**
 * The rounds of the Rounds page (spec rounds, « Liste des rounds »): from the latest to the
 * oldest by date of play, then by date of saving, each with the gross of its players in the
 * order of the card. Four queries whatever the number of rounds.
 */
final class RoundList
{
    /**
     * @return list<array{id: string, playedOn: string, courseName: string, tees: string, players: list<array{name: string, gross: int}>}>
     */
    public function get(): array
    {
        $rounds = Round::query()
            ->with('lines.player:id,name')
            ->orderByDesc('played_on')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        $gross = Score::query()
            ->toBase()
            ->selectRaw('round_id, player_id, sum(strokes) as gross')
            ->groupBy('round_id', 'player_id')
            ->get()
            ->mapWithKeys(fn (object $row): array => ["{$row->round_id}:{$row->player_id}" => (int) $row->gross]);

        return array_values($rounds->map(fn (Round $round): array => [
            'id' => $round->id,
            'playedOn' => $round->played_on->toDateString(),
            'courseName' => $round->course_name,
            'tees' => $round->tees,
            'players' => array_values($round->lines->map(fn (RoundPlayer $line): array => [
                'name' => $line->player->name,
                'gross' => $gross->get("{$round->id}:{$line->player_id}", 0),
            ])->all()),
        ])->all());
    }
}
