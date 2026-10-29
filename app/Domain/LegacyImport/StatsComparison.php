<?php

namespace App\Domain\LegacyImport;

use App\Domain\LegacyImport\Exceptions\LegacyImportFailed;
use Illuminate\Support\Facades\DB;

/**
 * Compares the player_round_stats recomputed by the port with those of Next, row by row (design
 * D11): any difference, or a row on one side only, fails the import, every difference listed.
 * The proof that the base gives the same indexes as Next.
 */
final class StatsComparison
{
    /** The columns compared, as both bases name them. */
    private const array COLUMNS = ['gross', 'adjusted', 'differential_t', 'index_before_t', 'index_after_t', 'counts_in_current'];

    /**
     * @return int the rows compared, all identical
     *
     * @throws LegacyImportFailed
     */
    public static function compare(LegacyDatabase $legacy, IdMap $ids): int
    {
        $recomputed = [];
        foreach (DB::table('player_round_stats')->get() as $row) {
            $recomputed[self::key($row->round_id, $row->player_id)] = self::values((array) $row, 'recalculé');
        }

        $differences = [];
        $compared = 0;
        $sqliteRows = $legacy->connection()->table('player_round_stats')->orderBy('round_id')->orderBy('player_id')->get();
        foreach ($sqliteRows as $row) {
            $stats = new LegacyRow('player_round_stats', (array) $row);
            $reference = "player_round_stats, round {$stats->int('round_id')}, joueur {$stats->int('player_id')}";
            $roundId = $ids->uuid('rounds', $stats->int('round_id'), $reference);
            $playerId = $ids->uuid('players', $stats->int('player_id'), $reference);
            $key = self::key($roundId, $playerId);
            $compared++;

            if (! array_key_exists($key, $recomputed)) {
                $differences[] = self::label($roundId, $playerId).' : absente du recalcul';

                continue;
            }

            $sqlite = self::values([
                ...array_combine(self::COLUMNS, array_map($stats->nullableInt(...), self::COLUMNS)),
                'counts_in_current' => $stats->int('counts_in_current') === 1,
            ], 'SQLite');
            foreach (self::COLUMNS as $column) {
                if ($sqlite[$column] !== $recomputed[$key][$column]) {
                    $differences[] = self::label($roundId, $playerId)." : {$column} SQLite ".self::show($sqlite[$column]).', recalculé '.self::show($recomputed[$key][$column]);
                }
            }
            unset($recomputed[$key]);
        }

        foreach (array_keys($recomputed) as $key) {
            [$roundId, $playerId] = explode('/', $key);
            $differences[] = self::label($roundId, $playerId).' : absente de SQLite';
        }

        if ($differences !== []) {
            throw new LegacyImportFailed('Statistiques différentes de la base SQLite :', [...$differences, 'Import annulé : rien n\'a été écrit.']);
        }

        return $compared;
    }

    private static function key(string $roundId, string $playerId): string
    {
        return "{$roundId}/{$playerId}";
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  string  $side  « SQLite » or « recalculé », for the message
     * @return array<string, int|bool|null>
     *
     * @throws LegacyImportFailed on a value of another type: a fault of the reading, never
     *                            compared as null
     */
    private static function values(array $row, string $side): array
    {
        $values = [];
        foreach (self::COLUMNS as $column) {
            $value = $row[$column] ?? null;
            if ($value !== null && ! is_int($value) && ! is_bool($value)) {
                throw new LegacyImportFailed("Valeur inattendue : player_round_stats.{$column} {$side}, de type ".get_debug_type($value));
            }
            $values[$column] = $value;
        }

        return $values;
    }

    /**
     * « BETHPAGE BLACK / Red, 2026-10-17, Manu »: for the console of the operator only.
     */
    private static function label(string $roundId, string $playerId): string
    {
        $round = DB::table('rounds')->where('id', $roundId)->first(['course_name', 'tees', 'played_on']);
        $player = DB::table('players')->where('id', $playerId)->value('name');

        return "{$round?->course_name} / {$round?->tees}, {$round?->played_on}, {$player}";
    }

    private static function show(int|bool|null $value): string
    {
        return match (true) {
            $value === null => 'aucun',
            is_bool($value) => $value ? 'oui' : 'non',
            default => (string) $value,
        };
    }
}
