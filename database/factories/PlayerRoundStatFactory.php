<?php

namespace Database\Factories;

use App\Models\Player;
use App\Models\PlayerRoundStat;
use App\Models\Round;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Fixed values, recomputed from nothing: for the tests that read the history.
 *
 * @extends Factory<PlayerRoundStat>
 */
class PlayerRoundStatFactory extends Factory
{
    /**
     * Without a round, the row gets a round of its own with the player on its card: the
     * composite foreign key wants the player on a line of the round.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'player_id' => Player::factory(),
            'round_id' => fn (array $attributes): string => Round::factory()
                ->withPlayers(Player::query()->whereKey($attributes['player_id'])->firstOrFail())
                ->create()->id,
            'gross' => 85,
            'adjusted' => 84,
            'differential_t' => 130,
            'index_before_t' => 150,
            'index_after_t' => 140,
            'counts_in_current' => false,
        ];
    }

    /**
     * The first round of the player: no index before it.
     */
    public function firstRound(): static
    {
        return $this->state(fn (): array => ['index_before_t' => null]);
    }

    /**
     * A differential retained in the current index.
     */
    public function counting(): static
    {
        return $this->state(fn (): array => ['counts_in_current' => true]);
    }
}
