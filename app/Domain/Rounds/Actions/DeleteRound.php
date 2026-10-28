<?php

namespace App\Domain\Rounds\Actions;

use App\Models\Round;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Deletes a round, its holes, lines and scores; its players stay. Its photo is erased once the
 * deletion is committed: a rollback never loses the photo of a round that still exists.
 */
final class DeleteRound
{
    public function handle(Round $round): void
    {
        DB::transaction(function () use ($round): void {
            $round->delete();

            if ($round->photo_path !== null) {
                $photo = $round->photo_path;
                DB::afterCommit(fn () => Storage::disk('photos')->delete($photo));
            }
        });
    }
}
