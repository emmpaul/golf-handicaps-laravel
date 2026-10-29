<?php

namespace Database\Seeders;

use App\Models\Player;
use App\Models\Round;
use RuntimeException;

/**
 * The demonstration base of make demo: the development players, the reference round IMG_3064
 * with its photo, the generated rounds, the rating of BETHPAGE BLACK / Red and three imports.
 * Written on an empty base only, so that it never mixes with rounds typed by hand.
 */
class DemoDatabaseSeeder extends DemoSeeder
{
    public function run(): void
    {
        if (Player::query()->exists() || Round::query()->exists()) {
            throw new RuntimeException('Le jeu de démonstration ne s’écrit que sur une base sans joueur ni round : lancer make demo.');
        }

        $this->call([
            DevelopmentPlayersSeeder::class,
            DemoRoundsSeeder::class,
            DemoCourseRatingsSeeder::class,
            DemoImportsSeeder::class,
        ]);
    }
}
