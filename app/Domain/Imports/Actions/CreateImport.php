<?php

namespace App\Domain\Imports\Actions;

use App\Domain\Imports\Exceptions\UnreadablePhoto;
use App\Domain\Imports\ImportStatus;
use App\Domain\Imports\WebpPhoto;
use App\Jobs\ReadScorecardImport;
use App\Models\Import;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use SplFileInfo;
use Throwable;

/**
 * Keeps the photo of a card as WebP on the photos disk, creates its queued import and queues
 * its reading once the import is committed (design D8). A failed transaction erases the photo.
 */
final class CreateImport
{
    /**
     * @throws UnreadablePhoto before any write
     */
    public function handle(SplFileInfo $photo): Import
    {
        $webp = WebpPhoto::fromPath($photo->getPathname());

        $id = (string) Str::uuid7();
        $path = "imports/{$id}.webp";
        $disk = Storage::disk('photos');
        $disk->put($path, $webp);

        try {
            return DB::transaction(function () use ($id, $path): Import {
                $import = new Import(['status' => ImportStatus::Queued, 'photo_path' => $path]);
                // The id is the name of the photo, written before the row.
                $import->id = $id;
                $import->save();

                ReadScorecardImport::dispatch($import->id)->afterCommit();

                return $import;
            });
        } catch (Throwable $exception) {
            $disk->delete($path);

            throw $exception;
        }
    }
}
