<?php

/*
 * Mutations that turn these tests red:
 * - ScoreFactory deleted: Score has no factory;
 * - a model added to app/Models without its factory, or left out of the literal list;
 * - withPlayers() that gives a score of 16 or skips a hole: RoundCardChecks finds a problem.
 */

use App\Domain\Rounds\RoundCard;
use App\Domain\Rounds\RoundCardChecks;
use App\Models\Player;
use App\Models\Round;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

test('every model of app/Models has its factory', function () {
    $models = collect(glob(app_path('Models/*.php')) ?: [])
        ->map(fn (string $path): string => basename($path, '.php'))
        ->sort()->values()->all();

    expect($models)->toBe(['Player', 'PlayerAlias', 'Round', 'RoundHole', 'RoundPlayer', 'Score']);

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
