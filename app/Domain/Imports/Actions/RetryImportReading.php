<?php

namespace App\Domain\Imports\Actions;

use App\Domain\AiUsage\AiBudget;
use App\Domain\Imports\Exceptions\AiBudgetReached;
use App\Domain\Imports\Exceptions\ImportAlreadySaved;
use App\Domain\Imports\Exceptions\ImportBeingRead;
use App\Domain\Imports\Exceptions\ImportNotFailed;
use App\Domain\Imports\ImportStatus;
use App\Jobs\ReadScorecardImport;
use App\Models\Import;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Queues the reading of a failed import again, with the same photo (design D6): the failure,
 * the draft and the saved correction are cleared, and the job reads it as a first reading. The
 * budget is checked at the request, and again by the job before its call.
 */
final class RetryImportReading
{
    public function __construct(private readonly AiBudget $budget) {}

    /**
     * @throws ImportAlreadySaved when the import has a round
     * @throws ImportBeingRead when the import is queued or being read
     * @throws ImportNotFailed when the import is ready to check
     * @throws AiBudgetReached when the ceiling of the month leaves no room for a call
     */
    public function handle(Import $import, CarbonImmutable $now): void
    {
        DB::transaction(function () use ($import, $now): void {
            $locked = Import::query()->lockForUpdate()->findOrFail($import->id);

            match (true) {
                $locked->round_id !== null => throw new ImportAlreadySaved,
                in_array($locked->status, [ImportStatus::Queued, ImportStatus::Reading], true) => throw new ImportBeingRead,
                $locked->status === ImportStatus::Ready => throw new ImportNotFailed,
                ! $this->budget->canSpend($now) => throw new AiBudgetReached,
                default => null,
            };

            $locked->update(['status' => ImportStatus::Queued, 'failure' => null, 'draft' => null, 'correction' => null]);

            ReadScorecardImport::dispatch($locked->id)->afterCommit();
        });
    }
}
