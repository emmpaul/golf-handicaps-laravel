<?php

namespace App\Http\Controllers;

use App\Domain\Players\Actions\AddPlayer;
use App\Domain\Players\Actions\RenamePlayer;
use App\Domain\Players\Queries\PlayerOverview;
use App\Http\Requests\StorePlayerRequest;
use App\Http\Requests\UpdatePlayerRequest;
use App\Models\Player;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PlayerController extends Controller
{
    /**
     * The players of the group with their aliases.
     */
    public function index(PlayerOverview $overview): Response
    {
        return Inertia::render('players/index', ['players' => $overview->get()]);
    }

    /**
     * Add a player, then go back: to the Players page, or to the round editor that created it.
     */
    public function store(StorePlayerRequest $request, AddPlayer $addPlayer): RedirectResponse
    {
        $addPlayer->handle($request->string('name')->value());

        return back();
    }

    /**
     * Rename a player; its rounds and aliases stay as they are.
     */
    public function update(UpdatePlayerRequest $request, Player $player, RenamePlayer $renamePlayer): RedirectResponse
    {
        $renamePlayer->handle($player, $request->string('name')->value());

        return back();
    }
}
