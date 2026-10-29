<?php

/*
 * Mutations that turn these tests red:
 * - ScoreFactory or AiUsageFactory deleted: Score or AiUsage has no factory;
 * - a model added to app/Models without its factory, or left out of the literal list;
 * - withPlayers() that gives a score of 16 or skips a hole: RoundCardChecks finds a problem;
 * - PlayerRoundStatFactory without the line of its player on its round: the composite foreign
 *   key of player_round_stats refuses the row;
 * - ImportFactory::ready() without the reference draft: the CHECK of imports refuses a ready
 *   import without draft, or the par total differs;
 * - CourseRatingFactory::proposed() that sets rating_t: the CHECKs of course_ratings refuse a
 *   rating without slope nor source, or the row has a rating it should not.
 */

use App\Domain\Courses\CourseRatingStatus;
use App\Domain\Rounds\RoundCard;
use App\Domain\Rounds\RoundCardChecks;
use App\Models\CourseRating;
use App\Models\Import;
use App\Models\Player;
use App\Models\PlayerRoundStat;
use App\Models\Round;
use Database\Factories\CourseRatingFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

test('every model of app/Models has its factory', function () {
    $models = collect(glob(app_path('Models/*.php')) ?: [])
        ->map(fn (string $path): string => basename($path, '.php'))
        ->sort()->values()->all();

    expect($models)->toBe(['AiUsage', 'CourseRating', 'Import', 'Player', 'PlayerAlias', 'PlayerRoundStat', 'Round', 'RoundHole', 'RoundPlayer', 'Score']);

    foreach ($models as $model) {
        /** @var class-string<Model> $class */
        $class = "App\\Models\\{$model}";

        expect(class_exists(Factory::resolveFactoryName($class)))->toBeTrue("{$model} has no factory")
            ->and($class::factory()->make())->toBeInstanceOf($class);
    }
});

test('a round made with two players has a coherent card', function () {
    $gab = Player::factory()->create(['name' => 'Gab']);
    $manu = Player::factory()->create(['name' => 'Manu']);

    $round = Round::factory()->withPlayers($gab, $manu)->create();

    expect($round->holes()->count())->toBe(18)
        ->and($round->lines()->pluck('player_id')->all())->toBe([$gab->id, $manu->id])
        ->and($round->scores()->count())->toBe(36)
        ->and(RoundCardChecks::problems(RoundCard::fromRound($round)))->toBe([]);
});

test('the history and rating factories need no other preparation', function () {
    $stat = PlayerRoundStat::factory()->create();
    CourseRating::factory()->bethpageRed()->create();

    expect(PlayerRoundStat::query()->count())->toBe(1)
        ->and($stat->round->lines()->pluck('player_id')->all())->toBe([$stat->player_id])
        ->and(CourseRating::query()->get(['course_name', 'tees', 'rating_t', 'slope'])->toArray())
        ->toBe([['course_name' => 'BETHPAGE BLACK', 'tees' => 'Red', 'rating_t' => 714, 'slope' => 144]]);
});

test('a ready import is made with the draft of the reference card', function () {
    $import = Import::factory()->ready()->create();

    expect($import->fresh()?->draft['par']['total'])->toBe(71);
});

test('a rating made in each state of the lookup needs no other preparation', function (Closure $state, array $expected) {
    /** @var CourseRatingFactory $factory */
    $factory = $state(CourseRating::factory());
    $rating = $factory->create()->fresh();

    expect([$rating?->status, $rating?->rating_t, $rating?->slope, $rating?->source, $rating?->note])->toBe($expected);
})->with([
    'confirmed by hand' => [fn (CourseRatingFactory $factory) => $factory->bethpageRed(), [CourseRatingStatus::Confirmed, 714, 144, 'manual', null]],
    'pending' => [fn (CourseRatingFactory $factory) => $factory->pending(), [CourseRatingStatus::Pending, null, null, null, null]],
    'proposed' => [fn (CourseRatingFactory $factory) => $factory->proposed(), [CourseRatingStatus::Proposed, null, null, null, null]],
    'choose course' => [fn (CourseRatingFactory $factory) => $factory->chooseCourse(), [CourseRatingStatus::ChooseCourse, null, null, null, null]],
    'choose tee' => [fn (CourseRatingFactory $factory) => $factory->chooseTee(), [CourseRatingStatus::ChooseTee, null, null, null, null]],
    'manual' => [fn (CourseRatingFactory $factory) => $factory->manual('Lecture IA impossible'), [CourseRatingStatus::Manual, null, null, null, 'Lecture IA impossible']],
    'not found' => [fn (CourseRatingFactory $factory) => $factory->notFound(), [CourseRatingStatus::NotFound, null, null, null, null]],
    'failed' => [fn (CourseRatingFactory $factory) => $factory->failed(), [CourseRatingStatus::Failed, null, null, null, 'SGT injoignable']],
    'confirmed from SGT' => [fn (CourseRatingFactory $factory) => $factory->confirmedFromSgt(), [CourseRatingStatus::Confirmed, 714, 144, 'sgt', null]],
]);

test('the proposal of BETHPAGE BLACK / Red carries the course and the tees read on the SGT card 30', function () {
    $rating = CourseRating::factory()->proposed()->create()->fresh();

    // toEqual: jsonb gives the keys of each object back in its own order.
    expect([$rating?->course_name, $rating?->tees, $rating?->sgt_course_id, $rating?->sgt_course_name, $rating?->sgt_course_location, $rating?->proposed_tee, $rating?->tees_read])
        ->toEqual(['BETHPAGE BLACK', 'Red', 30, 'Bethpage Black', 'Farmingdale, NY', 'Red', [
            ['name' => 'Blue', 'ratingT' => 775, 'slope' => 155],
            ['name' => 'White', 'ratingT' => 742, 'slope' => 148],
            ['name' => 'Red', 'ratingT' => 714, 'slope' => 144],
            ['name' => 'Junior', 'ratingT' => 710, 'slope' => 120],
            ['name' => 'PAR3', 'ratingT' => 540, 'slope' => 120],
        ]]);
});

test('the course to choose offers the SGT courses 477 and 1951, the tee to choose proposes none', function () {
    $chooseCourse = CourseRating::factory()->chooseCourse()->create()->fresh();
    $chooseTee = CourseRating::factory()->chooseTee()->create()->fresh();

    expect($chooseCourse?->candidates)->toEqual([
        ['id' => 477, 'name' => 'Sanctuary', 'location' => 'Sedalia, CO', 'par' => 72],
        ['id' => 1951, 'name' => 'The Pines Sanctuary Cove', 'location' => 'Hope Island, Queensland, Australia', 'par' => 72],
    ])
        ->and([$chooseTee?->sgt_course_id, $chooseTee?->proposed_tee, count($chooseTee->tees_read ?? [])])->toBe([30, null, 5]);
});
