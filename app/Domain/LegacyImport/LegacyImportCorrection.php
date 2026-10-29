<?php

namespace App\Domain\LegacyImport;

use App\Domain\Imports\ImportCorrection;

/**
 * The correction Next saved for an import still to check (savedCorrectionSchema of
 * src/scorecard/form-schema.ts), in the shape of the port (design D10): the holes gathered from
 * the rows of the card, each line on the UUID of its player, the totals only where Next read
 * them. aiCells is dropped (decision 3 of the port).
 */
final class LegacyImportCorrection
{
    /**
     * The correction of the port, { version: 1, card }, or null when the correction of Next has
     * another shape or no line.
     *
     * @param  array<mixed>  $next
     * @return array{version: int, card: array<string, mixed>}|null
     */
    public static function translate(array $next, IdMap $ids): ?array
    {
        $card = $next['card'] ?? null;
        $lines = $next['lines'] ?? null;
        if (! is_array($card) || ! is_array($lines)
            || ! is_array($card['par'] ?? null) || ! is_array($card['par']['holes'] ?? null)
            || ! is_array($card['strokeIndex'] ?? null)
            || ! is_array($card['distance'] ?? null) || ! is_array($card['distance']['holes'] ?? null)) {
            return null;
        }

        $holes = [];
        foreach ($card['par']['holes'] as $i => $par) {
            $holes[] = ['par' => $par, 'strokeIndex' => $card['strokeIndex'][$i] ?? null, 'distance' => $card['distance']['holes'][$i] ?? null];
        }

        $portLines = [];
        foreach ($lines as $line) {
            if (! is_array($line) || ! is_array($line['scores'] ?? null)) {
                return null;
            }

            $playerId = $line['playerId'] ?? null;
            $portLines[] = [
                'playerId' => is_int($playerId) ? $ids->find('players', $playerId) : null,
                'strokes' => $line['scores']['holes'] ?? null,
                ...(isset($line['name']) ? ['readName' => $line['name']] : []),
                ...(($line['readTotals'] ?? false) === true ? ['totals' => self::totals($line['scores'])] : []),
            ];
        }

        $roundCard = ImportCorrection::fromArray([
            'version' => $next['version'] ?? null,
            'card' => [
                'courseName' => $card['courseName'] ?? null,
                'tees' => $card['tees'] ?? null,
                'playedOn' => $card['playedOn'] ?? null,
                'holes' => $holes,
                'lines' => $portLines,
                ...(($next['readCardTotals'] ?? false) === true ? ['parTotals' => self::totals($card['par'])] : []),
            ],
        ]);

        return $roundCard === null ? null : ImportCorrection::toArray($roundCard);
    }

    /**
     * @param  array<mixed>  $row
     * @return array{out: mixed, in: mixed, total: mixed}
     */
    private static function totals(array $row): array
    {
        return ['out' => $row['out'] ?? null, 'in' => $row['in'] ?? null, 'total' => $row['total'] ?? null];
    }
}
