<?php

namespace App\Http\Controllers;

use App\Domain\Imports\Actions\SaveImportCorrection;
use App\Domain\Imports\Exceptions\ImportAlreadySaved;
use App\Domain\Imports\Exceptions\ImportBeingRead;
use App\Http\Requests\ImportCorrectionRequest;
use App\Models\Import;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class ImportCorrectionController extends Controller
{
    /**
     * Keep the correction sent by the autosave: 204, or 409 with the reason of the refusal for
     * an import being read or already saved.
     */
    public function __invoke(ImportCorrectionRequest $request, Import $import, SaveImportCorrection $saveCorrection): Response|JsonResponse
    {
        try {
            $saveCorrection->handle($import, $request->card());
        } catch (ImportAlreadySaved|ImportBeingRead $refusal) {
            return response()->json(['error' => $refusal->getMessage()], 409);
        }

        return response()->noContent();
    }
}
