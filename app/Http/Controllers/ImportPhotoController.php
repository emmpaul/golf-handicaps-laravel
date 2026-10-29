<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ServesPhotos;
use App\Models\Import;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImportPhotoController extends Controller
{
    use ServesPhotos;

    /**
     * The photo of an import, the one its round shares once saved.
     */
    public function __invoke(Import $import): StreamedResponse|JsonResponse
    {
        return $this->photoResponse($import->photo_path);
    }
}
