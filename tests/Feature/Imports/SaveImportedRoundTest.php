<?php

/*
 * Spec scorecard-import « Enregistrement d'un import », « Contrôle des totaux lus » (demande
 * incohérente reçue par le serveur), « Import retiré avec son round », « Conservation de la
 * photo »; spec players « Apprentissage des alias »; design D5, D6.
 *
 * Mutations that turn these tests red:
 * - the aliases learned before CreateRound, outside the transaction of SaveImportedRound: the
 *   incoherent card is refused but SEANP has moved to Sean;
 * - the `exact` comparison ignored (every read name assigned): the alias GAB of Gab is deleted
 *   and written again, with another id;
 * - photo_path not copied to the round: the round has no photo;
 * - the import not linked to its round: round_id stays null;
 * - the round_id check removed from SaveImportedRound: saving the same import again creates a
 *   second round and links the import to it;
 * - the check of ImportRoundController placed after FindDuplicateRound: the second save finds
 *   the round of the import and comes back with the duplicate flash instead of the refusal.
 */

use App\Domain\Imports\Actions\SaveImportedRound;
use App\Domain\Imports\Exceptions\ImportAlreadySaved;
use App\Domain\Rounds\Exceptions\IncoherentRoundCard;
use App\Domain\Rounds\RoundCard;
use App\Models\Import;
use App\Models\Player;
use App\Models\PlayerAlias;
use App\Models\Round;
use App\Models\Score;
use Illuminate\Support\Facades\Storage;

/**
 * The reference card as the correction screen sends it: line 0 read « GAB », line 1 read
 * « SEANP », the totals read coherent with the holes.
 *
 * @return array<string, mixed>
 */
function importedReferenceCard(?string $line0, ?string $line1, string $readName1 = 'SEANP'): array
{
    $card = referenceCardData($line0, $line1);
    $card['parTotals'] = ['out' => 36, 'in' => 35, 'total' => 71];
    $card['lines'][0] += ['readName' => 'GAB', 'totals' => ['out' => 38, 'in' => 34, 'total' => 72]];
    $card['lines'][1] += ['readName' => $readName1, 'totals' => ['out' => 46, 'in' => 42, 'total' => 88]];

    return $card;
}

/**
 * The gross of each player of a round, keyed by name.
 *
 * @return array<string, int>
 */
function grossByName(Round $round): array
{
    return Score::query()
        ->join('players', 'players.id', '=', 'scores.player_id')
        ->where('scores.round_id', $round->id)
        ->groupBy('players.name')
        ->orderBy('players.name')
        ->selectRaw('players.name, sum(scores.strokes) as gross')
        ->pluck('gross', 'name')
        ->map(fn ($gross): int => (int) $gross)
        ->all();
}

beforeEach(function () {
    Storage::fake('photos');
    $this->withPasscodeSession();
    $this->gab = Player::factory()->withAlias()->create(['name' => 'Gab']);
    $this->manu = Player::factory()->withAlias()->create(['name' => 'Manu']);
    $this->sean = Player::factory()->withAlias()->create(['name' => 'Sean']);
    PlayerAlias::factory()->for($this->manu)->create(['alias' => 'SEANP']);
    $this->import = Import::factory()->ready()->withPhoto()->create();
});

test('the reference card saves its round with the photo of the import and learns the moved alias', function () {
    $gabAlias = PlayerAlias::query()->where('alias', 'GAB')->sole();

    $this->withHeader('X-Inertia', 'true')
        ->post("/imports/{$this->import->id}/round", importedReferenceCard($this->gab->id, $this->sean->id))
        ->assertRedirect('/rounds')
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Round enregistré.']);

    $round = Round::query()->sole();
    expect(grossByName($round))->toBe(['Gab' => 72, 'Sean' => 88])
        ->and($round->photo_path)->toBe($this->import->photo_path)
        ->and($this->import->fresh()->round_id)->toBe($round->id)
        ->and(playersWithAliases())->toBe([
            'Gab' => ['GAB'],
            'Manu' => ['MANU'],
            'Sean' => ['SEAN', 'SEANP'],
        ])
        ->and(PlayerAlias::query()->where('alias', 'GAB')->sole()->id)->toBe($gabAlias->id);
});

test('a pre-selected close name kept by the person becomes an alias', function () {
    app(SaveImportedRound::class)->handle(
        $this->import,
        RoundCard::fromArray(importedReferenceCard($this->gab->id, $this->manu->id, 'MANV')),
    );

    expect(playersWithAliases()['Manu'])->toBe(['MANU', 'MANV', 'SEANP']);
});

