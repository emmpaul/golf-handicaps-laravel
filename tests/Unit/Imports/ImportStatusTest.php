<?php

/*
 * Mutation that turns this test red: a label reworded or moved to another status, as
 * « Lecture » instead of « Lecture en cours ».
 */

use App\Domain\Imports\ImportStatus;

test('each status of an import has its label', function () {
    expect(array_map(fn (ImportStatus $status): array => [$status->value, $status->label()], ImportStatus::cases()))
        ->toBe([
            ['queued', 'En attente'],
            ['reading', 'Lecture en cours'],
            ['ready', 'Prêt à vérifier'],
            ['failed', 'Échec'],
        ]);
});
