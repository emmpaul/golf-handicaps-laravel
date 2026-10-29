<?php

namespace App\Http\Controllers;

use App\Domain\Handicaps\Queries\PlayerHandicapHistory;
use App\Domain\Handicaps\Queries\PlayerIndexComputation;
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
        $selectedId = $this->selectedPlayerId($request, $players);

        return Inertia::render('handicaps/index', [
            'players' => $players,
            'selectedPlayerId' => $selectedId,
            // A closure: the deferred load of the stats does not read the history again (DO39).
            'history' => fn (): array => $selectedId === null ? [] : $history->get($selectedId),
            'scoringStats' => Inertia::defer(fn (): ?array => $selectedId === null ? null : $scoringStats->get($selectedId)),
        ]);
    }

    /**
     * The page « Calcul de l'index » (design D1 and D4 of index-computation-page): the player
     * chosen as on the Handicaps page, and the facts of the player's computation, null without
     * player.
     */
    public function computation(Request $request, PlayerStandings $standings, PlayerIndexComputation $computation): Response
    {
        $players = $standings->get();
        $selectedId = $this->selectedPlayerId($request, $players);

        return Inertia::render('handicaps/computation', [
            'players' => $players,
            'selectedPlayerId' => $selectedId,
            'computation' => fn (): ?array => $selectedId === null ? null : $computation->get($selectedId),
        ]);
    }

    /**
     * The player named by ?joueur=, or the first by name when the identifier names no player.
     *
     * @param  list<array{id: string}>  $players
     */
    private function selectedPlayerId(Request $request, array $players): ?string
    {
        $ids = array_column($players, 'id');
        $requested = $request->query('joueur');

        return in_array($requested, $ids, true) ? $requested : ($ids[0] ?? null);
    }
}
