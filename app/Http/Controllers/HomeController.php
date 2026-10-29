<?php

namespace App\Http\Controllers;

use App\Domain\Handicaps\Queries\PlayerStandings;
use App\Domain\Imports\Queries\PendingImports;
use App\Domain\Rounds\Queries\RoundList;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    /**
     * The home page: the index of each player, the latest round played and the imports to check.
     */
    public function __invoke(PlayerStandings $standings, RoundList $rounds, PendingImports $pendingImports): Response
    {
        return Inertia::render('home', [
            'players' => $standings->get(),
            'latestRound' => $rounds->get(1)[0] ?? null,
            'pendingImports' => $pendingImports->get(),
        ]);
    }
}
