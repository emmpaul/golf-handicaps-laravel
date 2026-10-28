<?php

namespace Database\Factories;

use App\Models\RoundPlayer;
use App\Models\Score;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Score>
 */
class ScoreFactory extends Factory
{
    /**
     * Without a round and a player, the score gets a line of its own: the composite foreign
     * key wants the player on a line of the round.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $line = null;
        $lineOf = function () use (&$line): RoundPlayer {
            return $line ??= RoundPlayer::factory()->create();
        };

        return [
            'round_id' => fn (): string => $lineOf()->round_id,
            'player_id' => fn (): string => $lineOf()->player_id,
            'hole' => 1,
            'strokes' => fake()->numberBetween(3, 7),
        ];
    }
}
