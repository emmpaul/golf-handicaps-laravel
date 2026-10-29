<?php

namespace App\Domain\LegacyImport;

/**
 * What golf:import-sqlite prints at the end of an import (design D13).
 */
final class ImportReport
{
    /** Distances of hole out of 1 to 999 imported empty (design D8). */
    public int $distancesCleared = 0;

    /**
     * @return list<string>
     */
    public function lines(): array
    {
        return [
            "Distances hors de 1 à 999 vidées : {$this->distancesCleared}",
            'Import terminé.',
        ];
    }
}
