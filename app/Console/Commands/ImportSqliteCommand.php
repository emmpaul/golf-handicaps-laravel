<?php

namespace App\Console\Commands;

use App\Domain\LegacyImport\Actions\ImportLegacyDatabase;
use App\Domain\LegacyImport\Exceptions\LegacyImportFailed;
use App\Domain\LegacyImport\TargetTables;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Symfony\Component\Console\Helper\Table;

/**
 * Run once by the operator at the cutover, and before on a local copy and on the staging: the
 * import of the SQLite base of the Next application (spec legacy-import). No route runs it.
 */
#[Signature('golf:import-sqlite {db : Chemin de la base SQLite de Next} {--uploads= : Dossier des photos de Next} {--force : Vider les tables métier après confirmation} {--dry-run : Tout faire puis tout annuler}')]
#[Description('Import once the SQLite base of the Next application')]
class ImportSqliteCommand extends Command
{
    public function handle(ImportLegacyDatabase $importLegacyDatabase): int
    {
        $uploads = $this->option('uploads');
        $force = (bool) $this->option('force');

        // Without a terminal (--no-interaction), the answer is the default: no.
        $nonEmpty = TargetTables::nonEmpty();
        if ($force && $nonEmpty !== [] && ! $this->confirm('Vider '.TargetTables::describe($nonEmpty).' avant l\'import ?', false)) {
            $this->error('Import abandonné.');

            return self::FAILURE;
        }

        try {
            $report = $importLegacyDatabase->handle(
                (string) $this->argument('db'),
                is_string($uploads) ? $uploads : null,
                $force,
                (bool) $this->option('dry-run'),
            );
        } catch (LegacyImportFailed $failure) {
            $this->error($failure->getMessage());
            foreach ($failure->details as $detail) {
                $this->line($detail);
            }

            return self::FAILURE;
        }

        // The counts aligned on the right, in the compact style of the table.
        $count = (clone Table::getStyleDefinition('compact'))->setPadType(STR_PAD_LEFT);
        $this->table(['Table', 'SQLite', 'PostgreSQL'], $report->tableRows(), 'compact', [1 => $count, 2 => $count]);
        foreach ($report->lines() as $line) {
            $this->line($line);
        }

        return self::SUCCESS;
    }
}
