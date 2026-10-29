<?php

namespace App\Http\Controllers;

use App\Domain\LocalTime;
use App\Domain\Players\Queries\PlayerList;
use App\Domain\Rounds\Actions\CreateRound;
use App\Domain\Rounds\Actions\DeleteRound;
use App\Domain\Rounds\Actions\UpdateRound;
use App\Domain\Rounds\Queries\FindDuplicateRound;
use App\Domain\Rounds\Queries\KnownCourses;
use App\Domain\Rounds\Queries\RoundDetail;
use App\Domain\Rounds\Queries\RoundList;
use App\Domain\Rounds\RoundCard;
use App\Http\Controllers\Concerns\SavesRoundCards;
use App\Http\Requests\StoreRoundRequest;
use App\Http\Requests\UpdateRoundRequest;
use App\Models\Round;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class RoundController extends Controller
{
    use SavesRoundCards;

    /**
     * The rounds from the latest to the oldest, with the gross of their players.
     */
    public function index(RoundList $rounds): Response
    {
        return Inertia::render('rounds/index', ['rounds' => $rounds->get()]);
    }

    /**
     * The empty round editor: one line, the day in Montreal (design D9), the known courses.
     */
    public function create(PlayerList $players, KnownCourses $courses): Response
    {
        return Inertia::render('rounds/create', [
            'today' => LocalTime::date(now()),
            'players' => $players->get(),
            'courses' => $courses->get(),
        ]);
    }

    /**
     * The page of a round: header, gross, card and photo.
     */
    public function show(Round $round, RoundDetail $detail): Response
    {
        return Inertia::render('rounds/show', ['round' => $detail->for($round)]);
    }

    /**
     * The round editor filled with the saved round.
     */
    public function edit(Round $round, PlayerList $players, KnownCourses $courses): Response
    {
        return Inertia::render('rounds/edit', [
            'round' => ['id' => $round->id, ...RoundCard::fromRound($round)->toArray()],
            'players' => $players->get(),
            'courses' => $courses->get(),
        ]);
    }

    /**
     * Save a new round, then open the list. A card that looks like a saved round goes back to
     * the editor with that round, unless the person saves it anyway (design D5).
     */
    public function store(StoreRoundRequest $request, FindDuplicateRound $duplicates, CreateRound $createRound): RedirectResponse
    {
        if (($duplicate = $this->duplicateOf($request, $duplicates)) !== null) {
            return $duplicate;
        }

        $createRound->handle($request->card());

        return $this->saved(to_route('rounds.index'), 'Round enregistré.');
    }

    /**
     * Correct a saved round, then open its page; the round is never its own duplicate.
     */
    public function update(UpdateRoundRequest $request, Round $round, FindDuplicateRound $duplicates, UpdateRound $updateRound): RedirectResponse
    {
        if (($duplicate = $this->duplicateOf($request, $duplicates, $round)) !== null) {
            return $duplicate;
        }

        $updateRound->handle($round, $request->card());

        return $this->saved(to_route('rounds.show', $round), 'Round enregistré.');
    }

    /**
     * Delete a round and its photo; its players stay.
     */
    public function destroy(Round $round, DeleteRound $deleteRound): RedirectResponse
    {
        $deleteRound->handle($round);

        return $this->saved(to_route('rounds.index'), 'Round supprimé.');
    }
}
