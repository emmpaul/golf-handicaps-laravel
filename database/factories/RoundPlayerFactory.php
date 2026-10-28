<?php

namespace Database\Factories;

use App\Models\Player;
use App\Models\Round;
use App\Models\RoundPlayer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RoundPlayer>
 */
class RoundPlayerFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'round_id' => Round::factory(),
            'player_id' => Player::factory(),
            'position' => 0,
        ];
    }
}
