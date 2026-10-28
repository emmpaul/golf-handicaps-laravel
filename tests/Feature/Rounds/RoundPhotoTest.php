<?php

/*
 * Spec rounds « Photo d'un round »; design D7.
 *
 * Mutations that turn these tests red:
 * - the photos disk rooted under the root of local (storage_path('app/private/photos')): the
 *   signed storage/{path} address of local answers 200 with the image (with serve => true on
 *   top, the two disks conflict at /storage and the application does not boot);
 * - the photo served without its Cache-Control header: the response gets the framework's
 *   « no-cache, private » instead of « max-age=86400, private »;
 * - the missing file not checked: the response fails with 500 instead of the 404;
 * - the 404 written as abort(404): the body lacks { "error": "Photo introuvable" }.
 */

use App\Models\Player;
use App\Models\Round;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->withPasscodeSession();
});

test('the photo of a round is served as private WebP', function () {
    Storage::fake('photos');
    $round = Round::factory()->withPlayers(Player::factory()->create())->withPhoto()->create();

    $response = $this->get("/rounds/{$round->id}/photo")
        ->assertOk()
        ->assertHeader('Content-Type', 'image/webp')
        ->assertHeader('Cache-Control', 'max-age=86400, private');

    expect($response->streamedContent())->toBe(Storage::disk('photos')->get((string) $round->photo_path));
});

test('a round without photo or whose file is gone answers 404', function (Closure $makeRound) {
    Storage::fake('photos');

    $this->get('/rounds/'.$makeRound()->id.'/photo')
        ->assertNotFound()
        ->assertExactJson(['error' => 'Photo introuvable']);
})->with([
    'without photo' => [fn () => Round::factory()->create()],
    'file gone' => [fn () => Round::factory()->create(['photo_path' => 'rounds/gone.webp'])],
]);

test('the photo is not reachable through storage/{path}', function () {
    // The real disks, not fakes: the test is about where the photos disk lies.
    $round = Round::factory()->withPhoto()->create();
    $photo = (string) realpath(Storage::disk('photos')->path((string) $round->photo_path));

    try {
        $localRoot = rtrim((string) realpath(Storage::disk('local')->path('')), '/').'/';
        // The address of the file relative to the served disk; outside its root, the absolute
        // path, which the served disk resolves under its own root.
        $relative = Str::startsWith($photo, $localRoot) ? Str::after($photo, $localRoot) : $photo;

        expect($photo)->not->toBe('');

        $this->get(Storage::disk('local')->temporaryUrl($relative, now()->addMinute()))->assertNotFound();
    } finally {
        Storage::disk('photos')->delete((string) $round->photo_path);
    }
});
