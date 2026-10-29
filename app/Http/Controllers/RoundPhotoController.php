<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ServesPhotos;
use App\Models\Round;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RoundPhotoController extends Controller
{
    use ServesPhotos;

    /**
     * The photo of a round's card, behind the passcode session like every web route.
     */
    public function __invoke(Round $round): StreamedResponse|JsonResponse
    {
        return $this->photoResponse($round->photo_path);
    }
}
