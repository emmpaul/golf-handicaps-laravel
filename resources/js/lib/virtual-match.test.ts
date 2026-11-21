import { describe, expect, it } from 'vite-plus/test';
import { winnerSummary } from '@/lib/virtual-match';
import type { RankedNet } from '@/lib/virtual-match';

// Spec virtual-match « Encadré du gagnant »: « {net} net · {n} coups devant
// {nom} », « 1 coup » in the singular, the second ones tied joined by « et »,
// first ones tied give « ÉGALITÉ ». The lines come in the order of the choice,
// with the competition rank of the server.
// Mutations:
// - the plural without the test of equality to 1 → the one stroke case reads
//   « 1 coups » and goes red;
// - the gap taken from the last ranked line instead of the second → the three
//   players case reads « 7 coups »;
// - the second ones tied not joined (only the first second) → the tied
//   seconds case reads « devant Sean » alone;
// - a tie at the top not detected → the tie case reads « AURAIT GAGNÉ »;
// - a player without index counted as ranked → the single ranked case gives a
//   summary instead of `null`.

const line = (
    name: string,
    net: number | null,
    rank: number | null,
): RankedNet => ({ name, net, rank });

describe('winnerSummary', () => {
    it('says « 65 net · 3 coups devant Sean » for Manu 65, Sean 68', () => {
        expect(
            winnerSummary([line('Sean', 68, 2), line('Manu', 65, 1)]),
        ).toEqual({
            heading: 'AURAIT GAGNÉ',
            names: 'Manu',
            detail: '65 net · 3 coups devant Sean',
        });
    });

    it('says « 1 coup devant Sean » in the singular for Manu 65, Sean 66', () => {
        expect(
            winnerSummary([line('Manu', 65, 1), line('Sean', 66, 2)]),
        ).toEqual({
            heading: 'AURAIT GAGNÉ',
            names: 'Manu',
            detail: '65 net · 1 coup devant Sean',
        });
    });

    it('joins the second ones tied: « 3 coups devant Sean et Gab »', () => {
        expect(
            winnerSummary([
                line('Manu', 65, 1),
                line('Sean', 68, 2),
                line('Gab', 68, 2),
            ]),
        ).toEqual({
            heading: 'AURAIT GAGNÉ',
            names: 'Manu',
            detail: '65 net · 3 coups devant Sean et Gab',
        });
    });

    it('measures the gap to the second, not to the last', () => {
        expect(
            winnerSummary([
                line('Manu', 65, 1),
                line('Sean', 68, 2),
                line('Gab', 72, 3),
            ]),
        ).toEqual({
            heading: 'AURAIT GAGNÉ',
            names: 'Manu',
            detail: '65 net · 3 coups devant Sean',
        });
    });

    it('gives « ÉGALITÉ », « Gab et Manu », « 70 net » for Gab and Manu at 70', () => {
        expect(
            winnerSummary([
                line('Gab', 70, 1),
                line('Manu', 70, 1),
                line('Sean', 72, 3),
            ]),
        ).toEqual({
            heading: 'ÉGALITÉ',
            names: 'Gab et Manu',
            detail: '70 net',
        });
    });

    it('gives null with fewer than 2 ranked players', () => {
        expect(winnerSummary([])).toBeNull();
        expect(winnerSummary([line('Gab', 70, 1)])).toBeNull();
        expect(
            winnerSummary([line('Gab', 70, 1), line('Sean', null, null)]),
        ).toBeNull();
    });
});
