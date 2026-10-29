<?php

namespace App\Domain\LegacyImport\Actions;

use App\Domain\Handicaps\Actions\RecomputePlayerStats;
use App\Domain\LegacyImport\Exceptions\LegacyImportFailed;
use App\Domain\LegacyImport\IdMap;
use App\Domain\LegacyImport\ImportReport;
use App\Domain\LegacyImport\LegacyDatabase;
use App\Domain\LegacyImport\LegacyRow;
use App\Domain\LegacyImport\LegacySchema;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Imports once the SQLite base of the Next application (design D3): checks without any write,
 * then every table in one transaction, with new UUIDs given in the order of the SQLite ids and
 * the foreign keys remapped. The rows go in as Next validated them, without the Actions: the
 * constraints of PostgreSQL stay the guard, and any row they refuse cancels everything.
 */
final class ImportLegacyDatabase
{
    private LegacyDatabase $legacy;

    private IdMap $ids;

    private ImportReport $report;

    /** The instant of the import, one for the whole command. */
    private string $now;

    public function __construct(private readonly RecomputePlayerStats $recomputePlayerStats) {}

    /**
     * @throws LegacyImportFailed
     */
    public function handle(string $dbPath, ?string $uploadsDir, bool $force, bool $dryRun): ImportReport
    {
        $this->legacy = LegacyDatabase::open($dbPath);
        LegacySchema::check($this->legacy);

        $this->ids = new IdMap;
        $this->now = CarbonImmutable::now('UTC')->format('Y-m-d H:i:s');
        $this->report = new ImportReport;

        DB::transaction(function (): void {
            $this->importPlayers();
            $this->importRounds();
            $this->importCourseRatings();
            $this->importImports();
            $this->importAiUsage();

            // player_round_stats is not copied: the history is replayed by the computation of
            // the port (design D7, D11).
            $this->recomputePlayerStats->handle(DB::table('round_players')->distinct()->pluck('player_id')->all());
        });

        return $this->report;
    }

