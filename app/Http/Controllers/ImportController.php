<?php

namespace App\Http\Controllers;

use App\Domain\Imports\Actions\CreateImport;
use App\Domain\Imports\AiReading;
use App\Http\Requests\StoreImportRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class ImportController extends Controller
{
    /**
     * Keep the photo of a card and queue its reading; refused without an Anthropic key.
     */
    public function store(StoreImportRequest $request, CreateImport $createImport): RedirectResponse
    {
        AiReading::ensureEnabled();

        $createImport->handle($request->photo());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Lecture en file d\'attente. Tu peux envoyer la photo suivante.']);

        return back();
    }
}
