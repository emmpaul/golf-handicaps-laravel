<?php

namespace App\Console\Commands;

use App\Domain\DomainException;
use App\Domain\Players\Actions\CreateInitialPlayers;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Run by the migrate service of the stack after the migrations: a failure keeps web and worker
 * from starting.
 */
#[Signature('golf:initial-players')]
#[Description('Create the players of INITIAL_PLAYERS on a base without any player')]
class CreateInitialPlayersCommand extends Command
{
    public function handle(CreateInitialPlayers $createInitialPlayers): int
    {
        try {
            $players = $createInitialPlayers->handle((string) config('golf.initial_players'));
        } catch (DomainException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info($players === []
            ? 'Aucun joueur créé.'
            : 'Joueurs créés : '.implode(', ', array_map(fn ($player): string => $player->name, $players)).'.');

        return self::SUCCESS;
    }
}
