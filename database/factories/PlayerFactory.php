<?php

namespace Database\Factories;

use App\Domain\Players\PlayerName;
use App\Models\Player;
use App\Models\PlayerAlias;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Player>
 */
class PlayerFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->firstName(),
        ];
    }

    /**
     * A player carrying its name upper case as alias, as INITIAL_PLAYERS creates them.
     */
    public function withAlias(): static
    {
        return $this->afterCreating(function (Player $player): void {
            PlayerAlias::factory()->for($player)->create(['alias' => PlayerName::alias($player->name)]);
        });
    }
}
