<?php

namespace App\Domain\Handicaps;

/**
 * A player round picked for a virtual match (spec virtual-match, « Choix gardé dans
 * l'adresse »): the line of a player on a round, read from the address as roundUuid.playerUuid.
 */
final readonly class Pick
{
    public function __construct(
        public string $roundId,
        public string $playerId,
    ) {}
}
