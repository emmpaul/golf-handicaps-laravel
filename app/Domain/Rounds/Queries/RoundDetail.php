<?php

namespace App\Domain\Rounds\Queries;

use App\Domain\Handicaps\Queries\RoundLeaderboard;
use App\Models\Round;
use App\Models\RoundHole;
use App\Models\RoundPlayer;
use App\Models\Score;

/**
 * The page of a round (spec rounds, « En-tête d'un round », « Classement net d'un round »,
 * « Rating affichée sur le round », « Carte de score d'un round », « Photo d'un round »):
 * header, total par, the net leaderboard (gross of each player in the order of the card), the
 * rating used, the card itself and whether the round has a photo.
 */
final class RoundDetail
{
    public function __construct(private readonly RoundLeaderboard $leaderboard) {}

    /**
     * @return array{
     *     id: string,
     *     courseName: string,
     *     tees: string,
     *     playedOn: string,
     *     par: int,
     *     hasPhoto: bool,
     *     leaderboard: list<array{playerId: string, name: string, gross: int, indexBeforeT: int|null, playingHandicap: int|null, net: int|null, rank: int|null}>,
     *     rating: array{rated: bool, ratingT: int, slope: int},
     *     card: array{
     *         holes: list<array{par: int, strokeIndex: int, distance: int|null}>,
     *         lines: list<array{playerId: string, name: string, strokes: list<int>}>,
     *     },
     * }
     */
    public function for(Round $round): array
    {
        $round->loadMissing(['holes', 'lines.player:id,name', 'scores']);
        // Read once for the page and its leaderboard.
        $rating = $this->leaderboard->rating($round);

        $lines = array_values($round->lines->map(fn (RoundPlayer $line): array => [
            'playerId' => $line->player_id,
            'name' => $line->player->name,
            'strokes' => array_values($round->scores
                ->where('player_id', $line->player_id)
                ->sortBy('hole')
                ->map(fn (Score $score): int => $score->strokes)
                ->all()),
        ])->all());

        return [
            'id' => $round->id,
            'courseName' => $round->course_name,
            'tees' => $round->tees,
            'playedOn' => $round->played_on->toDateString(),
            'par' => (int) $round->holes->sum('par'),
            'hasPhoto' => $round->photo_path !== null,
            'leaderboard' => $this->leaderboard->for($round, $rating),
            'rating' => $rating,
            'card' => [
                'holes' => array_values($round->holes->map(fn (RoundHole $hole): array => [
                    'par' => $hole->par,
                    'strokeIndex' => $hole->stroke_index,
                    'distance' => $hole->distance,
                ])->all()),
                'lines' => $lines,
            ],
        ];
    }
}
