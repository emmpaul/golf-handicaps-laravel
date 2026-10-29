<?php

/*
 * Spec scorecard-import « Liste des imports », « Lecture en tâche de fond », « Contenu lu sur
 * la carte », « Date au format du simulateur », « Correction manuelle d'un import », « Repère de
 * lecture par l'IA », « Reprise de la correction enregistrée », « Enregistrement d'un import »
 * (adresse après l'enregistrement), « Import introuvable »; spec players « Reconnaissance des
 * noms lus sur la carte »; spec app-shell « Adresses des sections »; design D2, D6, D7.
 * The action `retry` of a failed import is tested in RetryImportReadingTest.
 *
 * Mutations that turn these tests red:
 * - ImportList sorted by id instead of created_at: the imports come back in the reverse order
 *   of their dates, the first created (most recent date) last;
 * - ImportCorrection::fromArray() that throws on an unreadable correction instead of returning
 *   null: the page of that import answers 500 instead of the draft;
 * - the date of the day taken in UTC (now()->toDateString()): at 2026-10-18 03:30 UTC the
 *   draft without date gets 2026-10-18 instead of 2026-10-17;
 * - a missing player of the correction kept: the line keeps the id of a player that no longer
 *   exists;
 * - readByAi computed from the draft only when the screen starts from it (correction ignored):
 *   the ready import with a correction gets readByAi false.
 */

use App\Domain\Imports\ImportStatus;
use App\Models\Import;
use App\Models\Player;
use App\Models\Round;
use Carbon\CarbonImmutable;
use Database\Factories\ImportFactory;
use Inertia\Testing\AssertableInertia as Assert;

const UNKNOWN_IMPORT = '0190a6b2-7c3e-7000-8000-000000000001';

/**
 * The 18 empty holes of the empty screen.
 *
 * @return list<array{par: null, strokeIndex: null, distance: null}>
 */
function emptyHoles(): array
{
    return array_fill(0, 18, ['par' => null, 'strokeIndex' => null, 'distance' => null]);
}

/**
 * The 18 holes of the reference card IMG_3064, keyed as the editor sends them.
 *
 * @return list<array{par: int, strokeIndex: int, distance: int}>
 */
function referenceHoles(): array
{
    return referenceCardData(null, null)['holes'];
}

test('without a session the import pages redirect to /login', function (string $uri) {
    $this->get($uri)->assertRedirect('/login');
})->with([
    'list' => ['/import'],
    'import' => ['/imports/'.UNKNOWN_IMPORT],
]);

