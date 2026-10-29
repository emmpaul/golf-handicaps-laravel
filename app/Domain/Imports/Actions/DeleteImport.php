<?php

namespace App\Domain\Imports\Actions;

use App\Domain\Imports\Exceptions\ImportAlreadySaved;
use App\Domain\Imports\Exceptions\ImportBeingRead;
use App\Domain\Imports\ImportStatus;
use App\Models\Import;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Deletes an import queued, ready or failed, without round (spec scorecard-import,
 * « Suppression d'un import »). A queued import deleted is never read: the job finds nothing.
 * Its photo is erased once the deletion is committed.
 */
final class DeleteImport
{
    /**
     * @throws ImportAlreadySaved when the import has a round
     * @throws ImportBeingRead when the import is being read
     */
    public function handle(Import $import): void
    {
        DB::transaction(function () use ($import): void {
            $locked = Import::query()->lockForUpdate()->find($import->id);
            if ($locked === null) {
                return;
            }

            if ($locked->round_id !== null) {
                throw new ImportAlreadySaved;
            }

            if ($locked->status === ImportStatus::Reading) {
                throw new ImportBeingRead;
            }

            $locked->delete();

            $photo = $locked->photo_path;
            DB::afterCommit(fn () => Storage::disk('photos')->delete($photo));
        });
    }
}
