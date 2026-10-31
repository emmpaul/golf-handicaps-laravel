<?php

namespace App\Domain\Imports\Actions;

use App\Domain\Imports\Exceptions\UnreadablePhoto;
use App\Domain\Imports\ImportStatus;
use App\Domain\Imports\WebpPhoto;
use App\Jobs\ReadScorecardImport;
use App\Models\Import;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use SplFileInfo;
use Throwable;

/**
 * Keeps the photo of a card as WebP on the photos disk, creates its queued import and queues
 * its reading once the import is committed (design D8). A failed transaction erases the photo.
 *
 * The upload key drawn on the device makes it idempotent (offline-pwa, design D8): a key
 * already received returns its import without decoding the photo, and the loser of two
 * concurrent uploads of one key erases its photo and returns the winner. The returned import
 * was created by this call when wasRecentlyCreated is true.
 */
final class CreateImport
{
    /**
     * @throws UnreadablePhoto before any write
     */
    public function handle(SplFileInfo $photo, string $uploadKey): Import
    {
        $existing = $this->byUploadKey($uploadKey);

        if ($existing !== null) {
            return $existing;
        }

        $webp = WebpPhoto::fromPath($photo->getPathname());

        $id = (string) Str::uuid7();
        $path = "imports/{$id}.webp";
        $disk = Storage::disk('photos');
        $disk->put($path, $webp);

        try {
            return DB::transaction(function () use ($id, $path, $uploadKey): Import {
                $import = new Import(['status' => ImportStatus::Queued, 'photo_path' => $path, 'upload_key' => $uploadKey]);
                // The id is the name of the photo, written before the row.
                $import->id = $id;
                $import->save();

                ReadScorecardImport::dispatch($import->id)->afterCommit();

                return $import;
            });
        } catch (UniqueConstraintViolationException $exception) {
            $disk->delete($path);

            return $this->byUploadKey($uploadKey) ?? throw $exception;
        } catch (Throwable $exception) {
            $disk->delete($path);

            throw $exception;
        }
    }

    private function byUploadKey(string $uploadKey): ?Import
    {
        return Import::query()->where('upload_key', $uploadKey)->first();
    }
}
