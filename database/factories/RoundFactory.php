<?php

namespace Database\Factories;

use App\Models\Player;
use App\Models\Round;
use App\Models\RoundHole;
use App\Models\RoundPlayer;
use App\Models\Score;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Storage;

/**
 * @extends Factory<Round>
 */
class RoundFactory extends Factory
{
    /** Holes of the reference card IMG_3064 (BETHPAGE BLACK, tees Red, par 71). */
    public const array PARS = [4, 4, 3, 5, 4, 4, 5, 3, 4, 4, 4, 4, 5, 3, 4, 4, 3, 4];

    public const array STROKE_INDEXES = [8, 16, 18, 2, 4, 10, 6, 14, 12, 9, 11, 7, 3, 17, 1, 5, 13, 15];

    public const array DISTANCES = [420, 344, 128, 444, 398, 377, 480, 155, 392, 353, 405, 392, 470, 149, 401, 423, 173, 337];

    /** A 1×1 WebP image, enough for a photo served or deleted. */
    private const string PHOTO = 'UklGRhoAAABXRUJQVlA4TA0AAAAvAAAAEAcQERGIiP4HAA==';

    /**
     * A round without holes nor lines: withPlayers() gives it a coherent card.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'course_name' => mb_strtoupper(fake()->city()).' GOLF CLUB',
            'tees' => fake()->randomElement(['Red', 'White', 'Blue']),
            'played_on' => fake()->dateTimeBetween('-1 year')->format('Y-m-d'),
            'photo_path' => null,
        ];
    }

    /**
     * A coherent card: the 18 holes of the reference card, one line per player in this order,
     * and 18 scores from 1 to 15 per player.
     */
    public function withPlayers(Player ...$players): static
    {
        return $this->afterCreating(function (Round $round) use ($players): void {
            foreach (self::PARS as $i => $par) {
                RoundHole::factory()->for($round)->create([
                    'hole' => $i + 1,
                    'par' => $par,
                    'stroke_index' => self::STROKE_INDEXES[$i],
                    'distance' => self::DISTANCES[$i],
                ]);
            }

            foreach (array_values($players) as $position => $player) {
                RoundPlayer::factory()->for($round)->for($player)->create(['position' => $position]);

                foreach (self::PARS as $i => $par) {
                    Score::factory()->for($round)->for($player)->create([
                        'hole' => $i + 1,
                        'strokes' => $par + fake()->randomElement([-1, 0, 0, 1, 1, 2]),
                    ]);
                }
            }
        });
    }

    /**
     * A round with its photo on the photos disk, as the demonstration base has.
     */
    public function withPhoto(): static
    {
        return $this->state(fn (): array => ['photo_path' => 'rounds/'.fake()->uuid().'.webp'])
            ->afterCreating(function (Round $round): void {
                Storage::disk('photos')->put((string) $round->photo_path, (string) base64_decode(self::PHOTO, true));
            });
    }
}
