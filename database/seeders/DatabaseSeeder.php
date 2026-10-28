<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class DatabaseSeeder extends DemoSeeder
{
    use WithoutModelEvents;

    /**
     * The minimal development base of make fresh: the players, no round.
     */
    public function run(): void
    {
        $this->call(DevelopmentPlayersSeeder::class);
    }
}
