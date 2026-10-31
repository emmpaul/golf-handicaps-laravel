import { describe, expect, it } from 'vite-plus/test';

import {
    formatDayMonthShort,
    formatIndex,
    formatPlayedOn,
    formatShortPlayedOn,
    formatClockTime,
    formatSignedTenths,
    formatStoredAt,
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
// application, a negative value with the minus sign U+2212 (DO44).
// Mutation: « . » kept instead of « , » → every test of this block goes red.
// Mutation: « (provisoire) » dropped from formatIndex → the provisional test
// goes red.
describe('formatTenths', () => {
    it('writes 174 tenths as « 17,4 »', () => {
        expect(formatTenths(174)).toBe('17,4');
    });

    // V4, DO44: a negative index (a plus handicap) is written with the
    // typographic minus U+2212, like the deltas. Mutation: toFixed's
    // hyphen-minus kept → these two tests go red.
    it('writes -30 tenths as « −3,0 », with the minus sign U+2212', () => {
        expect(formatTenths(-30)).toBe('\u22123,0');
    });

    it('writes an index of -5 tenths as « −0,5 »', () => {
        expect(formatTenths(-5)).toBe('\u22120,5');
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

// Spec home-dashboard « Dernier round »: « 17 OCT. » under TZ=America/Montreal.
// Mutations: date read through `new Date('2026-10-17')` (midnight UTC) →
// « 16 OCT. »; the year kept (shortPlayedOnFormat) → « 17 OCT. 2026 ».
describe('formatDayMonthShort', () => {
    it('reads 2026-10-17 as « 17 OCT. »', () => {
        expect(formatDayMonthShort('2026-10-17')).toBe('17 OCT.');
    });

    it('reads 2026-05-04 as « 4 MAI », a month written whole', () => {
        expect(formatDayMonthShort('2026-05-04')).toBe('4 MAI');
    });
});

// Spec offline-mode « Bandeau hors ligne », scenario « Copie affichée »: a copy
// kept at 21:04 in Montréal on 30 October 2026, 01:04 UTC on the 31st, reads
// « 30 oct. à 21:04 » under TZ=America/Montreal.
// Mutations: hour formatted in UTC (`timeZone: 'UTC'`) → « 31 oct. à 01:04 »,
// both tests go red; 12-hour clock → « 9:04 PM »-like, red.
describe('formatStoredAt', () => {
    it('reads 2026-10-31T01:04:00Z as « 30 oct. à 21:04 »', () => {
        expect(formatStoredAt('2026-10-31T01:04:00Z')).toBe('30 oct. à 21:04');
    });

    it('reads 2026-05-04T12:07:00.000Z as « 4 mai à 08:07 »', () => {
        expect(formatStoredAt('2026-05-04T12:07:00.000Z')).toBe(
            '4 mai à 08:07',
        );
    });
});

// Spec scorecard-import « Liste des photos en attente d'envoi »: the time a
// photo was taken, « 21:04 » for 01:04 UTC under TZ=America/Montreal.
// Mutation: time formatted in UTC → « 01:04 », red.
describe('formatClockTime', () => {
    it('reads 2026-10-31T01:04:00.000Z as « 21:04 »', () => {
        expect(formatClockTime('2026-10-31T01:04:00.000Z')).toBe('21:04');
    });

    it('reads 2026-05-04T12:07:00.000Z as « 08:07 »', () => {
        expect(formatClockTime('2026-05-04T12:07:00.000Z')).toBe('08:07');
    });
});
