<?php

/*
 * Spec scorecard-import « Envoi sans doublon », « Envoi sans session », « Photo refusée à
 * l'envoi »; design D8 of offline-pwa: the photo queue of the device sends each photo as JSON
 * with an upload key drawn on the device, and a replay of the same key creates nothing.
 *
 * Mutations that turn these tests red:
 * - the lookup by key removed from CreateImport: a known key decodes the photo, and a file that
 *   is not an image throws UnreadablePhoto (the HTTP replay alone stays green: the unique
 *   violation then returns the first import);
 * - the unique violation not caught in CreateImport: the concurrent insertion throws;
 * - the written photo not erased on the unique violation: a file stays under imports/;
 * - expectsJson() ignored in ImportController::store: the JSON request gets the Inertia
 *   redirect (302) instead of 201 { id };
 * - upload_key no longer required, or its uuid rule dropped, in StoreImportRequest: the photo
 *   without key, or with « 12 », creates an import instead of the 422;
 * - the JSON branch of the DomainException rendering (bootstrap/app.php) removed: the reading
 *   disabled and the unreadable photo get a redirect instead of 422 { error };
 * - RequirePasscode redirecting every refused write to /login: 302 instead of the 401.
 */

use App\Domain\Imports\Actions\CreateImport;
use App\Jobs\ReadScorecardImport;
use App\Models\Import;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;

const FIRST_UPLOAD_KEY = '0195f3a2-7c4e-7b1a-9d2f-3e8c1a5b6d70';
const SECOND_UPLOAD_KEY = '0195f3a2-7c4e-7b1a-9d2f-3e8c1a5b6d71';

function referencePhoto(): UploadedFile
{
    return new UploadedFile(base_path('tests/fixtures/scorecards/gspro-img3064.jpg'), 'gspro-img3064.jpg', 'image/jpeg', test: true);
}

/**
 * A request sent by the photo queue of the device: JSON, outside Inertia.
 *
 * @param  array<string, mixed>  $fields
 */
function queueUpload(array $fields): TestResponse
{
    return test()->post('/imports', $fields, ['Accept' => 'application/json']);
}

beforeEach(function () {
    Storage::fake('photos');
    Queue::fake();
    config(['ai.providers.anthropic.key' => 'test-key']);
});

