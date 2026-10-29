<?php

namespace App\Http\Controllers;

use App\Domain\Imports\Actions\SaveImportedRound;
use App\Domain\Imports\Exceptions\ImportAlreadySaved;
use App\Domain\Rounds\Queries\FindDuplicateRound;
use App\Http\Controllers\Concerns\SavesRoundCards;
use App\Http\Requests\StoreImportedRoundRequest;
use App\Models\Import;
use Illuminate\Http\RedirectResponse;

class ImportRoundController extends Controller
{
    use SavesRoundCards;

    /**
     * Save the round of an import, then open the list of rounds; a card that looks like a saved
     * round goes back to the correction screen, as a manual card does. An import already saved
     * is refused first: the duplicate check would find its own round (design D6).
     */
    public function __invoke(StoreImportedRoundRequest $request, Import $import, FindDuplicateRound $duplicates, SaveImportedRound $saveRound): RedirectResponse
    {
        if ($import->round_id !== null) {
            throw new ImportAlreadySaved;
        }

        if (($duplicate = $this->duplicateOf($request, $duplicates)) !== null) {
            return $duplicate;
        }

        $saveRound->handle($import, $request->card());

        return $this->saved(to_route('rounds.index'), 'Round enregistré.');
    }
}
