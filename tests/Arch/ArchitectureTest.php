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

// Mutation that turns this test and the jobs-only rule below red:
// `use App\Integrations\Ai\ScorecardPhotoReader` in an Action of App\Domain, called in a method.
arch('the domain layer does not depend on integrations')
    ->expect('App\Domain')->not->toUse('App\Integrations');

// Mutation that turns this test red: `use Laravel\Ai\Enums\Lab` in an Action of App\Domain, used
// in a method.
arch('the domain layer does not call the ai sdk')
    ->expect('App\Domain')->not->toUse('Laravel\Ai');

// Design D9: only the reading job calls the AI integration; the domain writes its result.
// Same mutation as above.
arch('only the jobs use the ai integration')
    ->expect('App\Integrations\Ai')->toOnlyBeUsedIn(['App\Jobs', 'App\Integrations\Ai']);

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

// Design D2: the matching of the SGT list and the links are pure, as Whs.
// Mutation that turns this test red: `use Illuminate\Support\Str` in SgtCourseMatcher, called in a
// method.
arch('the matching on the Simulator Golf Tour list is pure: no framework, no model')
    ->expect('App\Domain\Courses\Sgt')->not->toUse(['Illuminate', 'App\Models']);

// Design D3: SgtClient reaches Simulator Golf Tour for the lookup job only.
// Mutation that turns both tests red: `use App\Integrations\Sgt\SgtClient` in an Action of
// App\Domain, called in a method.
arch('the domain layer does not reach Simulator Golf Tour')
    ->expect('App\Domain')->not->toUse('App\Integrations\Sgt');

arch('only the jobs use the Simulator Golf Tour integration')
    ->expect('App\Integrations\Sgt')->toOnlyBeUsedIn(['App\Jobs', 'App\Integrations\Sgt']);
