<?php

namespace App\Domain\Imports;

/**
 * The card as the AI read it, before the user checks it: the shape of src/scorecard/draft of the
 * Next application. Every cell is an integer, or null when it could not be read. toArray() is
 * what imports.draft keeps.
 *
 * @phpstan-type HoleRow array{holes: list<int|null>, out: int|null, in: int|null, total: int|null}
 * @phpstan-type PlayerRow array{name: string|null, scores: HoleRow}
 * @phpstan-type Draft array{courseName: string|null, tees: string|null, playedOn: string|null, par: HoleRow, strokeIndex: list<int|null>, distance: HoleRow, players: list<PlayerRow>}
 */
final readonly class ScorecardDraft
{
    public const int HOLES = 18;

    public const int MAX_PLAYERS = 4;

    /**
     * @param  string|null  $playedOn  `Y-m-d`
     * @param  HoleRow  $par
     * @param  list<int|null>  $strokeIndex
     * @param  HoleRow  $distance
     * @param  list<PlayerRow>  $players
     */
    private function __construct(
        public ?string $courseName,
        public ?string $tees,
        public ?string $playedOn,
        public array $par,
        public array $strokeIndex,
        public array $distance,
        public array $players,
    ) {}

    /**
     * The draft of a card, or null when the card is out of the expected shape: a row without
     * 18 holes, a cell or a total that is not an integer, more than 4 lines.
     *
     * @param  array<mixed>  $card
     */
    public static function fromArray(array $card): ?self
    {
        $courseName = self::text($card, 'courseName');
        $tees = self::text($card, 'tees');
        $playedOn = self::text($card, 'playedOn');
        $par = self::holeRow($card['par'] ?? null);
        $strokeIndex = self::cells($card['strokeIndex'] ?? null);
        $distance = self::holeRow($card['distance'] ?? null);
        $players = self::players($card['players'] ?? null);

        if ($courseName === false || $tees === false || $playedOn === false
            || $par === null || $strokeIndex === null || $distance === null || $players === null) {
            return null;
        }

        return new self($courseName, self::teesName($tees), $playedOn, $par, $strokeIndex, $distance, $players);
    }

    /**
     * @return Draft
     */
    public function toArray(): array
    {
        return [
            'courseName' => $this->courseName,
            'tees' => $this->tees,
            'playedOn' => $this->playedOn,
            'par' => $this->par,
            'strokeIndex' => $this->strokeIndex,
            'distance' => $this->distance,
            'players' => $this->players,
        ];
    }

    /**
     * The name of the tees without the distance the AI may read after it: GSPro prints
     * « Red 6241yd », the known courses are keyed by « Red ».
     */
    private static function teesName(?string $tees): ?string
    {
        return $tees === null ? null : (string) preg_replace('/\s*\d+\s*(yd|yds|m)\b\s*$/i', '', $tees);
    }

    /**
     * @param  array<mixed>  $card
     * @return string|false|null false when the key is missing or not a text
     */
    private static function text(array $card, string $key): string|false|null
    {
        if (! array_key_exists($key, $card)) {
            return false;
        }

        $value = $card[$key];

        return $value === null || is_string($value) ? $value : false;
    }

    /**
     * @return HoleRow|null
     */
    private static function holeRow(mixed $row): ?array
    {
        if (! is_array($row) || ! array_key_exists('out', $row) || ! array_key_exists('in', $row) || ! array_key_exists('total', $row)) {
            return null;
        }

        $holes = self::cells($row['holes'] ?? null);
        $totals = [$row['out'], $row['in'], $row['total']];

        if ($holes === null || ! self::allCells($totals)) {
            return null;
        }

        /** @var array{int|null, int|null, int|null} $totals */
        return ['holes' => $holes, 'out' => $totals[0], 'in' => $totals[1], 'total' => $totals[2]];
    }

    /**
     * @return list<int|null>|null
     */
    private static function cells(mixed $cells): ?array
    {
        if (! is_array($cells) || ! array_is_list($cells) || count($cells) !== self::HOLES || ! self::allCells($cells)) {
            return null;
        }

        /** @var list<int|null> $cells */
        return $cells;
    }

    /**
     * @param  array<mixed>  $values
     */
    private static function allCells(array $values): bool
    {
        foreach ($values as $value) {
            if ($value !== null && ! is_int($value)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<PlayerRow>|null
     */
    private static function players(mixed $players): ?array
    {
        if (! is_array($players) || ! array_is_list($players) || count($players) > self::MAX_PLAYERS) {
            return null;
        }

        $rows = [];
        foreach ($players as $player) {
            if (! is_array($player)) {
                return null;
            }

            $name = self::text($player, 'name');
            $scores = self::holeRow($player['scores'] ?? null);

            if ($name === false || $scores === null) {
                return null;
            }

            $rows[] = ['name' => $name, 'scores' => $scores];
        }

        return $rows;
    }
}
