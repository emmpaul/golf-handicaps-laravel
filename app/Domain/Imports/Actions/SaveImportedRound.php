<?php

namespace App\Domain\Imports\Actions;

use App\Domain\Players\Actions\AssignAlias;
use App\Domain\Players\PlayerNameMatcher;
use App\Domain\Rounds\Actions\CreateRound;
use App\Domain\Rounds\Exceptions\IncoherentRoundCard;
use App\Domain\Rounds\RoundCard;
use App\Models\Import;
use App\Models\Player;
use App\Models\Round;
use Illuminate\Support\Facades\DB;

/**
 * Saves the round of an import (design D5): the round keeps the photo of the import, the import
 * is linked to it, and each name read that did not already designate its player exactly
 * becomes an alias of that player. One transaction: a refused card learns nothing.
 */
final class SaveImportedRound
{
    public function __construct(
        private readonly CreateRound $createRound,
        private readonly AssignAlias $assignAlias,
    ) {}

    /**
     * @throws IncoherentRoundCard before any write
     */
    public function handle(Import $import, RoundCard $card): Round
    {
        return DB::transaction(function () use ($import, $card): Round {
            $locked = Import::query()->lockForUpdate()->findOrFail($import->id);

            $round = $this->createRound->handle($card);
            $round->update(['photo_path' => $locked->photo_path]);
            $locked->update(['round_id' => $round->id]);

            $this->learnAliases($card);

            return $round;
        });
    }

    /**
     * Compared with the players as they were before the save: a line read « GAB » and given to
     * Gab, who carries GAB, learns nothing.
     */
    private function learnAliases(RoundCard $card): void
    {
        $players = Player::query()->with('aliases')->get();

        $toLearn = [];
        foreach ($card->lines as $line) {
            $readName = $line['readName'] ?? null;
            if ($readName === null || trim($readName) === '' || $line['playerId'] === null) {
                continue;
            }

            $match = PlayerNameMatcher::match($readName, $players);
            if ($match?->kind !== 'exact' || $match->playerId !== $line['playerId']) {
                $toLearn[] = [$players->firstOrFail('id', $line['playerId']), $readName];
            }
        }

        foreach ($toLearn as [$player, $readName]) {
            $this->assignAlias->handle($player, $readName);
        }
    }
}
