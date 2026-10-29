<?php

namespace App\Domain\Imports;

use App\Domain\Players\PlayerNameMatcher;
use App\Domain\Rounds\RoundCard;
use App\Models\Import;
use App\Models\Player;
use Illuminate\Support\Collection;

/**
 * The opening state of the correction screen of an import (spec scorecard-import, « Reprise de
 * la correction enregistrée »; design D2): the saved correction when it is readable, otherwise
 * the draft (each read name matched to a player, the date of the day when none was read),
 * otherwise the empty screen of the day.
 *
 * `fromReading`: the card comes from the draft, its empty cells are highlighted at once.
 * `readByAi`: the AI gave a draft, whether the screen starts from it or from a correction.
 */
final class ImportEditorState
{
    /**
     * @param  string  $today  `Y-m-d` in Montreal
     * @return array{card: array<string, mixed>, fromReading: bool, readByAi: bool}
     */
    public static function for(Import $import, string $today): array
    {
        $players = Player::query()->with('aliases')->get();
        $draft = $import->draft === null ? null : ScorecardDraft::fromArray($import->draft);
        $correction = ImportCorrection::fromArray($import->correction);

        $card = match (true) {
            $correction !== null => self::withKnownPlayers($correction, $players),
            $draft !== null => self::fromDraft($draft, $players, $today),
            default => self::empty($today),
        };

        return [
            'card' => $card->toArray(),
            'fromReading' => $correction === null && $draft !== null,
            'readByAi' => $draft !== null,
        ];
    }

    /**
     * @param  Collection<int, Player>  $players
     */
    private static function fromDraft(ScorecardDraft $draft, Collection $players, string $today): RoundCard
    {
        $holes = [];
        foreach ($draft->par['holes'] as $i => $par) {
            $holes[] = ['par' => $par, 'strokeIndex' => $draft->strokeIndex[$i], 'distance' => $draft->distance['holes'][$i]];
        }

        $lines = array_map(function (array $player) use ($players): array {
            $name = $player['name'] === null || trim($player['name']) === '' ? null : $player['name'];

            return [
                'playerId' => $name === null ? null : PlayerNameMatcher::match($name, $players)?->playerId,
                'strokes' => $player['scores']['holes'],
                ...($name === null ? [] : ['readName' => $name]),
                'totals' => self::totals($player['scores']),
            ];
        }, $draft->players);

        return new RoundCard(
            courseName: $draft->courseName,
            tees: $draft->tees,
            playedOn: $draft->playedOn ?? $today,
            holes: $holes,
            lines: $lines,
            parTotals: self::totals($draft->par),
        );
    }

    /**
     * A player deleted since the correction was saved leaves its line without player.
     *
     * @param  Collection<int, Player>  $players
     */
    private static function withKnownPlayers(RoundCard $card, Collection $players): RoundCard
    {
        $known = $players->pluck('id')->all();

        return new RoundCard(
            courseName: $card->courseName,
            tees: $card->tees,
            playedOn: $card->playedOn,
            holes: $card->holes,
            lines: array_map(fn (array $line): array => [
                ...$line,
                'playerId' => in_array($line['playerId'], $known, true) ? $line['playerId'] : null,
            ], $card->lines),
            parTotals: $card->parTotals,
        );
    }

    /**
     * The empty screen of a failed import: one line, the date of the day, every cell empty.
     */
    private static function empty(string $today): RoundCard
    {
        return new RoundCard(
            courseName: null,
            tees: null,
            playedOn: $today,
            holes: array_fill(0, ScorecardDraft::HOLES, ['par' => null, 'strokeIndex' => null, 'distance' => null]),
            lines: [['playerId' => null, 'strokes' => array_fill(0, ScorecardDraft::HOLES, null)]],
        );
    }

    /**
     * @param  array{out: int|null, in: int|null, total: int|null}  $row
     * @return array{out: int|null, in: int|null, total: int|null}
     */
    private static function totals(array $row): array
    {
        return ['out' => $row['out'], 'in' => $row['in'], 'total' => $row['total']];
    }
}
