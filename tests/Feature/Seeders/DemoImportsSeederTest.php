<?php

/*
 * Mutations that turn these tests red:
 * - the seeder pushes ReadScorecardImport for the waiting import: Queue::assertNothingPushed()
 *   fails;
 * - the read line JULIEN left as MANU in the ready import: the names read differ from the
 *   literal list;
 * - the photo of an import not written: the photo is missing from the photos disk;
 * - the usage month taken in UTC instead of the budget time zone: on 2026-11-01 02:30 UTC the
 *   three rows land in 2026-11 instead of 2026-10;
 * - the imports directory not emptied first: the stale file stays on the disk;
 * - DemoImportsSeeder not called by DemoDatabaseSeeder: no import at all.
 */

use App\Domain\Imports\ImportStatus;
use App\Integrations\Ai\ScorecardPhotoReader;
use App\Models\AiUsage;
use App\Models\Import;
use Carbon\CarbonImmutable;
use Database\Seeders\DemoDatabaseSeeder;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('photos');
    Queue::fake();
});

test('the demonstration base has a ready, a failed and a waiting import, each with its photo, and no reading', function () {
    Storage::disk('photos')->put('imports/stale.webp', 'old');

    $this->seed(DemoDatabaseSeeder::class);

    expect(Import::query()->orderBy('status')->get()->map(fn (Import $import): array => [
        $import->status->value,
        $import->failure,
        $import->round_id,
    ])->all())->toBe([
        ['failed', 'Budget IA du mois atteint', null],
        ['queued', null, null],
        ['ready', null, null],
    ]);

    $ready = Import::query()->where('status', ImportStatus::Ready)->sole();
    expect(array_column($ready->draft['players'], 'name'))->toBe(['GAB', 'JULIEN'])
        ->and($ready->draft['courseName'])->toBe('BETHPAGE BLACK')
        ->and($ready->draft['par']['total'])->toBe(71);

    foreach (Import::query()->get() as $import) {
        expect($import->photo_path)->toBe("imports/{$import->id}.webp")
            ->and(Storage::disk('photos')->get($import->photo_path))
            ->toBe(file_get_contents(base_path('database/data/img3064.webp')));
    }

    expect(Storage::disk('photos')->exists('imports/stale.webp'))->toBeFalse();

    Queue::assertNothingPushed();
    ScorecardPhotoReader::assertNeverPrompted();
});

test('the demonstration counts three readings in the current month of the budget time zone', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-01 02:30', 'UTC'));

    $this->seed(DemoDatabaseSeeder::class);

    expect(AiUsage::query()->pluck('month')->all())->toBe(['2026-10', '2026-10', '2026-10'])
        ->and(AiUsage::query()->pluck('cost_micro_usd')->all())->toBe([18000, 18000, 18000]);
});
