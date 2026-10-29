<?php

/*
 * Spec scorecard-import « Sauvegarde automatique de la correction d'un import » (lecture
 * d'origine conservée), « Reprise de la correction enregistrée » (deux modifications
 * concurrentes), « Correction refusée hors vérification », « Import introuvable »; design D6, D7.
 * jsonb keeps its keys in its own order: the saved correction is compared with toEqual.
 *
 * Mutations that turn these tests red:
 * - the UPDATE of SaveImportCorrection without `round_id IS NULL`: the correction of a saved
 *   import is written, and answered 204 instead of 409;
 * - the correction written over the draft (`draft` updated with the card): the draft of the
 *   import changes;
 * - the status left out of the UPDATE: the correction of a queued import is written;
 * - the 409 answered with ImportBeingRead for a saved import (round not checked first):
 *   « Lecture en cours » instead of « Cette carte est déjà enregistrée ».
 */

use App\Models\Import;
use App\Models\Round;
use Database\Factories\ImportFactory;

/**
 * A correction of the reference card: Gab on line 0 read « GAB », date changed to 2026-10-13.
 *
 * @return array{version: int, card: array<string, mixed>}
 */
function referenceCorrection(?string $gabId, string $playedOn = '2026-10-13'): array
{
    $card = referenceCardData($gabId, null);
    $card['playedOn'] = $playedOn;
    $card['lines'][0]['readName'] = 'GAB';
    $card['lines'][0]['totals'] = ['out' => 38, 'in' => 34, 'total' => 72];
    $card['parTotals'] = ['out' => 36, 'in' => 35, 'total' => 71];

    return ['version' => 1, 'card' => $card];
}

beforeEach(function () {
    $this->withPasscodeSession();
    createPlayersOnTest('Gab');
});

test('a correction of a ready import is saved, the draft unchanged', function () {
    $import = Import::factory()->ready()->create();

    $this->putJson("/imports/{$import->id}/correction", referenceCorrection($this->gab->id))
        ->assertNoContent();

    $import->refresh();
    expect($import->correction)->toEqual(referenceCorrection($this->gab->id))
        ->and($import->draft)->toEqual(ImportFactory::referenceDraft());
});

test('a correction of a failed import is saved', function () {
    $import = Import::factory()->failed('Lecture IA indisponible')->create();

    $this->putJson("/imports/{$import->id}/correction", referenceCorrection(null))->assertNoContent();

    expect($import->fresh()->correction)->toEqual(referenceCorrection(null));
});

test('a correction of an import being read is refused', function (string $state) {
    $import = Import::factory()->{$state}()->create();

    $this->putJson("/imports/{$import->id}/correction", referenceCorrection(null))
        ->assertConflict()
        ->assertExactJson(['error' => 'Lecture en cours']);

    expect($import->fresh()->correction)->toBeNull();
})->with(['queued', 'reading']);

test('a correction of a saved import is refused, the saved correction unchanged', function () {
    $import = Import::factory()->savedAs(Round::factory()->create())
        ->create(['correction' => referenceCorrection(null, '2026-10-17')]);

    $this->putJson("/imports/{$import->id}/correction", referenceCorrection($this->gab->id))
        ->assertConflict()
        ->assertExactJson(['error' => 'Cette carte est déjà enregistrée']);

    expect($import->fresh()->correction)->toEqual(referenceCorrection(null, '2026-10-17'));
});

test('a correction of an unknown import answers 404', function () {
    $this->putJson('/imports/0190a6b2-7c3e-7000-8000-000000000001/correction', referenceCorrection(null))
        ->assertNotFound()
        ->assertExactJson(['error' => 'Import introuvable']);
});

test('a body of another shape is refused', function (array $body) {
    $import = Import::factory()->ready()->create();

    $this->putJson("/imports/{$import->id}/correction", $body)->assertUnprocessable();

    expect($import->fresh()->correction)->toBeNull();
})->with([
    'without version' => [['card' => referenceCorrection(null)['card']]],
    'five lines' => [function (): array {
        $correction = referenceCorrection(null);
        $correction['card']['lines'] = array_fill(0, 5, $correction['card']['lines'][1]);

        return $correction;
    }],
    'a player that is not a uuid' => [function (): array {
        $correction = referenceCorrection(null);
        $correction['card']['lines'][1]['playerId'] = '12';

        return $correction;
    }],
]);

test('the last correction received wins', function () {
    $import = Import::factory()->ready()->create();

    $this->putJson("/imports/{$import->id}/correction", referenceCorrection(null, '2026-10-12'))->assertNoContent();
    $this->putJson("/imports/{$import->id}/correction", referenceCorrection($this->gab->id, '2026-10-13'))->assertNoContent();

    expect($import->fresh()->correction)->toEqual(referenceCorrection($this->gab->id, '2026-10-13'));
});
