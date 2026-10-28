<?php

namespace Database\Factories;

use App\Models\Player;
use App\Models\PlayerAlias;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlayerAlias>
 */
class PlayerAliasFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'player_id' => Player::factory(),
            'alias' => mb_strtoupper(fake()->unique()->userName()),
        ];
    }
}
