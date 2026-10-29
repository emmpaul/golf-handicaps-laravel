<?php

namespace App\Domain\Imports\Actions;

use App\Domain\Imports\Exceptions\ImportAlreadySaved;
use App\Domain\Imports\Exceptions\ImportBeingRead;
use App\Domain\Imports\ImportCorrection;
use App\Domain\Imports\ImportStatus;
use App\Domain\Rounds\RoundCard;
use App\Models\Import;

/**
 * Keeps the correction of an import ready to check or failed, without round (design D6, D7).
 * One conditional UPDATE: a correction never lands after the round is saved. The draft read by
 * the AI is never touched.
 */
final class SaveImportCorrection
{
    /**
     * @throws ImportAlreadySaved when the import has a round
     * @throws ImportBeingRead when the import is queued or being read
     */
    public function handle(Import $import, RoundCard $card): void
    {
        $updated = Import::query()
            ->whereKey($import->id)
            ->whereNull('round_id')
            ->whereIn('status', [ImportStatus::Ready, ImportStatus::Failed])
            ->update([
                'correction' => json_encode(ImportCorrection::toArray($card), JSON_THROW_ON_ERROR),
                'updated_at' => now(),
            ]);

        if ($updated > 0) {
            return;
        }

        throw Import::query()->whereKey($import->id)->whereNotNull('round_id')->exists()
            ? new ImportAlreadySaved
            : new ImportBeingRead;
    }
}
