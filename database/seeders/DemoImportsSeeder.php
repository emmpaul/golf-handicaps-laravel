<?php

namespace Database\Seeders;

use App\Domain\AiUsage\AiBudget;
use App\Domain\Imports\ReadingFailure;
use App\Models\Import;
use Carbon\CarbonImmutable;
use Database\Factories\ImportFactory;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The imports of the demonstration base, each with the reference photo IMG_3064: one ready to
 * check whose second line reads JULIEN, a name no player carries; one failed on the budget; one
 * waiting, with no job pushed, so that it stays waiting and shows its pattern. Three readings of
 * the current month are counted. Nothing calls the AI.
 */
class DemoImportsSeeder extends DemoSeeder
{
    private const int DEMO_READINGS = 3;

    public function run(AiBudget $budget): void
    {
        Storage::disk('photos')->deleteDirectory('imports');

        $draft = ImportFactory::referenceDraft();
        $draft['players'][1]['name'] = 'JULIEN';

        $this->import()->ready($draft)->create();
        $this->import()->failed(ReadingFailure::BUDGET_REACHED)->create();
        $this->import()->queued()->create();

        for ($i = 0; $i < self::DEMO_READINGS; $i++) {
            $budget->record(config()->string('golf.ai.model'), 3000, 1200, CarbonImmutable::now());
        }
    }

    /**
     * An import whose photo sits at imports/<its id>.webp, as CreateImport writes it.
     */
    private function import(): ImportFactory
    {
        $id = (string) Str::uuid7();

        return Import::factory()->state(['id' => $id, 'photo_path' => "imports/{$id}.webp"])->withPhoto();
    }
}
