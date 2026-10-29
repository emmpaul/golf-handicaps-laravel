<?php

namespace App\Domain\Imports;

/**
 * Where the reading of an import stands: queued, then reading, then ready to check or failed.
 */
enum ImportStatus: string
{
    case Queued = 'queued';
    case Reading = 'reading';
    case Ready = 'ready';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Queued => 'En attente',
            self::Reading => 'Lecture en cours',
            self::Ready => 'Prêt à vérifier',
            self::Failed => 'Échec',
        };
    }
}
