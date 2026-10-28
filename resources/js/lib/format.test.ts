import { describe, expect, it } from 'vite-plus/test';

import { formatPlayedOn, isoToLocalDate, localDateToIso } from '@/lib/format';

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
