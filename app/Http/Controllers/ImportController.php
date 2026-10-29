<?php

namespace App\Http\Controllers;

use App\Domain\Imports\Actions\CreateImport;
use App\Domain\Imports\Actions\DeleteImport;
use App\Domain\Imports\AiReading;
use App\Domain\Imports\ImportEditorState;
use App\Domain\Imports\Queries\ImportList;
use App\Domain\LocalTime;
use App\Domain\Players\Queries\PlayerList;
use App\Domain\Rounds\Queries\KnownCourses;
use App\Http\Requests\StoreImportRequest;
use App\Models\Import;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ImportController extends Controller
{
    /**
     * The import screen: sending a photo when the photos can be read, and the latest imports.
     */
    public function index(ImportList $imports): Response
    {
        $readingEnabled = AiReading::enabled();

        return Inertia::render('import/index', [
            'imports' => $imports->get($readingEnabled),
            'readingEnabled' => $readingEnabled,
        ]);
    }

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

    /**
     * The correction screen of an import, or the page of its round once saved.
     */
    public function show(Import $import, PlayerList $players, KnownCourses $courses): Response|RedirectResponse
    {
        if ($import->round_id !== null) {
            return to_route('rounds.show', $import->round_id);
        }

        return Inertia::render('imports/show', [
            'import' => [
                'id' => $import->id,
                'status' => $import->status->value,
                'label' => $import->status->label(),
                'failure' => $import->failure,
            ],
            'editor' => ImportEditorState::for($import, LocalTime::date(now())),
            'players' => $players->get(),
            'courses' => $courses->get(),
        ]);
    }

    /**
     * Delete an import that is neither being read nor saved, and its photo.
     */
    public function destroy(Import $import, DeleteImport $deleteImport): RedirectResponse
    {
        $deleteImport->handle($import);

        return to_route('import.index');
    }
}
