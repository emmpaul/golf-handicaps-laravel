<?php

namespace App\Domain\LegacyImport;

use App\Domain\LegacyImport\Exceptions\LegacyImportFailed;

/**
 * The schema of the Next base at the level of the Drizzle migration 0004_course_ratings (design
 * D4), read in drizzle/0000 to 0004: each table the import reads, with each of its columns. A
 * column more is tolerated; the tables the import ignores (jobs, internal tables) are not
 * checked.
 */
final class LegacySchema
{
    public const array TABLES = [
        'players' => ['id', 'name', 'created_at'],
        'player_aliases' => ['id', 'player_id', 'alias'],
        'rounds' => ['id', 'course_name', 'tees', 'played_on', 'created_at', 'photo_path'],
        'round_holes' => ['round_id', 'hole', 'par', 'stroke_index', 'distance'],
        'round_players' => ['round_id', 'player_id', 'position'],
        'scores' => ['round_id', 'player_id', 'hole', 'strokes'],
        'player_round_stats' => ['round_id', 'player_id', 'gross', 'adjusted', 'differential_t', 'index_before_t', 'index_after_t', 'counts_in_current'],
        'imports' => ['id', 'status', 'photo_path', 'corners', 'draft', 'round_id', 'created_at', 'ai_cells', 'ai_note', 'correction', 'corners_source'],
        'ai_usage' => ['id', 'created_at', 'month', 'model', 'input_tokens', 'output_tokens', 'cost_micro_usd'],
        'course_ratings' => ['id', 'course_name', 'tees', 'rating_t', 'slope', 'source', 'status', 'sgt_course_id', 'sgt_course_name', 'sgt_course_location', 'candidates', 'tees_read', 'proposed_tee', 'note', 'updated_at'],
    ];

    /**
     * @throws LegacyImportFailed naming every missing table and column
     */
    public static function check(LegacyDatabase $database): void
    {
        $tables = $database->tables();
        $faults = [];

        foreach (self::TABLES as $table => $columns) {
            if (! in_array($table, $tables, true)) {
                $faults[] = "Table manquante : {$table}";

                continue;
            }

            foreach (array_diff($columns, $database->columns($table)) as $column) {
                $faults[] = "Colonne manquante : {$table}.{$column}";
            }
        }

        if ($faults !== []) {
            throw new LegacyImportFailed('Base SQLite au mauvais niveau : migration Drizzle 0004 attendue.', $faults);
        }
    }
}
