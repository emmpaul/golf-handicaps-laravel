<?php

/*
 * Mutations that turn these tests red:
 * - move the guard out of DemoSeeder::__invoke() into DatabaseSeeder::run(): the seeder called
 *   alone by --class writes its row in production;
 * - make the guard refuse whatever the environment: the seeder no longer writes outside
 *   production.
 */

use App\Models\Player;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Support\Seeders\WritesCacheRowSeeder;

test('the default seeding fails in production without creating a player', function () {
    $this->app['env'] = 'production';

    expect(fn () => Artisan::call('db:seed', ['--force' => true]))
        ->toThrow(RuntimeException::class, 'Les données de démonstration ne peuvent pas être écrites en production.');

    expect(Player::query()->count())->toBe(0);
});

test('a demonstration seeder called alone fails in production without writing', function () {
    $this->app['env'] = 'production';

    expect(fn () => Artisan::call('db:seed', ['--class' => WritesCacheRowSeeder::class, '--force' => true]))
        ->toThrow(RuntimeException::class, 'Les données de démonstration ne peuvent pas être écrites en production.');

    expect(DB::table('cache')->count())->toBe(0);
});

test('a demonstration seeder writes outside production', function () {
    Artisan::call('db:seed', ['--class' => WritesCacheRowSeeder::class]);

    expect(DB::table('cache')->pluck('key')->all())->toBe(['demo-row']);
});
