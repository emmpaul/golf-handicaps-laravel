<?php

namespace App\Domain\Rounds\Actions;

use App\Domain\Handicaps\Actions\RecomputePlayerStats;
use App\Domain\Rounds\Exceptions\IncoherentRoundCard;
use App\Domain\Rounds\RoundCard;
use App\Domain\Rounds\RoundCardChecks;
use App\Domain\Rounds\RoundCardWriter;
use App\Models\Round;
use Illuminate\Support\Facades\DB;

/**
 * Saves a new round, without photo, after replaying the coherence checks, then recomputes the
 * handicap history of its players.
 */
final class CreateRound
{
    public function __construct(
        private readonly RoundCardWriter $writer,
        private readonly RecomputePlayerStats $recomputePlayerStats,
    ) {}

    /**
     * @throws IncoherentRoundCard before any write
     */
    public function handle(RoundCard $card): Round
    {
        return DB::transaction(function () use ($card): Round {
            RoundCardChecks::ensureCoherent($card);

            $round = new Round;
            $this->writer->write($round, $card);
            $this->recomputePlayerStats->handle($card->playerIds());

            return $round;
        });
    }
}