    private function importPlayers(): void
    {
        $players = $this->rowsWithIds('players');
        foreach ($players as $player) {
            $createdAt = self::instant($player->int('created_at'));
            $this->insert('players', "joueur {$player->int('id')}", [
                'id' => $this->ids->uuid('players', $player->int('id'), "players, joueur {$player->int('id')}"),
                'name' => $player->string('name'),
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
        }

        foreach ($this->rowsWithIds('player_aliases') as $alias) {
            $this->insert('player_aliases', "alias {$alias->int('id')}", [
                'id' => $this->ids->uuid('player_aliases', $alias->int('id'), "player_aliases, alias {$alias->int('id')}"),
                'player_id' => $this->ids->uuid('players', $alias->int('player_id'), "player_aliases, alias {$alias->int('id')}, joueur {$alias->int('player_id')}"),
                'alias' => $alias->string('alias'),
                'created_at' => $this->now,
                'updated_at' => $this->now,
            ]);
        }
    }

    private function importRounds(): void
    {
        foreach ($this->rowsWithIds('rounds') as $round) {
            $createdAt = self::instant($round->int('created_at'));
            $this->insert('rounds', "round {$round->int('id')}", [
                'id' => $this->ids->uuid('rounds', $round->int('id'), "rounds, round {$round->int('id')}"),
                'course_name' => $round->string('course_name'),
                'tees' => $round->string('tees'),
                'played_on' => $round->string('played_on'),
                'photo_path' => self::photoPath($round->nullableString('photo_path')),
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
        }

        foreach ($this->rows('round_holes', ['round_id', 'hole']) as $hole) {
            $key = "round {$hole->int('round_id')}, trou {$hole->int('hole')}";
            $this->insert('round_holes', $key, [
                'id' => (string) Str::uuid7(),
                'round_id' => $this->ids->uuid('rounds', $hole->int('round_id'), "round_holes, {$key}"),
                'hole' => $hole->int('hole'),
                'par' => $hole->int('par'),
                'stroke_index' => $hole->int('stroke_index'),
                'distance' => $this->distance($hole->nullableInt('distance')),
            ]);
        }

        foreach ($this->rows('round_players', ['round_id', 'position']) as $line) {
            $key = "round {$line->int('round_id')}, joueur {$line->int('player_id')}";
            $this->insert('round_players', $key, [
                'id' => (string) Str::uuid7(),
                'round_id' => $this->ids->uuid('rounds', $line->int('round_id'), "round_players, {$key}"),
                'player_id' => $this->ids->uuid('players', $line->int('player_id'), "round_players, {$key}"),
                'position' => $line->int('position'),
            ]);
        }

        foreach ($this->rows('scores', ['round_id', 'player_id', 'hole']) as $score) {
            $key = "round {$score->int('round_id')}, joueur {$score->int('player_id')}, trou {$score->int('hole')}";
            $this->insert('scores', $key, [
                'id' => (string) Str::uuid7(),
                'round_id' => $this->ids->uuid('rounds', $score->int('round_id'), "scores, round {$score->int('round_id')}"),
                'player_id' => $this->ids->uuid('players', $score->int('player_id'), "scores, round {$score->int('round_id')}, joueur {$score->int('player_id')}"),
                'hole' => $score->int('hole'),
                'strokes' => $score->int('strokes'),
            ]);
        }
    }

    private function importCourseRatings(): void
    {
        foreach ($this->rowsWithIds('course_ratings') as $rating) {
            $updatedAt = self::instant($rating->int('updated_at'));
            $this->insert('course_ratings', "rating {$rating->int('id')}", [
                'id' => $this->ids->uuid('course_ratings', $rating->int('id'), "course_ratings, rating {$rating->int('id')}"),
                'course_name' => $rating->string('course_name'),
                'tees' => $rating->string('tees'),
                'rating_t' => $rating->nullableInt('rating_t'),
                'slope' => $rating->nullableInt('slope'),
                'source' => $rating->nullableString('source'),
                'status' => $rating->string('status'),
                'sgt_course_id' => $rating->nullableInt('sgt_course_id'),
                'sgt_course_name' => $rating->nullableString('sgt_course_name'),
                'sgt_course_location' => $rating->nullableString('sgt_course_location'),
                'candidates' => $rating->nullableString('candidates'),
                'tees_read' => $rating->nullableString('tees_read'),
                'proposed_tee' => $rating->nullableString('proposed_tee'),
                'note' => $rating->nullableString('note'),
                'created_at' => $updatedAt,
                'updated_at' => $updatedAt,
            ]);
        }
    }

    private function importImports(): void
    {
        foreach ($this->rowsWithIds('imports') as $import) {
            $createdAt = self::instant($import->int('created_at'));
            $this->insert('imports', "import {$import->int('id')}", [
                'id' => $this->ids->uuid('imports', $import->int('id'), "imports, import {$import->int('id')}"),
                'status' => $import->string('status'),
                'photo_path' => self::photoPath($import->nullableString('photo_path')),
                'draft' => $import->nullableString('draft'),
                'round_id' => $import->nullableInt('round_id') === null
                    ? null
                    : $this->ids->uuid('rounds', $import->int('round_id'), "imports, import {$import->int('id')}, round {$import->int('round_id')}"),
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
        }
    }

    private function importAiUsage(): void
    {
        foreach ($this->rowsWithIds('ai_usage') as $usage) {
            $this->insert('ai_usage', "ligne {$usage->int('id')}", [
                'id' => $this->ids->uuid('ai_usage', $usage->int('id'), "ai_usage, ligne {$usage->int('id')}"),
                'month' => $usage->string('month'),
                'model' => $usage->string('model'),
                'input_tokens' => $usage->int('input_tokens'),
                'output_tokens' => $usage->int('output_tokens'),
                'cost_micro_usd' => $usage->int('cost_micro_usd'),
                'created_at' => self::instant($usage->int('created_at')),
            ]);
        }
    }

    /**
     * A distance of Next, null when out of 1 to 999 (design D8): it enters no computation, and
     * the CHECK of round_holes would refuse it.
     */
    private function distance(?int $distance): ?int
    {
        if ($distance === null) {
            return null;
        }

        if ($distance < 1 || $distance > 999) {
            $this->report->distancesCleared++;

            return null;
        }

        return $distance;
    }

    /**
     * The rows of a table with an id, in the order of the ids, each id given its UUID.
     *
     * @return list<LegacyRow>
     */
    private function rowsWithIds(string $table): array
    {
        $rows = $this->rows($table, ['id']);
        $this->ids->assign($table, array_map(fn (LegacyRow $row): int => $row->int('id'), $rows));

        return $rows;
    }

    /**
     * @param  list<string>  $orderBy  the primary key of the table
     * @return list<LegacyRow>
     */
    private function rows(string $table, array $orderBy): array
    {
        $query = $this->legacy->connection()->table($table);
        foreach ($orderBy as $column) {
            $query->orderBy($column);
        }

        return array_values($query->get()->map(fn (object $row): LegacyRow => new LegacyRow($table, (array) $row))->all());
    }

    /**
     * Inserts a row; a refusal of PostgreSQL names the table and the SQLite row.
     *
     * @param  array<string, mixed>  $row
     *
     * @throws LegacyImportFailed
     */
    private function insert(string $table, string $sqliteKey, array $row): void
    {
        try {
            DB::table($table)->insert($row);
        } catch (QueryException $exception) {
            throw new LegacyImportFailed("Ligne refusée : {$table}, {$sqliteKey} : ".($exception->errorInfo[2] ?? $exception->getMessage()));
        }
    }

    /**
     * The path on the photos disk of a photo of Next, uploads/<name> (design D9).
     */
    private static function photoPath(?string $path): ?string
    {
        return $path !== null ? 'imports/'.pathinfo($path, PATHINFO_FILENAME).'.webp' : null;
    }

    /**
     * An instant of Next, in milliseconds, as a UTC timestamp to the second, milliseconds
     * truncated (design D6).
     */
    private static function instant(int $milliseconds): string
    {
        return CarbonImmutable::createFromTimestampMs($milliseconds, 'UTC')->format('Y-m-d H:i:s');
    }
}
