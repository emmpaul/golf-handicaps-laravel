<?php

namespace App\Http\Controllers;

use App\Models\Round;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RoundPhotoController extends Controller
{
    /**
     * The photo of a round's card, from the photos disk that storage/{path} never serves
     * (design D7); behind the passcode session like every web route.
     */
    public function __invoke(Round $round): StreamedResponse|JsonResponse
    {
        $disk = Storage::disk('photos');

        if ($round->photo_path === null || ! $disk->exists($round->photo_path)) {
            return response()->json(['error' => 'Photo introuvable'], 404);
        }

        return $disk->response($round->photo_path, headers: [
            'Content-Type' => 'image/webp',
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }
}