describe('with a session', function () {
    beforeEach(function () {
        $this->withPasscodeSession();
    });

    test('a photo sent as JSON with its key creates the import and answers 201 with its id', function () {
        $response = queueUpload(['photo' => referencePhoto(), 'upload_key' => FIRST_UPLOAD_KEY])
            ->assertStatus(201);

        $import = Import::query()->sole();

        $response->assertExactJson(['id' => $import->id]);
        expect($import->upload_key)->toBe(FIRST_UPLOAD_KEY)
            ->and(Storage::disk('photos')->allFiles())->toBe(["imports/{$import->id}.webp"]);
        Queue::assertPushed(ReadScorecardImport::class, 1);
    });

    test('the same photo sent again with the same key answers 200 with the same id and creates nothing', function () {
        $first = queueUpload(['photo' => referencePhoto(), 'upload_key' => FIRST_UPLOAD_KEY])->assertStatus(201);
        $import = Import::query()->sole();

        queueUpload(['photo' => referencePhoto(), 'upload_key' => FIRST_UPLOAD_KEY])
            ->assertStatus(200)
            ->assertExactJson(['id' => $first->json('id')]);

        expect(Import::query()->pluck('id')->all())->toBe([$import->id])
            ->and(Storage::disk('photos')->allFiles())->toBe(["imports/{$import->id}.webp"]);
        Queue::assertPushed(ReadScorecardImport::class, 1);
    });

    test('two photos with two different keys create two imports', function () {
        queueUpload(['photo' => referencePhoto(), 'upload_key' => FIRST_UPLOAD_KEY])->assertStatus(201);
        queueUpload(['photo' => referencePhoto(), 'upload_key' => SECOND_UPLOAD_KEY])->assertStatus(201);

        expect(Import::query()->orderBy('upload_key')->pluck('upload_key')->all())->toBe([FIRST_UPLOAD_KEY, SECOND_UPLOAD_KEY])
            ->and(Storage::disk('photos')->allFiles())->toHaveCount(2);
        Queue::assertPushed(ReadScorecardImport::class, 2);
    });

    test('a photo without key is refused with 422 and creates nothing', function () {
        queueUpload(['photo' => referencePhoto()])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['upload_key' => 'Clé d’envoi manquante']);

        expect(Import::query()->count())->toBe(0)
            ->and(Storage::disk('photos')->allFiles())->toBe([]);
    });

    test('a key that is not a uuid is refused with 422 and creates nothing', function () {
        queueUpload(['photo' => referencePhoto(), 'upload_key' => '12'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['upload_key' => 'Clé d’envoi invalide']);

        expect(Import::query()->count())->toBe(0)
            ->and(Storage::disk('photos')->allFiles())->toBe([]);
    });

    test('with the reading disabled the JSON upload answers 422 with the refusal and creates nothing', function () {
        config(['ai.providers.anthropic.key' => '']);

        queueUpload(['photo' => referencePhoto(), 'upload_key' => FIRST_UPLOAD_KEY])
            ->assertStatus(422)
            ->assertExactJson(['error' => 'Lecture des photos désactivée : aucune clé d’IA n’est configurée.']);

        expect(Import::query()->count())->toBe(0)
            ->and(Storage::disk('photos')->allFiles())->toBe([]);
        Queue::assertNothingPushed();
    });

    test('a file that is not an image sent as JSON answers 422 Photo illisible', function () {
        queueUpload(['photo' => UploadedFile::fake()->createWithContent('carte.jpg', 'pas une image'), 'upload_key' => FIRST_UPLOAD_KEY])
            ->assertStatus(422)
            ->assertExactJson(['error' => 'Photo illisible']);

        expect(Import::query()->count())->toBe(0);
    });
});

test('without a session the JSON upload answers 401 Session requise and creates nothing', function () {
    queueUpload(['photo' => referencePhoto(), 'upload_key' => FIRST_UPLOAD_KEY])
        ->assertStatus(401)
        ->assertExactJson(['error' => 'Session requise']);

    expect(Import::query()->count())->toBe(0)
        ->and(Storage::disk('photos')->allFiles())->toBe([]);
});

test('a known key returns the existing import without decoding the photo', function () {
    $existing = Import::factory()->withUploadKey(FIRST_UPLOAD_KEY)->create();

    // Not an image: decoding it would throw UnreadablePhoto.
    $import = app(CreateImport::class)->handle(UploadedFile::fake()->createWithContent('carte.jpg', 'pas une image'), FIRST_UPLOAD_KEY);

    expect($import->id)->toBe($existing->id)
        ->and($import->wasRecentlyCreated)->toBeFalse()
        ->and(Storage::disk('photos')->allFiles())->toBe([]);
    Queue::assertNothingPushed();
});

test('a concurrent insertion of the same key returns the first import and leaves no file', function () {
    // The other request inserts its import right after this one looked the key up and missed.
    $competitor = null;
    DB::listen(function (QueryExecuted $query) use (&$competitor): void {
        if ($competitor === null && str_starts_with($query->sql, 'select') && str_contains($query->sql, 'upload_key')) {
            $competitor = Import::factory()->withUploadKey(FIRST_UPLOAD_KEY)->create();
        }
    });

    $import = app(CreateImport::class)->handle(referencePhoto(), FIRST_UPLOAD_KEY);

    expect($competitor)->not->toBeNull()
        ->and($import->id)->toBe($competitor->id)
        ->and($import->wasRecentlyCreated)->toBeFalse()
        ->and(Import::query()->pluck('id')->all())->toBe([$competitor->id])
        ->and(Storage::disk('photos')->allFiles())->toBe([]);
    Queue::assertNothingPushed();
});
