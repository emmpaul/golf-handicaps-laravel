<?php

namespace App\Domain\Rounds;

/**
 * The shape of a round card as the round editor sends it (design D3 of players-and-rounds): any
 * cell may be empty, the coherence checks of RoundCardChecks run afterwards. The same rules
 * validate a card sent to be saved and the correction of an import, at writing and at reading
 * (design D7).
 */
final class RoundCardShape
{
    /**
     * The validation rules of a card under $prefix (`card.` for a correction).
     *
     * @param  bool  $withReading  also the totals and names read on a photo
     * @param  bool  $knownPlayers  each player must exist; a saved correction may name a player
     *                              deleted since, that its opening drops
     * @return array<string, list<string>>
     */
    public static function rules(string $prefix = '', bool $withReading = false, bool $knownPlayers = true): array
    {
        $rules = [
            'courseName' => ['nullable', 'string', 'max:100'],
            'tees' => ['nullable', 'string', 'max:100'],
            'playedOn' => ['nullable', 'date_format:Y-m-d'],
            'holes' => ['required', 'list', 'size:18'],
            'holes.*' => ['array'],
            'holes.*.par' => ['nullable', 'integer'],
            'holes.*.strokeIndex' => ['nullable', 'integer'],
            'holes.*.distance' => ['nullable', 'integer'],
            'lines' => ['required', 'list', 'min:1', 'max:'.RoundCardChecks::MAX_PLAYERS],
            'lines.*' => ['array'],
            'lines.*.playerId' => ['nullable', 'uuid', ...($knownPlayers ? ['exists:players,id'] : [])],
            'lines.*.strokes' => ['required', 'list', 'size:18'],
            'lines.*.strokes.*' => ['nullable', 'integer'],
        ];

        if ($withReading) {
            $rules = [
                ...$rules,
                ...self::totalsRules('parTotals'),
                'lines.*.readName' => ['nullable', 'string', 'max:100'],
                ...self::totalsRules('lines.*.totals'),
            ];
        }

        $prefixed = [];
        foreach ($rules as $field => $fieldRules) {
            $prefixed[$prefix.$field] = $fieldRules;
        }

        return $prefixed;
    }

    /**
     * The card of validated data, its numbers as integers whether they came as JSON or as form
     * fields; the totals and names read only when present.
     *
     * @param  array<array-key, mixed>  $card  validated by rules()
     */
    public static function card(array $card): RoundCard
    {
        /** @var array{courseName?: string|null, tees?: string|null, playedOn?: string|null, holes: list<array<string, mixed>>, lines: list<array<string, mixed>>, parTotals?: array<string, mixed>|null} $card */
        return new RoundCard(
            courseName: $card['courseName'] ?? null,
            tees: $card['tees'] ?? null,
            playedOn: $card['playedOn'] ?? null,
            holes: array_map(fn (array $hole): array => [
                'par' => self::int($hole['par'] ?? null),
                'strokeIndex' => self::int($hole['strokeIndex'] ?? null),
                'distance' => self::int($hole['distance'] ?? null),
            ], $card['holes']),
            lines: array_map(fn (array $line): array => [
                'playerId' => isset($line['playerId']) ? (string) $line['playerId'] : null,
                'strokes' => array_map(self::int(...), array_values((array) $line['strokes'])),
                ...(isset($line['readName']) ? ['readName' => (string) $line['readName']] : []),
                ...(isset($line['totals']) ? ['totals' => self::totals((array) $line['totals'])] : []),
            ], $card['lines']),
            parTotals: isset($card['parTotals']) ? self::totals($card['parTotals']) : null,
        );
    }

    /**
     * @return array<string, list<string>>
     */
    private static function totalsRules(string $field): array
    {
        return [
            $field => ['nullable', 'array:out,in,total'],
            "{$field}.out" => ['nullable', 'integer'],
            "{$field}.in" => ['nullable', 'integer'],
            "{$field}.total" => ['nullable', 'integer'],
        ];
    }

    /**
     * @param  array<array-key, mixed>  $totals
     * @return array{out: int|null, in: int|null, total: int|null}
     */
    private static function totals(array $totals): array
    {
        return [
            'out' => self::int($totals['out'] ?? null),
            'in' => self::int($totals['in'] ?? null),
            'total' => self::int($totals['total'] ?? null),
        ];
    }

    private static function int(mixed $value): ?int
    {
        return $value === null ? null : (int) $value;
    }
}