describe('with a session', function () {
    beforeEach(function () {
        $this->withPasscodeSession();
        $this->travelTo(CarbonImmutable::parse('2026-10-20 15:00:00', 'UTC'));
    });

    test('the list gets the 20 most recent imports of 21, latest first', function () {
        // Created in this order, so with increasing ids, but dated the other way round.
        $ids = [];
        foreach (range(0, 20) as $i) {
            $import = Import::factory()->create();
            $import->forceFill(['created_at' => CarbonImmutable::parse('2026-10-20 12:00:00', 'UTC')->subMinutes($i)])->save();
            $ids[] = $import->id;
        }

        $response = $this->get('/import')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('import/index'));

        expect(array_column($response->inertiaProps('imports'), 'id'))->toBe(array_slice($ids, 0, 20));
    });

    test('each import of the list has its label, failure and actions', function () {
        $at = fn (string $time) => ['created_at' => CarbonImmutable::parse("2026-10-20 {$time}", 'UTC')];
        $round = Round::factory()->create();
        $saved = Import::factory()->savedAs($round)->create($at('12:05:00'));
        $ready = Import::factory()->ready()->create($at('12:04:00'));
        $failed = Import::factory()->failed('Budget IA du mois atteint')->create($at('12:03:00'));
        $reading = Import::factory()->reading()->create($at('12:02:00'));
        $queued = Import::factory()->queued()->create($at('12:01:00'));

        $response = $this->get('/import')->assertOk();

        expect($response->inertiaProps('imports'))->toBe([
            ['id' => $saved->id, 'status' => 'ready', 'label' => 'Prêt à vérifier', 'failure' => null, 'roundId' => $round->id, 'createdAt' => '2026-10-20 08:05', 'actions' => ['viewRound']],
            ['id' => $ready->id, 'status' => 'ready', 'label' => 'Prêt à vérifier', 'failure' => null, 'roundId' => null, 'createdAt' => '2026-10-20 08:04', 'actions' => ['review', 'delete']],
            ['id' => $failed->id, 'status' => 'failed', 'label' => 'Échec', 'failure' => 'Budget IA du mois atteint', 'roundId' => null, 'createdAt' => '2026-10-20 08:03', 'actions' => ['correctEmpty', 'delete']],
            ['id' => $reading->id, 'status' => 'reading', 'label' => 'Lecture en cours', 'failure' => null, 'roundId' => null, 'createdAt' => '2026-10-20 08:02', 'actions' => []],
            ['id' => $queued->id, 'status' => 'queued', 'label' => 'En attente', 'failure' => null, 'roundId' => null, 'createdAt' => '2026-10-20 08:01', 'actions' => ['delete']],
        ]);
    });

    test('the list says whether the photos can be read', function (?string $key, bool $enabled) {
        config(['ai.providers.anthropic.key' => $key]);

        $this->get('/import')->assertOk()->assertInertia(fn (Assert $page) => $page->where('readingEnabled', $enabled));
    })->with([
        'without key' => [null, false],
        'with a key' => ['test-key', true],
    ]);

    test('an unknown sub-address of the import answers 404', function (string $uri) {
        $this->get($uri)->assertNotFound();
    })->with([
        'other sub-address' => ['/import/xyz'],
        'numeric import' => ['/imports/12'],
        'unknown import' => ['/imports/'.UNKNOWN_IMPORT],
    ]);

    test('a ready import opens on the draft, each read name matched to a player', function () {
        $gab = Player::factory()->withAlias()->create(['name' => 'Gab']);
        $draft = ImportFactory::referenceDraft();
        $draft['players'][1]['name'] = 'JULIEN';
        $import = Import::factory()->ready($draft)->create();

        $response = $this->get("/imports/{$import->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('imports/show'));

        expect($response->inertiaProps('import'))->toBe([
            'id' => $import->id,
            'status' => 'ready',
            'label' => 'Prêt à vérifier',
            'failure' => null,
        ])->and($response->inertiaProps('editor'))->toBe([
            'card' => [
                'courseName' => 'BETHPAGE BLACK',
                'tees' => 'Red',
                'playedOn' => '2026-10-17',
                'holes' => referenceHoles(),
                'lines' => [
                    [
                        'playerId' => $gab->id,
                        'strokes' => [5, 5, 3, 5, 4, 4, 4, 3, 5, 4, 4, 4, 4, 3, 4, 5, 3, 3],
                        'readName' => 'GAB',
                        'totals' => ['out' => 38, 'in' => 34, 'total' => 72],
                    ],
                    [
                        'playerId' => null,
                        'strokes' => [7, 5, 3, 7, 6, 5, 5, 3, 5, 3, 6, 5, 7, 4, 5, 4, 3, 5],
                        'readName' => 'JULIEN',
                        'totals' => ['out' => 46, 'in' => 42, 'total' => 88],
                    ],
                ],
                'parTotals' => ['out' => 36, 'in' => 35, 'total' => 71],
            ],
            'fromReading' => true,
            'readByAi' => true,
        ])->and($response->inertiaProps('players'))->toBe([['id' => $gab->id, 'name' => 'Gab']])
            ->and($response->inertiaProps('courses'))->toBe([]);
    });

    test('a draft without date opens on the day in Montreal', function () {
        $this->travelTo(CarbonImmutable::parse('2026-10-18 03:30:00', 'UTC'));
        $draft = ImportFactory::referenceDraft();
        $draft['playedOn'] = null;
        $import = Import::factory()->ready($draft)->create();

        $response = $this->get("/imports/{$import->id}")->assertOk();

        expect($response->inertiaProps('editor.card.playedOn'))->toBe('2026-10-17');
    });

    test('a failed import opens on the empty screen of the day, with its failure', function () {
        $import = Import::factory()->failed('Lecture IA indisponible')->create();

        $response = $this->get("/imports/{$import->id}")->assertOk();

        expect($response->inertiaProps('import.failure'))->toBe('Lecture IA indisponible')
            ->and($response->inertiaProps('editor'))->toBe([
                'card' => [
                    'courseName' => null,
                    'tees' => null,
                    'playedOn' => '2026-10-20',
                    'holes' => emptyHoles(),
                    'lines' => [['playerId' => null, 'strokes' => array_fill(0, 18, null)]],
                ],
                'fromReading' => false,
                'readByAi' => false,
            ]);
    });

    test('a saved correction is the opening state, still read by the AI', function () {
        createPlayersOnTest('Gab', 'Manu');
        $card = referenceCardData($this->gab->id, $this->manu->id);
        $card['playedOn'] = '2026-10-13';
        $card['lines'][0]['readName'] = 'GAB';
        $card['lines'][0]['totals'] = ['out' => 38, 'in' => 34, 'total' => 72];
        $import = Import::factory()->ready()->create(['correction' => ['version' => 1, 'card' => $card]]);

        $response = $this->get("/imports/{$import->id}")->assertOk();

        expect($response->inertiaProps('editor'))->toBe([
            'card' => [
                'courseName' => 'BETHPAGE BLACK',
                'tees' => 'Red',
                'playedOn' => '2026-10-13',
                'holes' => referenceHoles(),
                'lines' => [
                    [
                        'playerId' => $this->gab->id,
                        'strokes' => [5, 5, 3, 5, 4, 4, 4, 3, 5, 4, 4, 4, 4, 3, 4, 5, 3, 3],
                        'readName' => 'GAB',
                        'totals' => ['out' => 38, 'in' => 34, 'total' => 72],
                    ],
                    [
                        'playerId' => $this->manu->id,
                        'strokes' => [7, 5, 3, 7, 6, 5, 5, 3, 5, 3, 6, 5, 7, 4, 5, 4, 3, 5],
                    ],
                ],
            ],
            'fromReading' => false,
            'readByAi' => true,
        ]);
    });

    test('the correction of a failed import is the opening state', function () {
        $card = ['courseName' => 'BETHPAGE BLACK', 'tees' => null, 'playedOn' => '2026-10-13', 'holes' => emptyHoles(), 'lines' => [['playerId' => null, 'strokes' => array_fill(0, 18, null)]]];
        $import = Import::factory()->failed('Lecture IA indisponible')->create(['correction' => ['version' => 1, 'card' => $card]]);

        $response = $this->get("/imports/{$import->id}")->assertOk();

        expect($response->inertiaProps('editor.card.courseName'))->toBe('BETHPAGE BLACK')
            ->and($response->inertiaProps('editor.card.playedOn'))->toBe('2026-10-13')
            ->and($response->inertiaProps('editor.readByAi'))->toBeFalse();
    });

    test('an unreadable correction opens on the draft', function (array $correction) {
        $import = Import::factory()->ready()->create(['correction' => $correction]);

        $response = $this->get("/imports/{$import->id}")->assertOk();

        expect($response->inertiaProps('editor.card.playedOn'))->toBe('2026-10-17')
            ->and($response->inertiaProps('editor.card.lines.0.readName'))->toBe('GAB')
            ->and($response->inertiaProps('editor.fromReading'))->toBeTrue();
    })->with([
        'another version' => [['version' => 2, 'card' => referenceCardData(null, null)]],
        'a line of 17 strokes' => [function (): array {
            $card = referenceCardData(null, null);
            array_pop($card['lines'][0]['strokes']);

            return ['version' => 1, 'card' => $card];
        }],
        'no card' => [['version' => 1]],
    ]);

    test('a player of the correction that no longer exists leaves its line without player', function () {
        createPlayersOnTest('Gab');
        $card = referenceCardData($this->gab->id, '0190a6b2-7c3e-7000-8000-0000000000ff');
        $import = Import::factory()->ready()->create(['correction' => ['version' => 1, 'card' => $card]]);

        $response = $this->get("/imports/{$import->id}")->assertOk();

        expect($response->inertiaProps('editor.card.lines.0.playerId'))->toBe($this->gab->id)
            ->and($response->inertiaProps('editor.card.lines.1.playerId'))->toBeNull();
    });

    test('a saved import opens the page of its round', function () {
        $round = Round::factory()->create();
        $import = Import::factory()->savedAs($round)->create();

        $this->get("/imports/{$import->id}")->assertRedirect("/rounds/{$round->id}");
        expect($import->fresh()->status)->toBe(ImportStatus::Ready);
    });
});
