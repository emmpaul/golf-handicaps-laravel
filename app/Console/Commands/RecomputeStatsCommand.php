<?php

namespace App\Console\Commands;

use App\Domain\Handicaps\Actions\RecomputePlayerStats;
use App\Models\RoundPlayer;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Run by the migrate service of the stack after the initial players, at every deployment: a
 * base whose rounds precede the history (staging, a restored dump) is computed without a manual
 * step. Idempotent: the same rounds and ratings give the same history.
 */
#[Signature('golf:recompute-stats')]
#[Description('Recompute the handicap history of every player who has a round')]
class RecomputeStatsCommand extends Command
{
    public function handle(RecomputePlayerStats $recomputePlayerStats): int
    {
        /** @var list<string> $playerIds */
        $playerIds = RoundPlayer::query()->distinct()->orderBy('player_id')->pluck('player_id')->all();

        DB::transaction(fn () => $recomputePlayerStats->handle($playerIds));

        $count = count($playerIds);
        $this->info($count.' '.($count > 1 ? 'joueurs recalculés' : 'joueur recalculé').'.');

        return self::SUCCESS;
    }
}
