<?php

/*
 * Spec scorecard-import « Envoi d'une photo », « Conservation de la photo »; spec
 * scorecard-ai-completion « Lecture désactivée sans clé d'API »; spec deployment « Photos de
 * carte acceptées en production »; design D6, D8.
 *
 * Fixtures under tests/fixtures/scorecards/:
 * - gspro-img3064.jpg, copied from the Next repository: 3000×2250, iPhone EXIF (make Apple);
 * - sideways-exif-6.jpg, a 200×400 JPEG whose pixels lie on their side (red on the left half,
 *   blue on the right half) with EXIF Orientation 6, which shows it upright as 400×200, red on
 *   top. Produced in the laravel.test container with:
 *
 *   vendor/bin/sail php -r '$i = new Imagick(); $i->newImage(400, 200, "red"); $b = new Imagick();
 *   $b->newImage(400, 100, "blue"); $i->compositeImage($b, Imagick::COMPOSITE_OVER, 0, 100);
 *   $i->rotateImage("white", -90); $i->setImageFormat("jpeg"); $j = $i->getImageBlob();
 *   $app1 = "\xFF\xE1\x00\x22Exif\x00\x00MM\x00\x2A\x00\x00\x00\x08\x00\x01\x01\x12\x00\x03
 *   \x00\x00\x00\x01\x00\x06\x00\x00\x00\x00\x00\x00"; file_put_contents(
 *   "tests/fixtures/scorecards/sideways-exif-6.jpg", substr($j, 0, 20).$app1.substr($j, 20));'
 *
 *   (one line; the APP1 segment, a TIFF IFD with the single tag 0x0112 = 6, goes right after
 *   the JFIF APP0 segment of 18 bytes that follows the SOI marker).
 *
 * Mutations that turn these tests red:
 * - the received file written as is: the stored file is a JPEG with its EXIF profile;
 * - the orientation not applied (autoOrientation: false on the ImageManager): the stored photo
 *   is 200×400, red on the left;
 * - the job pushed before the commit (afterCommit() dropped): the job runs inside the
 *   transaction that is then rolled back.
 */

use App\Domain\Imports\Actions\CreateImport;
use App\Domain\Imports\ImportStatus;
use App\Jobs\ReadScorecardImport;
use App\Models\Import;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;

function scorecardUpload(string $fixture): UploadedFile
{
    return new UploadedFile(base_path("tests/fixtures/scorecards/{$fixture}"), $fixture, 'image/jpeg', test: true);
}

function postPhoto(UploadedFile $photo): TestResponse
{
    return test()->withHeader('X-Inertia', 'true')->post('/imports', ['photo' => $photo, 'upload_key' => '0195f3a2-7c4e-7b1a-9d2f-3e8c1a5b6d72']);
}

/**
 * The pure colour nearest to a pixel of a lossy image: each channel rounded to 0 or 255.
 */
function primaryColorAt(Imagick $image, int $x, int $y): string
{
    $color = $image->getImagePixelColor($x, $y)->getColor();
    $rounded = array_map(fn (int $channel): int => $channel < 128 ? 0 : 255, [$color['r'], $color['g'], $color['b']]);

    return match ($rounded) {
        [255, 0, 0] => 'red',
        [0, 0, 255] => 'blue',
        default => implode(',', $rounded),
    };
}

beforeEach(function () {
    Storage::fake('photos');
    config(['ai.providers.anthropic.key' => 'test-key']);
});

describe('with a session', function () {
    beforeEach(function () {
        Queue::fake();
        $this->withPasscodeSession();
    });

    test('the reference photo creates a queued import, stored as a WebP without EXIF, and queues its reading', function () {
        postPhoto(scorecardUpload('gspro-img3064.jpg'))
            ->assertRedirect()
            ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Lecture en file d\'attente. Tu peux envoyer la photo suivante.']);

        $import = Import::query()->sole();
        $disk = Storage::disk('photos');

        expect($import->status)->toBe(ImportStatus::Queued)
            ->and($import->photo_path)->toBe("imports/{$import->id}.webp")
            ->and($disk->allFiles())->toBe(["imports/{$import->id}.webp"])
            ->and($disk->mimeType($import->photo_path))->toBe('image/webp')
            ->and(array_slice((array) getimagesize($disk->path($import->photo_path)), 0, 2))->toBe([3000, 2250])
            // The colour profile stays, so that the colours do not shift; EXIF and XMP are gone.
            ->and((new Imagick($disk->path($import->photo_path)))->getImageProfiles('*', false))->toBe(['icc']);

        Queue::assertPushed(ReadScorecardImport::class, 1);
        Queue::assertPushed(ReadScorecardImport::class, fn (ReadScorecardImport $job): bool => $job->importId === $import->id);
    });

    test('a photo turned by its EXIF orientation is stored upright', function () {
        postPhoto(scorecardUpload('sideways-exif-6.jpg'))->assertRedirect();

        $stored = new Imagick(Storage::disk('photos')->path(Import::query()->sole()->photo_path));

        expect([$stored->getImageWidth(), $stored->getImageHeight()])->toBe([400, 200])
            ->and(primaryColorAt($stored, 200, 20))->toBe('red')
            ->and(primaryColorAt($stored, 200, 180))->toBe('blue');
    });

    test('a file that is not an image is refused without import nor file', function () {
        postPhoto(UploadedFile::fake()->createWithContent('carte.jpg', 'pas une image'))
            ->assertRedirect()
            ->assertInertiaFlash('toast', ['type' => 'error', 'message' => 'Photo illisible']);

        expect(Import::query()->count())->toBe(0)
            ->and(Storage::disk('photos')->allFiles())->toBe([]);
        Queue::assertNothingPushed();
    });

    test('a photo over 20 MB is refused with the limit', function () {
        postPhoto(UploadedFile::fake()->create('carte.jpg', 25 * 1024, 'image/jpeg'))
            ->assertSessionHasErrors(['photo' => 'La photo dépasse 20 Mo']);

        expect(Import::query()->count())->toBe(0)
            ->and(Storage::disk('photos')->allFiles())->toBe([]);
    });

    test('without an API key the photo is refused without import', function () {
        config(['ai.providers.anthropic.key' => '']);

        postPhoto(scorecardUpload('gspro-img3064.jpg'))
            ->assertRedirect()
            ->assertInertiaFlash('toast', ['type' => 'error', 'message' => 'Lecture des photos désactivée : aucune clé d’IA n’est configurée.']);

        expect(Import::query()->count())->toBe(0)
            ->and(Storage::disk('photos')->allFiles())->toBe([]);
        Queue::assertNothingPushed();
    });
});

test('without a session the upload is sent to /login without import', function () {
    postPhoto(scorecardUpload('gspro-img3064.jpg'))->assertRedirect('/login');

    expect(Import::query()->count())->toBe(0);
});

test('the reading job leaves only once the import is committed', function () {
    // The sync queue of phpunit.xml, which honours afterCommit: the job would run right away.
    $ran = false;
    Queue::before(function () use (&$ran): void {
        $ran = true;
    });

    try {
        DB::transaction(function (): void {
            app(CreateImport::class)->handle(scorecardUpload('gspro-img3064.jpg'), '0195f3a2-7c4e-7b1a-9d2f-3e8c1a5b6d72');

            throw new LogicException('rolled back');
        });
    } catch (LogicException) {
    }

    expect($ran)->toBeFalse()
        ->and(Import::query()->count())->toBe(0);
});
