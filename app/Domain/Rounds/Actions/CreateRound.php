<?php

namespace App\Domain\Rounds\Actions;

use App\Domain\Rounds\Exceptions\IncoherentRoundCard;
use App\Domain\Rounds\RoundCard;
use App\Domain\Rounds\RoundCardChecks;
use App\Domain\Rounds\RoundCardWriter;
use App\Models\Round;
use Illuminate\Support\Facades\DB;

/**
 * Saves a new round, without photo, after replaying the coherence checks.
 */
final class CreateRound
{
    public function __construct(private readonly RoundCardWriter $writer) {}

    /**
     * @throws IncoherentRoundCard before any write
     */
    public function handle(RoundCard $card): Round
    {
        return DB::transaction(function () use ($card): Round {
            RoundCardChecks::ensureCoherent($card);

            $round = new Round;
            $this->writer->write($round, $card);

            return $round;
        });
    }
}
