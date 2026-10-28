<?php

namespace App\Http\Controllers;

use App\Domain\Players\Actions\AssignAlias;
use App\Domain\Players\Actions\RemoveAlias;
use App\Http\Requests\StorePlayerAliasRequest;
use App\Models\Player;
use App\Models\PlayerAlias;
use Illuminate\Http\RedirectResponse;

class PlayerAliasController extends Controller
{
    /**
     * Give an alias to a player; the player who carried it loses it.
     */
    public function store(StorePlayerAliasRequest $request, Player $player, AssignAlias $assignAlias): RedirectResponse
    {
        $assignAlias->handle($player, $request->string('alias')->value());

        return back();
    }

    /**
     * Remove an alias of this player, without confirmation; scopeBindings() makes an alias of
     * another player a 404.
     */
    public function destroy(Player $player, PlayerAlias $alias, RemoveAlias $removeAlias): RedirectResponse
    {
        $removeAlias->handle($alias);

        return back();
    }
}
