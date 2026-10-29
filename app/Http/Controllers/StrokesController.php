<?php

namespace App\Http\Controllers;

use App\Domain\Courses\Queries\CourseOverview;
use App\Domain\Handicaps\Queries\PlayerStandings;
use Inertia\Inertia;
use Inertia\Response;

class StrokesController extends Controller
{
    /**
     * The Strokes page: every player with his current index, a player without index listed
     * greyed out, and the known courses with their rating and stroke indexes. The strokes are
     * computed in the browser, nothing is written (design D5).
     */
    public function index(PlayerStandings $standings, CourseOverview $courses): Response
    {
        return Inertia::render('strokes/index', [
            'players' => array_map(
                fn (array $player): array => ['id' => $player['id'], 'name' => $player['name'], 'indexT' => $player['indexT']],
                $standings->get(),
            ),
            'courses' => $courses->get(),
        ]);
    }
}
