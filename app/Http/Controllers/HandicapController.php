<?php

namespace App\Http\Controllers;

use App\Domain\Handicaps\Queries\PlayerHandicapHistory;
use App\Domain\Handicaps\Queries\PlayerScoringStats;
use App\Domain\Handicaps\Queries\PlayerStandings;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HandicapController extends Controller
{
    /**
     * The Handicaps page, one player at a time: the one named by ?joueur=, or the first by name
     * when the identifier names no player (spec handicap-index, « Joueur inconnu »). The STATS
     * block of that player comes after the page (design D13 of visual-redesign).
     */
    public function index(Request $request, PlayerStandings $standings, PlayerHandicapHistory $history, PlayerScoringStats $scoringStats): Response
    {
        $players = $standings->get();
        $ids = array_column($players, 'id');
        $requested = $request->query('joueur');
        $selectedId = in_array($requested, $ids, true) ? $requested : ($ids[0] ?? null);

        return Inertia::render('handicaps/index', [
            'players' => $players,
            'selectedPlayerId' => $selectedId,
            'history' => $selectedId === null ? [] : $history->get($selectedId),
            'scoringStats' => Inertia::defer(fn (): ?array => $selectedId === null ? null : $scoringStats->get($selectedId)),
        ]);
    }
}
