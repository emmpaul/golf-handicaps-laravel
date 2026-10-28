<?php

namespace Database\Seeders;

use App\Domain\Players\Actions\CreateInitialPlayers;

/**
 * The players of every development base, whatever INITIAL_PLAYERS says: Gab, Manu and Sean.
 */
class DevelopmentPlayersSeeder extends DemoSeeder
{
    public function run(CreateInitialPlayers $createInitialPlayers): void
    {
        $createInitialPlayers->handle('Manu,Gab,Sean');
    }
}
