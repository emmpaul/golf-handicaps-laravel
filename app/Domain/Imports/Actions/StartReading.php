<?php

namespace App\Domain\Imports\Actions;

use App\Domain\Imports\ImportStatus;
use App\Models\Import;
use Illuminate\Support\Facades\DB;

/**
 * Marks an import as being read, under a lock (design D4). An import that no longer exists, or
 * that is neither queued nor reading (deleted, or already read by an earlier run of the job),
 * is left alone and never sent to the AI.
 */
final class StartReading
{
    /**
     * @return Import|null the import to read, or null when there is nothing to read
     */
    public function handle(string $importId): ?Import
    {
        return DB::transaction(function () use ($importId): ?Import {
            $import = Import::query()->lockForUpdate()->find($importId);

            if ($import === null || ! in_array($import->status, [ImportStatus::Queued, ImportStatus::Reading], true)) {
                return null;
            }

            $import->update(['status' => ImportStatus::Reading]);

            return $import;
        });
    }
}
