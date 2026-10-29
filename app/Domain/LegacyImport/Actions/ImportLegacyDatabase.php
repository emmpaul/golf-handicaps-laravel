<?php

namespace App\Domain\LegacyImport\Actions;

use App\Domain\Courses\CourseRatingStatus;
use App\Domain\Handicaps\Actions\RecomputePlayerStats;
use App\Domain\Imports\ImportStatus;
use App\Domain\Imports\ScorecardDraft;
use App\Domain\LegacyImport\Exceptions\DryRunCancelled;
use App\Domain\LegacyImport\Exceptions\LegacyImportFailed;
use App\Domain\LegacyImport\IdMap;
use App\Domain\LegacyImport\ImportReport;
use App\Domain\LegacyImport\LegacyDatabase;
use App\Domain\LegacyImport\LegacyImportCorrection;
use App\Domain\LegacyImport\LegacyPhotos;
use App\Domain\LegacyImport\LegacyRow;
use App\Domain\LegacyImport\LegacySchema;
use App\Domain\LegacyImport\StatsComparison;
use App\Domain\LegacyImport\TargetTables;
use App\Jobs\LookUpCourseRating;
use Carbon\CarbonImmutable;
use Closure;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

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

    private LegacyPhotos $photos;

    /**
     * The course_ratings still looked up in Next, requeued after the commit (design D12).
     *
     * @var list<string>
     */
    private array $pendingLookups = [];

    /** The instant of the import, one for the whole command. */
    private string $now;

    public function __construct(private readonly RecomputePlayerStats $recomputePlayerStats) {}

    /**
     * @param  array<string, int>|null  $confirmedCounts  the tables, with their counts, the operator
     *                                                    confirmed to empty (--force); null without
     *                                                    --force
     * @param  bool  $dryRun  everything is done, then cancelled: nothing written, nothing queued
     *
     * @throws LegacyImportFailed
     */
    public function handle(string $dbPath, ?string $uploadsDir, ?array $confirmedCounts, bool $dryRun): ImportReport
    {
        $this->legacy = LegacyDatabase::open($dbPath);
        LegacySchema::check($this->legacy);
        $nonEmpty = TargetTables::nonEmpty();
        if ($nonEmpty !== [] && $confirmedCounts === null) {
            throw new LegacyImportFailed('Tables non vides : '.TargetTables::describe($nonEmpty).'. Relancer avec --force pour les vider.');
        }
        // Only the rows the operator saw in the question are emptied (design D12).
        if ($confirmedCounts !== null && $nonEmpty !== $confirmedCounts) {
            throw new LegacyImportFailed('Tables modifiées depuis la confirmation : '.TargetTables::describe($nonEmpty).' au lieu de '.($confirmedCounts === [] ? 'tables vides' : TargetTables::describe($confirmedCounts)).'. Relancer l\'import.');
        }
        $this->photos = LegacyPhotos::check($this->legacy, $uploadsDir);

        $this->ids = new IdMap;
        $this->pendingLookups = [];
        $this->now = CarbonImmutable::now('UTC')->format('Y-m-d H:i:s');
        $this->report = new ImportReport($dryRun);
        $this->report->unreferencedFiles = $this->photos->unreferencedFiles();

        // The photos of the rows emptied by --force, deleted once the import is committed.
        $emptiedPhotos = [];

        try {
            DB::transaction(function () use ($nonEmpty, $dryRun, &$emptiedPhotos): void {
                if ($nonEmpty !== []) {
                    $emptiedPhotos = self::targetPhotos();
                    TargetTables::truncate();
                }

                $this->importPlayers();
                $this->importRounds();
                $this->importCourseRatings();
                $this->importImports();
                $this->importAiUsage();
                $this->report->photos = $this->photos->copy($dryRun);

                // player_round_stats is not copied: the history is replayed by the computation
                // of the port (design D7, D11).
                self::step('du recalcul des statistiques', fn () => $this->recomputePlayerStats->handle(DB::table('round_players')->distinct()->pluck('player_id')->all()));
                $this->report->statsCompared = StatsComparison::compare($this->legacy, $this->ids);
                $this->report->ratingLookups = count($this->pendingLookups);
                self::step('du comptage des lignes', $this->countRows(...));

                if ($dryRun) {
                    throw new DryRunCancelled;
                }
            });
        } catch (DryRunCancelled) {
            return $this->report;
        } catch (Throwable $exception) {
            $this->photos->undo();

            throw $exception;
        }

        $this->deleteEmptiedPhotos(array_values(array_diff($emptiedPhotos, $this->photos->destinations())));
        $this->requeueLookups();

        return $this->report;
    }

    /**
     * Deletes the photos of the rows emptied by --force, once the import is committed: a photo
     * that stays is told in the report, the import kept, nothing logged (design D3, D13).
     *
     * @param  list<string>  $paths
     */
    private function deleteEmptiedPhotos(array $paths): void
    {
        $disk = Storage::disk(LegacyPhotos::DISK);
        $kept = array_values(array_filter($paths, function (string $path) use ($disk): bool {
            try {
                return ! $disk->delete($path);
            } catch (Exception) {
                return true;
            }
        }));

        if ($kept !== []) {
            $this->report->afterCommitFailures[] = 'de l\'effacement de '.count($kept).(count($kept) > 1 ? ' photos' : ' photo').' des lignes vidées : '.implode(', ', $kept);
        }
    }

    /**
     * Queues again the lookups still pending in Next, once the import is committed. A lookup
     * that cannot be queued is told in the report: golf:queue-rating-lookups only queues the
     * pairs without a row, so the operator relaunches it from the courses page.
     */
    private function requeueLookups(): void
    {
        $failed = 0;
        foreach ($this->pendingLookups as $courseRatingId) {
            try {
                LookUpCourseRating::dispatch($courseRatingId);
            } catch (Exception) {
                $failed++;
            }
        }

        if ($failed > 0) {
            $this->report->afterCommitFailures[] = $failed > 1
                ? "de la mise en file de {$failed} recherches de rating : les relancer depuis la page Parcours (« Relancer la recherche »)."
                : 'de la mise en file de 1 recherche de rating : la relancer depuis la page Parcours (« Relancer la recherche »).';
        }
    }

    /**
     * The rows of each table on both sides, and the jobs of Next left out (design D13); read
     * inside the transaction, so that a dry run counts what it would have written.
     */
    private function countRows(): void
    {
        foreach (TargetTables::TABLES as $table) {
            $this->report->tables[$table] = [$this->legacy->connection()->table($table)->count(), DB::table($table)->count()];
        }
        $this->report->jobsIgnored = $this->legacy->connection()->table('jobs')->count();
    }

    /**
     * Runs a step of the transaction that is not the insertion of a row: a database error names
     * the step (« Échec du recalcul des statistiques : … »), without being reported to the log.
     *
     * @throws LegacyImportFailed
     */
    private static function step(string $name, Closure $step): void
    {
        try {
            $step();
        } catch (QueryException $exception) {
            throw new LegacyImportFailed("Échec {$name} : ".self::databaseMessage($exception));
        }
    }

    /**
     * The first line of the message of PostgreSQL, without its context.
     */
    private static function databaseMessage(QueryException $exception): string
    {
        $message = $exception->errorInfo[2] ?? $exception->getMessage();

        return trim(strtok((string) $message, "\n") ?: '');
    }

    /**
     * The photos of the rounds and imports of the target base.
     *
     * @return list<string>
     */
    private static function targetPhotos(): array
    {
        return array_values(array_unique(array_filter([
            ...DB::table('rounds')->pluck('photo_path')->all(),
            ...DB::table('imports')->pluck('photo_path')->all(),
        ], is_string(...))));
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
                'photo_path' => LegacyPhotos::destination($round->nullableString('photo_path')),
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
            $id = $this->ids->uuid('course_ratings', $rating->int('id'), "course_ratings, rating {$rating->int('id')}");
            $updatedAt = self::instant($rating->int('updated_at'));
            if ($rating->string('status') === CourseRatingStatus::Pending->value) {
                $this->pendingLookups[] = $id;
            }
            $this->insert('course_ratings', "rating {$rating->int('id')}", [
                'id' => $id,
                'course_name' => $rating->string('course_name'),
                'tees' => $rating->string('tees'),
                'rating_t' => $rating->nullableInt('rating_t'),
                'slope' => $rating->nullableInt('slope'),
                'source' => $rating->nullableString('source'),
                'status' => $rating->string('status'),
                'sgt_course_id' => $rating->nullableInt('sgt_course_id'),
                'sgt_course_name' => $rating->nullableString('sgt_course_name'),
                'sgt_course_location' => $rating->nullableString('sgt_course_location'),
                'candidates' => $rating->nullableJson('candidates'),
                'tees_read' => $rating->nullableJson('tees_read'),
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
            $id = $import->int('id');
            $roundId = $import->nullableInt('round_id');
            [$status, $failure] = self::statusOfImport($import);
            $createdAt = self::instant($import->int('created_at'));
            $this->insert('imports', "import {$id}", [
                'id' => $this->ids->uuid('imports', $id, "imports, import {$id}"),
                'status' => $status,
                'photo_path' => LegacyPhotos::destination($import->nullableString('photo_path')),
                'draft' => self::draft($import, $status),
                'correction' => $this->correction($import->nullableString('correction'), $roundId !== null),
                'failure' => $failure,
                'round_id' => $roundId === null ? null : $this->ids->uuid('rounds', $roundId, "imports, import {$id}, round {$roundId}"),
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
        }
    }

    /**
     * The status of an import and the reason of its failure (design D10): a reading cut by the
     * migration fails, a failure of Next keeps its note or the only failure Next had.
     *
     * @return array{string, string|null}
     */
    private static function statusOfImport(LegacyRow $import): array
    {
        $status = $import->string('status');

        return match ($status) {
            ImportStatus::Queued->value, ImportStatus::Reading->value => [ImportStatus::Failed->value, 'Import interrompu par la migration'],
            ImportStatus::Failed->value => [$status, $import->nullableString('ai_note') ?? 'Panneau introuvable sur la photo'],
            default => [$status, null],
        };
    }

    /**
     * The draft of an import as ScorecardDraft writes it; a ready import needs one.
     *
     * @throws LegacyImportFailed when the draft is out of shape
     */
    private static function draft(LegacyRow $import, string $status): ?string
    {
        $json = $import->nullableString('draft');
        if ($json === null && $status !== ImportStatus::Ready->value) {
            return null;
        }

        $decoded = $json === null ? null : json_decode($json, true);
        $draft = is_array($decoded) ? ScorecardDraft::fromArray($decoded) : null;
        if ($draft === null) {
            throw new LegacyImportFailed("Brouillon illisible : import {$import->int('id')}");
        }

        return (string) json_encode($draft->toArray());
    }

    /**
     * The correction of Next in the shape of the port; dropped and counted on an import saved
     * as a round, or when it cannot be read.
     */
    private function correction(?string $json, bool $saved): ?string
    {
        if ($json === null) {
            return null;
        }

        $decoded = $saved ? null : json_decode($json, true);
        $correction = is_array($decoded) ? LegacyImportCorrection::translate($decoded, $this->ids) : null;
        if ($correction === null) {
            $this->report->correctionsDropped++;

            return null;
        }

        return (string) json_encode($correction);
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
     * An instant of Next, in milliseconds, as a UTC timestamp to the second, milliseconds
     * truncated (design D6).
     */
    private static function instant(int $milliseconds): string
    {
        return CarbonImmutable::createFromTimestampMs($milliseconds, 'UTC')->format('Y-m-d H:i:s');
    }
}
