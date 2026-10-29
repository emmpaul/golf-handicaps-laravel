<?php

namespace App\Domain\LegacyImport;

use Illuminate\Support\Facades\DB;

/**
 * The tables golf:import-sqlite fills: empty before an import, or emptied with --force (design
 * D12). The tables of the framework (jobs, sessions, cache, migrations) do not count.
 */
final class TargetTables
{
    public const array TABLES = [
        'players', 'player_aliases', 'rounds', 'round_holes', 'round_players', 'scores',
        'player_round_stats', 'course_ratings', 'imports', 'ai_usage',
    ];

    /**
     * @return array<string, int> the tables that have a row, with their count, in the order of
     *                            TABLES
     */
    public static function nonEmpty(): array
    {
        $counts = [];
        foreach (self::TABLES as $table) {
            $count = DB::table($table)->count();
            if ($count > 0) {
                $counts[$table] = $count;
            }
        }

        return $counts;
    }

    /**
     * « players (3), player_aliases (3) ».
     *
     * @param  array<string, int>  $counts
     */
    public static function describe(array $counts): string
    {
        return implode(', ', array_map(
            fn (string $table, int $count): string => "{$table} ({$count})",
            array_keys($counts),
            $counts,
        ));
    }

    /**
     * Empties every table, inside the transaction of the caller (TRUNCATE is transactional in
     * PostgreSQL).
     */
    public static function truncate(): void
    {
        DB::statement('TRUNCATE '.implode(', ', self::TABLES));
    }
}
