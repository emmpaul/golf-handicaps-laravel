import { describe, expect, it } from 'vite-plus/test';

import {
    formatIndex,
    formatPlayedOn,
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
