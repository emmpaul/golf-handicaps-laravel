<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * A photo of the photos disk, which storage/{path} never serves (design D7 of
 * players-and-rounds): WebP, kept a day by the browser only, or 404 « Photo introuvable ».
 */
trait ServesPhotos
{
    protected function photoResponse(?string $path): StreamedResponse|JsonResponse
    {
        $disk = Storage::disk('photos');

        if ($path === null || ! $disk->exists($path)) {
            return response()->json(['error' => 'Photo introuvable'], 404);
        }

        return $disk->response($path, headers: [
            'Content-Type' => 'image/webp',
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }
}
