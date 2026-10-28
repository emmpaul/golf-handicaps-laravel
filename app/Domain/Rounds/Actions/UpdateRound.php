<?php

namespace App\Domain\Rounds\Actions;

use App\Domain\Handicaps\Actions\RecomputePlayerStats;
use App\Domain\Rounds\Exceptions\IncoherentRoundCard;
use App\Domain\Rounds\RoundCard;
use App\Domain\Rounds\RoundCardChecks;
use App\Domain\Rounds\RoundCardWriter;
use App\Models\Round;
use App\Models\RoundHole;
use App\Models\RoundPlayer;
use Illuminate\Support\Facades\DB;

/**
 * Corrects a saved round with the same checks as a new one: its holes, lines and scores are
 * replaced; its photo stays. The handicap history of its former and new players is recomputed.
 */
final class UpdateRound
{
    public function __construct(
        private readonly RoundCardWriter $writer,
        private readonly RecomputePlayerStats $recomputePlayerStats,
    ) {}

    /**
     * @throws IncoherentRoundCard before any write
     */
    public function handle(Round $round, RoundCard $card): Round
    {
        return DB::transaction(function () use ($round, $card): Round {
            RoundCardChecks::ensureCoherent($card);

            // A player taken off the round is recomputed too (« Joueur remplacé »).
            $formerPlayerIds = RoundPlayer::query()->where('round_id', $round->id)->pluck('player_id')->all();

            // The scores and the history rows follow their lines (composite foreign keys in cascade).
            RoundPlayer::query()->where('round_id', $round->id)->delete();
            RoundHole::query()->where('round_id', $round->id)->delete();
            $this->writer->write($round, $card);
            $this->recomputePlayerStats->handle([...$formerPlayerIds, ...$card->playerIds()]);

            return $round;
        });
    }
}
