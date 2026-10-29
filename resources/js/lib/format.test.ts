import { describe, expect, it } from 'vite-plus/test';

import {
    formatIndex,
    formatPlayedOn,
    formatShortPlayedOn,
    formatSignedTenths,
    formatTenths,
    isoToLocalDate,
    localDateToIso,
} from '@/lib/format';

// Spec rounds « Choix de la date au calendrier », scenario « Fuseau horaire à
// l'ouest de Greenwich ». The tests run under TZ=America/Montreal (vite.config.ts).
// Mutation: formatPlayedOn reading `new Date('2026-10-17')` (midnight UTC, the
// evening of the 16th in Montreal) → « 16 octobre 2026 », the first test goes red.
// Mutation: localDateToIso through toISOString() (UTC) → the last test goes red
// for a date picked late in the evening.
describe('formatPlayedOn', () => {
    it('reads 2026-10-17 as the civil day « 17 octobre 2026 »', () => {
        expect(formatPlayedOn('2026-10-17')).toBe('17 octobre 2026');
    });

    it('reads 2025-03-04 as « 4 mars 2025 »', () => {
        expect(formatPlayedOn('2025-03-04')).toBe('4 mars 2025');
    });
});

describe('civil day conversions', () => {
    it('opens 2026-10-17 on the 17th of October, local time', () => {
        const date = isoToLocalDate('2026-10-17');

        expect([date.getFullYear(), date.getMonth(), date.getDate()]).toEqual([
            2026, 9, 17,
        ]);
    });

    it('writes a local evening of the 15th as 2026-10-15', () => {
        expect(localDateToIso(new Date(2026, 9, 15, 23, 30))).toBe(
            '2026-10-15',
        );
    });
});

// Spec players « Index sur la page Joueurs » and handicap-index: tenths of the
// server written with a decimal comma, as formatTenths of the Next.js
// application (toFixed, hence a hyphen-minus for a negative value).
// Mutation: « . » kept instead of « , » → every test of this block goes red.
// Mutation: « (provisoire) » dropped from formatIndex → the provisional test
// goes red.
describe('formatTenths', () => {
    it('writes 174 tenths as « 17,4 »', () => {
        expect(formatTenths(174)).toBe('17,4');
    });

    it('writes -30 tenths as « -3,0 », with a hyphen-minus', () => {
        expect(formatTenths(-30)).toBe('-3,0');
    });
});

describe('formatIndex', () => {
    it('writes a provisional 145 as « 14,5 (provisoire) »', () => {
        expect(formatIndex(145, true)).toBe('14,5 (provisoire)');
    });

    it('writes an established 100 as « 10,0 »', () => {
        expect(formatIndex(100, false)).toBe('10,0');
    });

    it('writes no index as « — »', () => {
        expect(formatIndex(null, false)).toBe('—');
    });
});

// Spec home-dashboard « Index des joueurs »: signed delta in tenths, « = » when
// nothing moved, the typographic minus U+2212 for a fall (formatSignedTenths of
// the Next.js application).
// Mutation: hyphen-minus « - » instead of U+2212 → the negative tests go red.
// Mutation: « 0 » instead of « = » → the zero test goes red.
describe('formatSignedTenths', () => {
    it('writes 3 tenths as « +0,3 »', () => {
        expect(formatSignedTenths(3)).toBe('+0,3');
    });

    it('writes -3 tenths as « −0,3 » with U+2212', () => {
        const signed = formatSignedTenths(-3);

        expect(signed.charCodeAt(0)).toBe(0x2212);
        expect(signed).toBe('−0,3');
    });

    it('writes 0 tenths as « = »', () => {
        expect(formatSignedTenths(0)).toBe('=');
    });

    it('writes -25 tenths as « −2,5 »', () => {
        expect(formatSignedTenths(-25)).toBe('−2,5');
    });
});

// Spec handicap-index « Liste des rounds d'un joueur »: short date of a row, a
// civil day under TZ=America/Montreal.
// Mutation: date read through `new Date('2026-10-17')` (midnight UTC) →
// « 16 oct. 2026 », the first test goes red.
describe('formatShortPlayedOn', () => {
    it('reads 2026-10-17 as « 17 oct. 2026 »', () => {
        expect(formatShortPlayedOn('2026-10-17')).toBe('17 oct. 2026');
    });

    it('reads 2026-10-01 as « 1 oct. 2026 »', () => {
        expect(formatShortPlayedOn('2026-10-01')).toBe('1 oct. 2026');
    });
});
