<?php

namespace App\Domain\LegacyImport;

/**
 * What golf:import-sqlite prints at the end of an import or of a dry run (design D13).
 */
final class ImportReport
{
    /**
     * Rows of each table: read in SQLite, written in PostgreSQL (recomputed for
     * player_round_stats), in the order of TargetTables::TABLES.
     *
     * @var array<string, array{int, int}>
     */
    public array $tables = [];

    /** Rows of the job queue of Next, never imported. */
    public int $jobsIgnored = 0;

    /** Photos copied, or only read in a dry run (design D9). */
    public int $photos = 0;

    /** Files of the --uploads folder that no row references. */
    public int $unreferencedFiles = 0;

    /** Rows of player_round_stats recomputed and compared, all identical (design D11). */
    public int $statsCompared = 0;

    /** Distances of hole out of 1 to 999 imported empty (design D8). */
    public int $distancesCleared = 0;

    /** Corrections of Next not kept (design D10). */
    public int $correctionsDropped = 0;

    /** Rows of course_ratings still looked up, requeued after the commit (design D12). */
    public int $ratingLookups = 0;

    /**
     * What failed after the commit, the import itself kept (design D3, step 4): « échec de … ».
     *
     * @var list<string>
     */
    public array $afterCommitFailures = [];

    public function __construct(public readonly bool $dryRun = false) {}

    /**
     * The table of the report: one row per table, its SQLite count and its PostgreSQL count.
     *
     * @return list<array{string, int, int}>
     */
    public function tableRows(): array
    {
        return array_map(
            fn (string $table, array $counts): array => [$table, ...$counts],
            array_keys($this->tables),
            array_values($this->tables),
        );
    }

    /**
     * The lines printed under the table.
     *
     * @return list<string>
     */
    public function lines(): array
    {
        return [
            ($this->dryRun ? 'Photos lues : ' : 'Photos copiées : ').$this->photos,
            "Fichiers non référencés ignorés : {$this->unreferencedFiles}",
            "Statistiques identiques : {$this->statsCompared} / {$this->statsCompared}",
            "Distances hors de 1 à 999 vidées : {$this->distancesCleared}",
            "Corrections abandonnées : {$this->correctionsDropped}",
            ($this->dryRun ? 'Recherches de rating à remettre en file : ' : 'Recherches de rating remises en file : ').$this->ratingLookups,
            "Jobs Next ignorés : {$this->jobsIgnored}",
            ...$this->ending(),
        ];
    }

    /**
     * @return list<string>
     */
    private function ending(): array
    {
        if ($this->dryRun) {
            return ['Essai à blanc : import annulé, rien n\'a été écrit.'];
        }

        if ($this->afterCommitFailures === []) {
            return ['Import terminé.'];
        }

        return array_map(fn (string $failure): string => "Import terminé ; échec {$failure}", $this->afterCommitFailures);
    }
}