test('an incoherent card is refused without round nor alias', function () {
    $card = importedReferenceCard($this->gab->id, $this->sean->id);
    $card['lines'][0]['strokes'][8] = 3;
    $card['lines'][0]['totals']['total'] = 70;

    try {
        app(SaveImportedRound::class)->handle($this->import, RoundCard::fromArray($card));
        $this->fail('The incoherent card was saved.');
    } catch (IncoherentRoundCard $refusal) {
        expect(array_map(fn ($problem): string => $problem->message, $refusal->problems))
            ->toBe(['Somme aller 36 ≠ OUT 38', 'OUT + IN 72 ≠ TOT 70']);
    }

    expect(Round::query()->count())->toBe(0)
        ->and($this->import->fresh()->round_id)->toBeNull()
        ->and(playersWithAliases()['Manu'])->toBe(['MANU', 'SEANP'])
        ->and(playersWithAliases()['Sean'])->toBe(['SEAN']);
});

test('an incoherent card sent to the server comes back with the count of its problems', function () {
    $card = importedReferenceCard($this->gab->id, $this->sean->id);
    $card['lines'][0]['strokes'][8] = 3;
    $card['lines'][0]['totals']['total'] = 72;

    $this->withHeader('X-Inertia', 'true')
        ->post("/imports/{$this->import->id}/round", $card)
        ->assertInertiaFlash('toast', ['type' => 'error', 'message' => '1 problème à corriger']);

    expect(Round::query()->count())->toBe(0);
});

test('a duplicate without force comes back with the duplicate flash and writes nothing', function () {
    $existing = createRoundCard(referenceCardData($this->gab->id, $this->sean->id));

    $this->withHeader('X-Inertia', 'true')
        ->post("/imports/{$this->import->id}/round", importedReferenceCard($this->gab->id, $this->sean->id))
        ->assertInertiaFlash('duplicate', ['roundId' => $existing->id]);

    expect(Round::query()->count())->toBe(1)
        ->and($this->import->fresh()->round_id)->toBeNull()
        ->and(playersWithAliases()['Sean'])->toBe(['SEAN']);
});

test('deleting the saved round removes its import and the photo', function () {
    $round = app(SaveImportedRound::class)->handle(
        $this->import,
        RoundCard::fromArray(importedReferenceCard($this->gab->id, $this->sean->id)),
    );

    $this->delete("/rounds/{$round->id}")->assertRedirect('/rounds');

    expect(Import::query()->count())->toBe(0)
        ->and(Storage::disk('photos')->exists($this->import->photo_path))->toBeFalse();
    expect($this->get('/import')->inertiaProps('imports'))->toBe([]);
});

test('deleting a round typed by hand leaves the imports', function () {
    $round = createRoundCard(referenceCardData($this->gab->id, $this->sean->id));

    $this->delete("/rounds/{$round->id}")->assertRedirect('/rounds');

    expect(Import::query()->pluck('id')->all())->toBe([$this->import->id]);
});

describe('an import already saved', function () {
    beforeEach(function () {
        $this->round = app(SaveImportedRound::class)->handle(
            $this->import,
            RoundCard::fromArray(importedReferenceCard($this->gab->id, $this->sean->id)),
        );
        $this->aliases = playersWithAliases();
    });

    test('is not saved again', function () {
        // The import as the second tab still has it: without its round.
        expect(fn () => app(SaveImportedRound::class)->handle(
            $this->import,
            RoundCard::fromArray(importedReferenceCard($this->gab->id, $this->sean->id)),
        ))->toThrow(ImportAlreadySaved::class, 'Cette carte est déjà enregistrée');

        expect(Round::query()->count())->toBe(1)
            ->and(playersWithAliases())->toBe($this->aliases)
            ->and($this->import->fresh()->round_id)->toBe($this->round->id);
    });

    test('sent again from a second tab, is refused without the duplicate flash', function () {
        $this->withHeader('X-Inertia', 'true')
            ->post("/imports/{$this->import->id}/round", importedReferenceCard($this->gab->id, $this->sean->id))
            ->assertInertiaFlash('toast', ['type' => 'error', 'message' => 'Cette carte est déjà enregistrée'])
            ->assertInertiaFlashMissing('duplicate');

        expect(Round::query()->count())->toBe(1);
    });
});
