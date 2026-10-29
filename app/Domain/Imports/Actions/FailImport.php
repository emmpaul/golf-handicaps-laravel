<?php

namespace App\Domain\Imports\Actions;

use App\Domain\Imports\ImportStatus;
use App\Models\Import;

/**
 * Ends the reading of an import still being read without draft, with the reason shown to the
 * person.
 */
final class FailImport
{
    public function handle(string $importId, string $failure): void
    {
        Import::query()
            ->whereKey($importId)
            ->where('status', ImportStatus::Reading)
            ->update(['status' => ImportStatus::Failed, 'failure' => $failure, 'updated_at' => now()]);
    }
}
