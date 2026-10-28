<?php

namespace App\Domain\Rounds;

use App\Domain\Rounds\Exceptions\IncoherentRoundCard;

/**
 * The coherence checks of a round card (spec rounds, « Contrôles de cohérence »), replayed by
 * every write before it touches the base. resources/js/lib/round-checks.ts runs the same checks
 * in the editor; tests/fixtures/round-checks.json keeps the two aligned.
 */
final class RoundCardChecks
{
    public const int MAX_PLAYERS = 4;

    /** Message of a required cell left empty. */
    public const string EMPTY = 'Case vide';

    /** Message of a line without a player. */
    public const string UNASSIGNED = 'Ligne sans joueur';

    /**
     * The problems of the card, in the order the editor lists them; empty when it can be saved.
     *
     * @return list<Problem>
     */
    public static function problems(RoundCard $card): array
    {
        return [
            ...self::fieldProblems(['courseName' => $card->courseName, 'tees' => $card->tees]),
            ...self::cellProblems('par', array_column($card->holes, 'par'), 3, 5, 'Par'),
            ...self::strokeIndexProblems(array_column($card->holes, 'strokeIndex')),
            ...self::distanceProblems(array_column($card->holes, 'distance')),
            ...self::playerCountProblems(count($card->lines)),
            ...self::scoreProblems($card->lines),
            ...self::fieldProblems(['playedOn' => $card->playedOn]),
            ...self::unassignedLineProblems($card->lines),
            ...self::duplicatePlayerProblems($card->lines),
        ];
    }

    /**
     * @throws IncoherentRoundCard when the card has a problem
     */
    public static function ensureCoherent(RoundCard $card): void
    {
        $problems = self::problems($card);

        if ($problems !== []) {
            throw new IncoherentRoundCard($problems);
        }
    }

    /**
     * Label of the save button and of the refusal: « 1 problème à corriger ».
     */
    public static function problemCountLabel(int $count): string
    {
        return $count.' '.($count > 1 ? 'problèmes' : 'problème').' à corriger';
    }

    private static function holeKey(string $row, int $hole): string
    {
        return $row.':'.($hole + 1);
    }

    /**
     * @param  array<string, string|null>  $fields
     * @return list<Problem>
     */
    private static function fieldProblems(array $fields): array
    {
        $problems = [];
        foreach ($fields as $field => $value) {
            if (trim($value ?? '') === '') {
                $problems[] = new Problem(self::EMPTY, [$field]);
            }
        }

        return $problems;
    }

    /**
     * Required cells: empty or out of bounds, one problem per cell.
     *
     * @param  array<int, int|null>  $values
     * @return list<Problem>
     */
    private static function cellProblems(string $row, array $values, int $min, int $max, string $label): array
    {
        $problems = [];
        foreach (array_values($values) as $hole => $value) {
            $cells = [self::holeKey($row, $hole)];
            if ($value === null) {
                $problems[] = new Problem(self::EMPTY, $cells);
            } elseif ($value < $min || $value > $max) {
                $problems[] = new Problem("{$label} {$value} hors de {$min} à {$max}", $cells);
            }
        }

        return $problems;
    }

    /**
     * The indexes form a permutation of 1 to 18: each duplicate highlights all its cells.
     *
     * @param  array<int, int|null>  $indexes
     * @return list<Problem>
     */
    private static function strokeIndexProblems(array $indexes): array
    {
        $problems = self::cellProblems('strokeIndex', $indexes, 1, 18, 'Index');

        $holesByIndex = [];
        foreach (array_values($indexes) as $hole => $value) {
            if ($value !== null) {
                $holesByIndex[$value][] = $hole;
            }
        }

        foreach ($holesByIndex as $value => $holes) {
            if (count($holes) > 1) {
                $problems[] = new Problem(
                    "Index {$value} en double",
                    array_map(fn (int $hole): string => self::holeKey('strokeIndex', $hole), $holes),
                );
            }
        }

        return $problems;
    }

    /**
     * The distance is optional: only a filled cell out of 1 to 999 is a problem.
     *
     * @param  array<int, int|null>  $distances
     * @return list<Problem>
     */
    private static function distanceProblems(array $distances): array
    {
        $problems = [];
        foreach (array_values($distances) as $hole => $distance) {
            if ($distance !== null && ($distance < 1 || $distance > 999)) {
                $problems[] = new Problem("Distance {$distance} hors de 1 à 999", [self::holeKey('distance', $hole)]);
            }
        }

        return $problems;
    }

    /**
     * @return list<Problem>
     */
    private static function playerCountProblems(int $count): array
    {
        return match (true) {
            $count === 0 => [new Problem('Aucune ligne de joueur', [])],
            $count > self::MAX_PLAYERS => [new Problem(self::MAX_PLAYERS.' joueurs au plus', [])],
            default => [],
        };
    }

    /**
     * @param  list<array{playerId: string|null, strokes: list<int|null>}>  $lines
     * @return list<Problem>
     */
    private static function scoreProblems(array $lines): array
    {
        $problems = [];
        foreach ($lines as $position => $line) {
            array_push($problems, ...self::cellProblems("player{$position}", $line['strokes'], 1, 15, 'Score'));
        }

        return $problems;
    }

    /**
     * @param  list<array{playerId: string|null, strokes: list<int|null>}>  $lines
     * @return list<Problem>
     */
    private static function unassignedLineProblems(array $lines): array
    {
        $problems = [];
        foreach ($lines as $position => $line) {
            if ($line['playerId'] === null) {
                $problems[] = new Problem(self::UNASSIGNED, ["player{$position}:player"]);
            }
        }

        return $problems;
    }

    /**
     * @param  list<array{playerId: string|null, strokes: list<int|null>}>  $lines
     * @return list<Problem>
     */
    private static function duplicatePlayerProblems(array $lines): array
    {
        $positionsByPlayer = [];
        foreach ($lines as $position => $line) {
            if ($line['playerId'] !== null) {
                $positionsByPlayer[$line['playerId']][] = $position;
            }
        }

        $problems = [];
        foreach ($positionsByPlayer as $positions) {
            if (count($positions) > 1) {
                $problems[] = new Problem(
                    'Joueur en double',
                    array_map(fn (int $position): string => "player{$position}:player", $positions),
                );
            }
        }

        return $problems;
    }
}
