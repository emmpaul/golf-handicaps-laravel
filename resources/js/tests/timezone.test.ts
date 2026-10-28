import { describe, expect, test } from 'vite-plus/test';

describe('frontend test timezone', () => {
    // The zone name is not asserted: ICU reports America/Montreal under its
    // canonical alias America/Toronto. The offsets prove the zone instead.
    test('a winter UTC instant reads as 23:00 in the display timezone', () => {
        expect(new Date('2026-11-02T04:30:00Z').getHours()).toBe(23);
    });

    test('a summer UTC instant reads as 23:00 in the display timezone', () => {
        expect(new Date('2026-07-15T03:30:00Z').getHours()).toBe(23);
    });

    // new Date('YYYY-MM-DD') reads midnight UTC: west of Greenwich it falls on the
    // previous local day. The fixed TZ makes that defect visible on every machine.
    test('a date-only string read by new Date falls on the previous local day', () => {
        expect(new Date('2026-11-02').getDate()).toBe(1);
    });
});
