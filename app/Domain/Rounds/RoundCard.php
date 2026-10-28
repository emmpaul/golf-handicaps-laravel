<?php

namespace App\Domain\Rounds;

use App\Models\Round;
use App\Models\RoundHole;
use App\Models\RoundPlayer;
use App\Models\Score;

/**
 * The card of a round as typed or corrected, before it is saved: any cell may still be empty.
 * RoundCardChecks says whether it can be saved.
 *
 * @phpstan-type Hole array{par: int|null, strokeIndex: int|null, distance: int|null}
 * @phpstan-type Line array{playerId: string|null, strokes: list<int|null>}
 */
final readonly class RoundCard
{
    /**
     * @param  string|null  $playedOn  `Y-m-d`
     * @param  list<Hole>  $holes  the 18 holes, hole 1 first
     * @param  list<Line>  $lines  the lines of players in the order of the card, 18 strokes each
     */
    public function __construct(
        public ?string $courseName,
        public ?string $tees,
        public ?string $playedOn,
        public array $holes,
        public array $lines,
    ) {}

    /**
     * @param  array{courseName?: string|null, tees?: string|null, playedOn?: string|null, holes: list<Hole>, lines: list<Line>}  $card  the validated card, keyed as the editor sends it
     */
    public static function fromArray(array $card): self
    {
        return new self(
            courseName: $card['courseName'] ?? null,
            tees: $card['tees'] ?? null,
            playedOn: $card['playedOn'] ?? null,
            holes: array_map(fn (array $hole): array => [
                'par' => $hole['par'],
                'strokeIndex' => $hole['strokeIndex'],
                'distance' => $hole['distance'],
            ], $card['holes']),
            lines: array_map(fn (array $line): array => [
                'playerId' => $line['playerId'],
                'strokes' => $line['strokes'],
            ], $card['lines']),
        );
    }

    /**
     * The card keyed as the editor sends it, the inverse of fromArray().
     *
     * @return array{courseName: string|null, tees: string|null, playedOn: string|null, holes: list<Hole>, lines: list<Line>}
     */
    public function toArray(): array
    {
        return [
            'courseName' => $this->courseName,
            'tees' => $this->tees,
            'playedOn' => $this->playedOn,
            'holes' => $this->holes,
            'lines' => $this->lines,
        ];
    }

    /**
     * The card of a saved round, to correct it or to check it again.
     */
    public static function fromRound(Round $round): self
    {
        $round->loadMissing(['holes', 'lines', 'scores']);

        return new self(
            courseName: $round->course_name,
            tees: $round->tees,
            playedOn: $round->played_on->toDateString(),
            holes: array_values($round->holes->map(fn (RoundHole $hole): array => [
                'par' => $hole->par,
                'strokeIndex' => $hole->stroke_index,
                'distance' => $hole->distance,
            ])->all()),
            lines: array_values($round->lines->map(fn (RoundPlayer $line): array => [
                'playerId' => $line->player_id,
                'strokes' => array_values($round->scores
                    ->where('player_id', $line->player_id)
                    ->sortBy('hole')
                    ->map(fn (Score $score): int => $score->strokes)
                    ->all()),
            ])->all()),
        );
    }
}
