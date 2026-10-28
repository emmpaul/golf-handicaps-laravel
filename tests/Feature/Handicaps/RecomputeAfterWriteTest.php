<?php

/*
 * Port of the recompute cases of tests/server/rounds/{save,edit}.test.ts of the Next.js
 * application (spec handicap-index, « Recalcul complet »).
 *
 * Mutations that turn these tests red:
 * - UpdateRound that recomputes only the players of the new card: Manu, taken off the round of
 *   2026-10-17, keeps 170 as index before his round of 2026-10-24;
 * - DeleteRound without recompute: Gab keeps (88, 170, 185) and (83, 120, 100);
 * - UpdateRound that recomputes before the checks and the write: the corrected round has no
 *   row for Manu nor Gab, their rows went with the former lines.
 */

use App\Domain\Rounds\Actions\DeleteRound;
use App\Domain\Rounds\Actions\UpdateRound;
use App\Domain\Rounds\Exceptions\IncoherentRoundCard;
use App\Domain\Rounds\RoundCard;
use App\Models\Player;
use App\Models\PlayerRoundStat;

beforeEach(function () {
    createPlayersOnTest('Gab', 'Manu', 'Sean');
});

/**
 * The rows of a player by date of play: [gross, differential_t, index_after_t] per round.
 *
 * @return list<list<mixed>>
 */
function grossDifferentialIndexOf(Player $player): array
{
    return statsByDateOf($player, ['gross', 'differential_t', 'index_after_t']);
}

test('correcting a score recomputes the differential and the index', function () {
    createRoundCard(referenceCardOn('2026-10-10', [[$this->manu->id, strokesOverPar(17)]]));
    $round = createRoundCard(referenceCardData($this->gab->id, $this->manu->id));
    $card = referenceCardData($this->gab->id, $this->manu->id);
    $card['lines'][1]['strokes'][0] = 6;

    app(UpdateRound::class)->handle($round, RoundCard::fromArray($card));

    expect(grossDifferentialIndexOf($this->manu))->toBe([[88, 170, 170], [87, 160, 165]]);
});

test('a player replaced on a round keeps only his other rounds', function () {
    createRoundCard(referenceCardOn('2026-10-10', [[$this->manu->id, strokesOverPar(17)]]));
    $round = createRoundCard(referenceCardData($this->gab->id, $this->manu->id));

    app(UpdateRound::class)->handle($round, RoundCard::fromArray(referenceCardData($this->gab->id, $this->sean->id)));

    expect(grossDifferentialIndexOf($this->manu))->toBe([[88, 170, 170]])
        ->and(grossDifferentialIndexOf($this->sean))->toBe([[88, 170, 170]]);
});

test('a player replaced on a round is recomputed on his later rounds', function () {
    $round = createRoundCard(referenceCardData($this->gab->id, $this->manu->id));
    createRoundCard(referenceCardOn('2026-10-24', [[$this->manu->id, strokesOverPar(12)]]));

    app(UpdateRound::class)->handle($round, RoundCard::fromArray(referenceCardData($this->gab->id, $this->sean->id)));

    expect(PlayerRoundStat::query()->where('player_id', $this->manu->id)
        ->get(['gross', 'index_before_t', 'index_after_t'])->toArray())
        ->toBe([['gross' => 83, 'index_before_t' => null, 'index_after_t' => 120]]);
});

test('a refused correction leaves the history unchanged', function () {
    $round = createRoundCard(referenceCardData($this->gab->id, $this->manu->id));
    $card = referenceCardData($this->gab->id, $this->manu->id);
    $card['holes'][2]['par'] = 6;
    $card['lines'][1]['strokes'][0] = 6;

    expect(fn () => app(UpdateRound::class)->handle($round, RoundCard::fromArray($card)))
        ->toThrow(IncoherentRoundCard::class);

    expect(grossDifferentialIndexOf($this->gab))->toBe([[72, 10, 10]])
        ->and(grossDifferentialIndexOf($this->manu))->toBe([[88, 170, 170]]);
});

test('deleting a round recomputes the later rounds', function () {
    $first = createRoundCard(referenceCardOn('2026-10-01', [[$this->gab->id, strokesOverPar(20)]]));
    createRoundCard(referenceCardOn('2026-10-10', [[$this->gab->id, strokesOverPar(17)]]));
    createRoundCard(referenceCardOn('2026-10-17', [[$this->gab->id, strokesOverPar(12)]]));

    app(DeleteRound::class)->handle($first);

    expect(grossDifferentialIndexOf($this->gab))->toBe([[88, 170, 170], [83, 120, 145]]);
});
