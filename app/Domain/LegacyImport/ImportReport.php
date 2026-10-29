<?php

namespace App\Domain\LegacyImport;

/**
 * What golf:import-sqlite prints at the end of an import or of a dry run (design D13).
 */
final class ImportReport
{
    /** Photos copied, or only read in a dry run (design D9). */
    public int $photos = 0;

    /** Files of the --uploads folder that no row references. */
    public int $unreferencedFiles = 0;

    /** Distances of hole out of 1 to 999 imported empty (design D8). */
    public int $distancesCleared = 0;

    /** Corrections of Next not kept (design D10). */
    public int $correctionsDropped = 0;

    public function __construct(public readonly bool $dryRun = false) {}

    /**
     * @return list<string>
     */
    public function lines(): array
    {
        return [
            ($this->dryRun ? 'Photos lues : ' : 'Photos copiées : ').$this->photos,
            "Fichiers non référencés ignorés : {$this->unreferencedFiles}",
            "Distances hors de 1 à 999 vidées : {$this->distancesCleared}",
            "Corrections abandonnées : {$this->correctionsDropped}",
            $this->dryRun ? 'Essai à blanc : import annulé, rien n\'a été écrit.' : 'Import terminé.',
        ];
    }
}
