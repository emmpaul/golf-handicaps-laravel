<?php

use Database\Seeders\DemoSeeder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

arch('the php preset holds for application code')->preset()->php();

// The php preset leaves out dd and ddd.
arch('application code does not ship debug helpers')
    ->expect(['dd', 'ddd', 'dump', 'ray', 'var_dump'])->not->toBeUsed();

arch('eloquent models use uuid identifiers')
    ->expect('App\Models')->classes()->toUseTrait(HasUuids::class);

arch('the domain layer does not depend on http or the frontend')
    ->expect('App\Domain')->not->toUse(['Illuminate\Http', 'Inertia', 'App\Http']);

arch('the domain layer does not depend on integrations')
    ->expect('App\Domain')->not->toUse('App\Integrations');

arch('integrations do not depend on controllers or the frontend')
    ->expect('App\Integrations')->not->toUse(['App\Http', 'Inertia']);

// Mutation that turns this test red: a seeder of database/seeders that extends Seeder directly.
arch('demo seeders refuse to run in production')
    ->expect('Database\Seeders')->classes()->toExtend(DemoSeeder::class)
    ->ignoring(DemoSeeder::class);

// Whs, NetRanking and their values: everything of App\Domain\Handicaps but the Actions and
// the Queries, which read and write the base.
// Mutation that turns this test red: `use Illuminate\Support\Facades\DB` in Whs, called in a method.
arch('the handicap computation is pure: no framework, no model')
    ->expect('App\Domain\Handicaps')->not->toUse(['Illuminate', 'App\Models'])
    ->ignoring(['App\Domain\Handicaps\Actions', 'App\Domain\Handicaps\Queries']);
