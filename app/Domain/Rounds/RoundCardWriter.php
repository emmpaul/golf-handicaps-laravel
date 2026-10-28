<?php

namespace App\Domain\Rounds;

use App\Models\Round;
use App\Models\RoundHole;
use App\Models\RoundPlayer;
use App\Models\Score;
use Illuminate\Support\Str;

/**
 * Writes the columns, holes, lines and scores of a coherent card to a round, inside the
 * transaction of the calling Action. RoundCardChecks::ensureCoherent() runs first: no required
 * cell is empty here.
 */
final class RoundCardWriter
{
    public function write(Round $round, RoundCard $card): void
    {
        $round->fill([
            'course_name' => trim((string) $card->courseName),
            'tees' => trim((string) $card->tees),
            'played_on' => $card->playedOn,
        ])->save();

        RoundHole::query()->insert(array_map(fn (array $hole, int $i): array => [
            'id' => (string) Str::uuid7(),
            'round_id' => $round->id,
            'hole' => $i + 1,
            'par' => $hole['par'],
            'stroke_index' => $hole['strokeIndex'],
            'distance' => $hole['distance'],
        ], $card->holes, array_keys($card->holes)));

        $lines = [];
        $scores = [];
        foreach ($card->lines as $position => $line) {
            $lines[] = [
                'id' => (string) Str::uuid7(),
                'round_id' => $round->id,
                'player_id' => $line['playerId'],
                'position' => $position,
            ];

            foreach ($line['strokes'] as $i => $strokes) {
                $scores[] = [
                    'id' => (string) Str::uuid7(),
                    'round_id' => $round->id,
                    'player_id' => $line['playerId'],
                    'hole' => $i + 1,
                    'strokes' => $strokes,
                ];
            }
        }

        RoundPlayer::query()->insert($lines);
        Score::query()->insert($scores);

        $round->unsetRelation('holes')->unsetRelation('lines')->unsetRelation('scores');
    }
}
