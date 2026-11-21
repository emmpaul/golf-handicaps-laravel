<?php

namespace App\Http\Controllers;

use App\Domain\Handicaps\Queries\VirtualMatch;
use App\Domain\LocalTime;
use App\Domain\Players\Queries\PlayerList;
use App\Domain\Rounds\Actions\CreateRound;
use App\Domain\Rounds\Actions\DeleteRound;
use App\Domain\Rounds\Actions\UpdateRound;
use App\Domain\Rounds\Queries\FindDuplicateRound;
use App\Domain\Rounds\Queries\KnownCourses;
use App\Domain\Rounds\Queries\RoundDetail;
use App\Domain\Rounds\Queries\RoundList;
use App\Domain\Rounds\Queries\VirtualMatchSuggestion;
use App\Domain\Rounds\RoundCard;
use App\Http\Controllers\Concerns\SavesRoundCards;
use App\Http\Requests\CompareRoundsRequest;
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
     * The virtual match (spec virtual-match, design D4): the known courses, and the match of the
     * requested course and tees with the picks of the address. An unknown or missing course
     * falls to the first known one, without picks; nothing is written.
     */
    public function compare(CompareRoundsRequest $request, KnownCourses $knownCourses, VirtualMatch $virtualMatch): Response
    {
        $courses = array_map(
            fn (array $course): array => ['courseName' => $course['courseName'], 'tees' => $course['tees']],
            $knownCourses->get(),
        );
        $requested = $this->knownCourse($courses, $request->courseName(), $request->tees());
        $course = $requested ?? $courses[0] ?? null;

        return Inertia::render('rounds/compare', [
            'courses' => $courses,
            'match' => $course === null ? null : $virtualMatch->for(
                $course['courseName'],
                $course['tees'],
                $requested === null ? [] : $request->picks(),
            ),
        ]);
    }

    /**
     * The page of a round: header, gross, card and photo, and the players to suggest for a
     * virtual match with the picks of its link: the players of the card in order, then the
     * suggested ones (spec virtual-match, design D6).
     */
    public function show(Round $round, RoundDetail $detail, VirtualMatchSuggestion $suggestion): Response
    {
        $roundDetail = $detail->for($round);
        $suggested = $suggestion->for($round);
        $picks = [
            ...array_map(fn (array $line): string => "{$round->id}.{$line['playerId']}", $roundDetail['card']['lines']),
            ...array_map(fn (array $line): string => "{$line['roundId']}.{$line['playerId']}", $suggested),
        ];

        return Inertia::render('rounds/show', [
            'round' => $roundDetail,
            'virtualMatch' => [
                'suggested' => array_map(
                    fn (array $line): array => ['name' => $line['name'], 'colorSlot' => $line['colorSlot'], 'playedOn' => $line['playedOn']],
                    $suggested,
                ),
                'picks' => implode(',', $picks),
            ],
        ]);
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

    /**
     * The known course of the requested name and tees, compared without case nor surrounding
     * spaces as KnownCourses groups them, null when none.
     *
     * @param  list<array{courseName: string, tees: string}>  $courses
     * @return array{courseName: string, tees: string}|null
     */
    private function knownCourse(array $courses, ?string $courseName, ?string $tees): ?array
    {
        if ($courseName === null || $tees === null) {
            return null;
        }

        $key = fn (string $value): string => mb_strtolower(trim($value));

        return array_find($courses, fn (array $course): bool => $key($course['courseName']) === $key($courseName)
            && $key($course['tees']) === $key($tees));
    }
}
