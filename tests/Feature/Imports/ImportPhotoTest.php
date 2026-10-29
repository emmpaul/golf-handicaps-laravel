<?php

/*
 * Spec scorecard-import « Conservation de la photo »; design D6, D8.
 *
 * Mutations that turn these tests red:
 * - the route without whereUuid() and Import binding without the uuid check of HasUuids
 *   (resolveRouteBindingQuery overridden by a plain where): the query on the uuid column fails
 *   with 500 instead of the 404. whereUuid() removed alone stays green: HasUuids already turns
 *   a value that is not a uuid into a 404 (HasUniqueStringIds::resolveRouteBindingQuery);
 * - the photo served without its Cache-Control header: « no-cache, private » instead of
 *   « max-age=86400, private »;
 * - the missing file not checked: 500 instead of the 404 « Photo introuvable ».
 */

use App\Models\Import;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('photos');
});

describe('with a session', function () {
    beforeEach(function () {
        $this->withPasscodeSession();
    });

    test('the photo of an import is served as private WebP', function () {
        $import = Import::factory()->withPhoto()->create();

        $response = $this->get("/imports/{$import->id}/photo")
            ->assertOk()
            ->assertHeader('Content-Type', 'image/webp')
            ->assertHeader('Cache-Control', 'max-age=86400, private');

        expect($response->streamedContent())->toBe(file_get_contents(database_path('data/img3064.webp')));
    });

    test('an import whose file is gone answers 404', function () {
        $import = Import::factory()->create();

        $this->get("/imports/{$import->id}/photo")
            ->assertNotFound()
            ->assertExactJson(['error' => 'Photo introuvable']);
    });

    test('an address that is not a uuid answers 404', function () {
        $this->get('/imports/12/photo')->assertNotFound();
    });
});

test('without a session the photo is not sent', function () {
    $import = Import::factory()->withPhoto()->create();

    $this->get("/imports/{$import->id}/photo")->assertRedirect('/login');
});
