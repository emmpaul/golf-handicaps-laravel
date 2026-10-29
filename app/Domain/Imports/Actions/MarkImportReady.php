<?php

namespace App\Domain\Imports\Actions;

use App\Domain\Imports\ImportStatus;
use App\Domain\Imports\ScorecardDraft;
use App\Models\Import;

/**
 * Keeps the draft read by the AI on an import still being read: it is ready to check.
 */
final class MarkImportReady
{
    public function handle(string $importId, ScorecardDraft $draft): void
    {
        Import::query()
            ->whereKey($importId)
            ->where('status', ImportStatus::Reading)
            ->update([
                'status' => ImportStatus::Ready,
                'draft' => json_encode($draft->toArray(), JSON_THROW_ON_ERROR),
                'failure' => null,
                'updated_at' => now(),
            ]);
    }
}
