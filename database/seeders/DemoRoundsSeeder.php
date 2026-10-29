<?php

namespace Database\Seeders;

use App\Domain\Rounds\Actions\CreateRound;
use App\Domain\Rounds\RoundCard;
use App\Models\Player;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;

/**
 * The rounds of the demonstration base, written through CreateRound so that each one passes the
 * coherence checks: the reference round IMG_3064 with its photo, then 24 rounds generated on
 * three courses from 2026-04-01 to 2026-10-15. Faker is seeded: two runs write the same rounds.
 */
class DemoRoundsSeeder extends DemoSeeder
{
    private const int GENERATED_ROUNDS = 24;

    private const string FIRST_DAY = '2026-04-01';

    private const string LAST_DAY = '2026-10-15';

    /**
     * The cards of the generated courses, read on the simulator: par, stroke index and distance
     * of each hole, hole 1 first.
     *
     * @var list<array{courseName: string, tees: string, pars: list<int>, strokeIndexes: list<int>, distances: list<int>}>
     */
    private const array COURSES = [
        [
            'courseName' => 'BETHPAGE BLACK',
            'tees' => 'Red',
            'pars' => [4, 4, 3, 5, 4, 4, 5, 3, 4, 4, 4, 4, 5, 3, 4, 4, 3, 4],
            'strokeIndexes' => [8, 16, 18, 2, 4, 10, 6, 14, 12, 9, 11, 7, 3, 17, 1, 5, 13, 15],
            'distances' => [420, 344, 128, 444, 398, 377, 480, 155, 392, 353, 405, 392, 470, 149, 401, 423, 173, 337],
        ],
        [
            'courseName' => 'PAYNES VALLEY',
            'tees' => 'Blue',
            'pars' => [4, 3, 4, 5, 3, 4, 4, 5, 4, 3, 4, 4, 5, 4, 4, 3, 4, 5],
            'strokeIndexes' => [3, 7, 17, 11, 9, 13, 15, 5, 1, 16, 12, 18, 2, 10, 4, 14, 8, 6],
            'distances' => [394, 197, 279, 491, 142, 406, 316, 499, 406, 139, 374, 270, 493, 353, 378, 172, 424, 479],
        ],
        [
            'courseName' => 'CALA NOVA',
            'tees' => 'White',
            'pars' => [5, 4, 4, 4, 4, 3, 4, 3, 5, 5, 4, 4, 3, 5, 4, 4, 3, 4],
            'strokeIndexes' => [9, 5, 13, 11, 1, 7, 15, 17, 3, 6, 4, 18, 12, 2, 8, 10, 16, 14],
            'distances' => [495, 314, 318, 325, 393, 166, 337, 140, 455, 472, 377, 252, 154, 519, 282, 379, 145, 357],
        ],
    ];

    /**
     * The strokes over par each player may take on a hole, drawn evenly: Gab plays near par,
     * Sean a little above, Manu above both.
     *
     * @var array<string, list<int>>
     */
    private const array OVER_PAR = [
        'Gab' => [-1, 0, 0, 0, 1, 1],
        'Sean' => [0, 0, 1, 1, 1, 2, 2],
        'Manu' => [0, 1, 1, 1, 2, 2, 3],
    ];

    /**
     * The one hole above par + 4 of the demonstration, so that the page « Calcul de l'index » shows
     * a capped hole: Manu plays 8 on the par 3 of hole 2 in the generated round 19 (PAYNES VALLEY,
     * 2026-09-10). Written over the strokes drawn, after the draw, so that every other score stays
     * the same.
     */
    private const array CAPPED_HOLE = ['round' => 19, 'player' => 'Manu', 'hole' => 2, 'strokes' => 8];

    /** The reference card IMG_3064, as read on the photo. */
    private const array REFERENCE_LINES = [
        'Gab' => [5, 5, 3, 5, 4, 4, 4, 3, 5, 4, 4, 4, 4, 3, 4, 5, 3, 3],
        'Manu' => [7, 5, 3, 7, 6, 5, 5, 3, 5, 3, 6, 5, 7, 4, 5, 4, 3, 5],
    ];

    public function run(CreateRound $createRound): void
    {
        fake()->seed(2026);

        $photos = Storage::disk('photos');
        $photos->deleteDirectory('rounds');

        /** @var array<string, string> $playerIds */
        $playerIds = Player::query()->pluck('id', 'name')->all();

        $firstDay = CarbonImmutable::parse(self::FIRST_DAY);
        $span = (int) $firstDay->diffInDays(CarbonImmutable::parse(self::LAST_DAY));

        for ($i = 0; $i < self::GENERATED_ROUNDS; $i++) {
            $course = self::COURSES[$i % count(self::COURSES)];
            $players = fake()->randomElements(array_keys(self::OVER_PAR), fake()->numberBetween(1, 3));

            $createRound->handle($this->card(
                $course,
                $firstDay->addDays(intdiv($i * $span, self::GENERATED_ROUNDS - 1))->toDateString(),
                array_map(fn (string $player): array => [
                    'playerId' => $playerIds[$player],
                    'strokes' => $this->withCappedHole($i, $player, array_map(
                        fn (int $par): int => $par + (int) fake()->randomElement(self::OVER_PAR[$player]),
                        $course['pars'],
                    )),
                ], array_values($players)),
            ));
        }

        $reference = $createRound->handle($this->card(self::COURSES[0], '2026-10-17', [
            ['playerId' => $playerIds['Gab'], 'strokes' => self::REFERENCE_LINES['Gab']],
            ['playerId' => $playerIds['Manu'], 'strokes' => self::REFERENCE_LINES['Manu']],
        ]));

        $path = "rounds/{$reference->id}.webp";
        $photos->put($path, (string) file_get_contents(database_path('data/img3064.webp')));
        $reference->update(['photo_path' => $path]);
    }

    /**
     * @param  list<int>  $strokes
     * @return list<int>
     */
    private function withCappedHole(int $round, string $player, array $strokes): array
    {
        if ($round !== self::CAPPED_HOLE['round'] || $player !== self::CAPPED_HOLE['player']) {
            return $strokes;
        }

        return array_values(array_replace($strokes, [self::CAPPED_HOLE['hole'] - 1 => self::CAPPED_HOLE['strokes']]));
    }

    /**
     * @param  array{courseName: string, tees: string, pars: list<int>, strokeIndexes: list<int>, distances: list<int>}  $course
     * @param  list<array{playerId: string, strokes: list<int>}>  $lines
     */
    private function card(array $course, string $playedOn, array $lines): RoundCard
    {
        return new RoundCard(
            courseName: $course['courseName'],
            tees: $course['tees'],
            playedOn: $playedOn,
            holes: array_map(
                fn (int $par, int $strokeIndex, int $distance): array => ['par' => $par, 'strokeIndex' => $strokeIndex, 'distance' => $distance],
                $course['pars'], $course['strokeIndexes'], $course['distances'],
            ),
            lines: $lines,
        );
    }
}
