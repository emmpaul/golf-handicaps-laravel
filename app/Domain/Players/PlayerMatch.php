<?php

namespace App\Domain\Players;

/**
 * The player found for a name read on a card: `exact` when the name or an alias is the same,
 * `close` when it is pre-selected and the user may change it.
 */
final readonly class PlayerMatch
{
    /**
     * @param  'exact'|'close'  $kind
     */
    public function __construct(
        public string $playerId,
        public string $kind,
    ) {}
}
