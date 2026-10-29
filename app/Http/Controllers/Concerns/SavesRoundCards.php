<?php

namespace App\Http\Controllers\Concerns;

use App\Domain\Rounds\Queries\FindDuplicateRound;
use App\Http\Requests\RoundCardRequest;
use App\Models\Round;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * The answers shared by the controllers that save a round card: the duplicate check and the
 * success toast (design D5 of players-and-rounds).
 */
trait SavesRoundCards
{
    /**
     * Back to the editor with the `duplicate` flash when the card repeats a saved round and the
     * person did not choose to save it anyway; nothing is written.
     */
    private function duplicateOf(RoundCardRequest $request, FindDuplicateRound $duplicates, ?Round $except = null): ?RedirectResponse
    {
        if ($request->forced()) {
            return null;
        }

        $duplicate = $duplicates->for($request->card(), $except);

        return $duplicate === null ? null : Inertia::flash('duplicate', ['roundId' => $duplicate->id])->back();
    }

    private function saved(RedirectResponse $redirect, string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return $redirect;
    }
}
