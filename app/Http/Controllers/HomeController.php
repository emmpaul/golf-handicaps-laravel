<?php

namespace App\Http\Controllers;

use App\Domain\Handicaps\Queries\PlayerStandings;
use App\Domain\Rounds\Queries\LatestRound;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    /**
     * The home page: the players by index and the latest round played with its net leaderboard.
     * The imports to check come with the shared pendingImports (design D7, DO17).
     */
    public function __invoke(PlayerStandings $standings, LatestRound $latestRound): Response
    {
        return Inertia::render('home', [
            'players' => $standings->ranked(),
            'latestRound' => $latestRound->get(),
        ]);
    }
}
