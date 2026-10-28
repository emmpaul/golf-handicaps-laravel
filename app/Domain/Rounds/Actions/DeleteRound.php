<?php

namespace App\Domain\Rounds\Actions;

use App\Domain\Handicaps\Actions\RecomputePlayerStats;
use App\Models\Round;
use App\Models\RoundPlayer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Deletes a round, its holes, lines and scores; its players stay and their handicap history is
 * recomputed. Its photo is erased once the deletion is committed: a rollback never loses the
 * photo of a round that still exists.
 */
final class DeleteRound
{
    public function __construct(private readonly RecomputePlayerStats $recomputePlayerStats) {}

    public function handle(Round $round): void
    {
        DB::transaction(function () use ($round): void {
            $playerIds = RoundPlayer::query()->where('round_id', $round->id)->pluck('player_id')->all();

            $round->delete();
            $this->recomputePlayerStats->handle($playerIds);

            if ($round->photo_path !== null) {
                $photo = $round->photo_path;
                DB::afterCommit(fn () => Storage::disk('photos')->delete($photo));
            }
        });
    }
}
